<?php

namespace App\Services;

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class MercadoPagoWrapper
{
    private array $items = [];
    private array $config;
    private ?Order $order = null;

    public function __construct()
    {
        $this->config = config('mercadopago');
        
        // Configurar MercadoPago SDK
        MercadoPagoConfig::setAccessToken($this->config['access_token']);
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
    }

    public function begin(callable $callback): array
    {
        $this->items = [];
        $callback($this);
        
        return $this->createPreference();
    }

    public function addItem(array $item): self
    {
        $this->items[] = [
            'id' => $item['id'] ?? uniqid(),
            'title' => $item['title'],
            'quantity' => $item['qtty'] ?? $item['quantity'] ?? 1,
            'unit_price' => (float) $item['price'],
            'currency_id' => $item['currency'] ?? $this->config['currency'],
        ];

        return $this;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function createPreference(): array
    {
        try {
            $client = new PreferenceClient();
            
            $preferenceData = [
                'items' => $this->items,
                'external_reference' => $this->order ? (string) $this->order->id : null,
                'statement_descriptor' => $this->config['statement_descriptor'],
                'notification_url' => url('/api/webhooks/mercadopago'),
                'expires' => true,
                'expiration_date_from' => now()->toISOString(),
                'expiration_date_to' => now()->addHours(24)->toISOString(),
            ];

            // Agregar URLs de retorno sin parámetros adicionales
            $preferenceData['back_urls'] = [
                'success' => $this->config['success_url'],
                'failure' => $this->config['failure_url'],
                'pending' => $this->config['pending_url'],
            ];
            $preferenceData['auto_return'] = 'approved';

            $preference = $client->create($preferenceData);

            // Crear registro de pago si hay orden asociada
            if ($this->order) {
                $this->createPaymentRecord($preference);
            }

            return [
                'id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point,
                'items' => $this->items,
                'order_id' => $this->order?->id,
            ];

        } catch (MPApiException $e) {
            $apiResponse = $e->getApiResponse();
            $errorDetails = $apiResponse ? $apiResponse->getContent() : 'No details available';
            
            Log::error('Error creating MercadoPago preference', [
                'error' => $e->getMessage(),
                'details' => $errorDetails,
                'items' => $this->items,
                'order_id' => $this->order?->id,
                'status_code' => $e->getCode(),
            ]);

            // Proporcionar más información del error
            $errorMessage = 'Error al crear la preferencia de pago: ' . $e->getMessage();
            if ($apiResponse) {
                if (is_string($errorDetails)) {
                    $content = json_decode($errorDetails, true);
                    if (isset($content['message'])) {
                        $errorMessage .= ' - ' . $content['message'];
                    }
                } elseif (is_array($errorDetails) && isset($errorDetails['message'])) {
                    $errorMessage .= ' - ' . $errorDetails['message'];
                }
            }

            throw new \Exception($errorMessage);
        }
    }

    public function getPayment(string $paymentId): ?array
    {
        try {
            // Usar CURL directo para evitar problemas con el SDK y diferentes formatos de ID
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . $paymentId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->config['access_token'],
                    'Content-Type: application/json'
                ],
                CURLOPT_USERAGENT => 'MasterColorAPI/1.0'
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                Log::error('CURL error fetching payment from MercadoPago', [
                    'payment_id' => $paymentId,
                    'curl_error' => $curlError,
                ]);
                return null;
            }

            if ($httpCode !== 200) {
                Log::error('MercadoPago API error fetching payment', [
                    'payment_id' => $paymentId,
                    'http_code' => $httpCode,
                    'response' => substr($response, 0, 500),
                ]);
                return null;
            }

            $payment = json_decode($response, true);

            if (!$payment || !isset($payment['id'])) {
                Log::error('Invalid payment response from MercadoPago', [
                    'payment_id' => $paymentId,
                    'response' => substr($response, 0, 200),
                ]);
                return null;
            }

            return [
                'id' => $payment['id'],
                'status' => $payment['status'],
                'status_detail' => $payment['status_detail'] ?? null,
                'transaction_amount' => $payment['transaction_amount'],
                'currency_id' => $payment['currency_id'],
                'external_reference' => $payment['external_reference'] ?? null,
                'payment_method' => $payment['payment_method_id'] ?? null,
                'installments' => $payment['installments'] ?? null,
                'date_created' => $payment['date_created'] ?? null,
                'date_last_updated' => $payment['date_last_updated'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching payment from MercadoPago', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function processWebhookNotification(array $data): bool
    {
        try {
            // Extraer payment ID desde diferentes formatos de webhook
            $paymentId = $this->extractPaymentIdFromWebhook($data);
            
            if (!$paymentId) {
                Log::warning('Webhook notification without payment ID', $data);
                return false;
            }

            // Limpiar el payment ID para manejar formatos no estándar
            $paymentId = $this->cleanPaymentId($paymentId);
            
            Log::info('Processing webhook notification', [
                'extracted_payment_id' => $paymentId,
                'webhook_data' => $data
            ]);

            $paymentData = $this->getPayment($paymentId);
            
            if (!$paymentData) {
                Log::error('Could not fetch payment data', ['payment_id' => $paymentId]);
                return false;
            }

            $orderId = $paymentData['external_reference'];
            
            if (!$orderId) {
                Log::warning('Payment without external reference', ['payment_id' => $paymentId]);
                return false;
            }

            // Buscar pago por external_id o por order_id como fallback
            $payment = Payment::where('external_id', $paymentId)->first();
            
            if (!$payment) {
                // Fallback: buscar por order_id y actualizar external_id
                $payment = Payment::where('order_id', $orderId)
                    ->where('payment_method', 'MercadoPago')
                    ->whereNull('external_id')
                    ->first();
                    
                if ($payment) {
                    $payment->update(['external_id' => $paymentId]);
                    Log::info('Updated external_id for existing payment', [
                        'payment_id' => $paymentId,
                        'order_id' => $orderId,
                        'internal_payment_id' => $payment->id,
                    ]);
                }
            }
            
            if (!$payment) {
                Log::warning('Payment record not found', [
                    'payment_id' => $paymentId,
                    'order_id' => $orderId,
                ]);
                return false;
            }

            // Actualizar el pago
            $payment->update([
                'status' => $this->mapMercadoPagoStatus($paymentData['status']),
                'external_response' => json_encode($paymentData),
            ]);

            // Actualizar la orden si corresponde
            if ($paymentData['status'] === 'approved') {
                $payment->order->update(['status' => 'pendiente']);
                // Descontar stock automáticamente
                app(\App\Services\StockMovementService::class)->processOrderStockReduction($payment->order);
            } elseif (in_array($paymentData['status'], ['rejected', 'cancelled'])) {
                $payment->order->update(['status' => 'pago_fallido']);
            }

            Log::info('Payment status updated', [
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'status' => $paymentData['status'],
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error processing webhook notification', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return false;
        }
    }

    private function createPaymentRecord($preference): void
    {
        $totalAmount = array_sum(array_map(function ($item) {
            return $item['unit_price'] * $item['quantity'];
        }, $this->items));

        // Verificar si ya existe un pago para esta orden
        $existingPayment = Payment::where('order_id', $this->order->id)
            ->where('payment_method', 'MercadoPago')
            ->first();
            
        if ($existingPayment) {
            // Actualizar el pago existente
            $existingPayment->update([
                'payment_code' => $preference->id,
                'amount' => $totalAmount,
                'currency' => $this->config['currency'],
                'status' => 'pending',
                'external_response' => json_encode($preference),
            ]);
        } else {
            // Crear nuevo pago
            Payment::create([
                'order_id' => $this->order->id,
                'payment_method' => 'MercadoPago',
                'payment_code' => $preference->id,
                'amount' => $totalAmount,
                'currency' => $this->config['currency'],
                'status' => 'pending',
                'external_id' => null, // Se actualizará con el webhook
                'external_response' => json_encode($preference),
            ]);
        }
    }

    private function mapMercadoPagoStatus(string $status): string
    {
        return match ($status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'charged_back' => 'refunded',
            default => 'pending',
        };
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['access_token']) && 
               !empty($this->config['public_key']) &&
               $this->config['access_token'] !== 'your_access_token_here' &&
               $this->config['public_key'] !== 'your_public_key_here';
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Procesar pago desde datos de URL de retorno
     */
    public function processPaymentFromReturnUrl(array $urlParams): bool
    {
        try {
            // Extraer datos relevantes de la URL de retorno
            $paymentId = $urlParams['payment_id'] ?? $urlParams['collection_id'] ?? null;
            $externalReference = $urlParams['external_reference'] ?? null;
            $status = $urlParams['status'] ?? $urlParams['collection_status'] ?? null;
            
            if (!$paymentId || !$externalReference || !$status) {
                Log::error('Invalid return URL parameters', $urlParams);
                return false;
            }

            Log::info('Processing payment from return URL', [
                'payment_id' => $paymentId,
                'external_reference' => $externalReference,
                'status' => $status,
                'all_params' => $urlParams
            ]);

            // Obtener datos del pago desde MercadoPago
            $paymentData = $this->getPayment($paymentId);
            
            if (!$paymentData) {
                Log::error('Could not fetch payment data from return URL', [
                    'payment_id' => $paymentId,
                    'external_reference' => $externalReference
                ]);
                return false;
            }

            // Buscar la orden
            $order = \App\Models\Order::find($externalReference);
            
            if (!$order) {
                Log::error('Order not found for external reference', [
                    'external_reference' => $externalReference,
                    'payment_id' => $paymentId
                ]);
                return false;
            }

            // Buscar el pago en nuestra base de datos
            $payment = \App\Models\Payment::where('order_id', $order->id)
                ->where('payment_method', 'MercadoPago')
                ->first();

            if (!$payment) {
                Log::error('Payment record not found', [
                    'order_id' => $order->id,
                    'payment_id' => $paymentId
                ]);
                return false;
            }

            // Actualizar el pago
            $payment->update([
                'status' => $this->mapMercadoPagoStatus($paymentData['status']),
                'external_id' => $paymentData['id'],
                'external_response' => json_encode($paymentData),
            ]);

            // Actualizar la orden si corresponde
            if ($paymentData['status'] === 'approved') {
                $order->update(['status' => 'pendiente']);
                // Descontar stock automáticamente
                app(\App\Services\StockMovementService::class)->processOrderStockReduction($order);
            } elseif (in_array($paymentData['status'], ['rejected', 'cancelled'])) {
                $order->update(['status' => 'pago_fallido']);
            }

            Log::info('Payment processed successfully from return URL', [
                'payment_id' => $paymentId,
                'order_id' => $order->id,
                'status' => $paymentData['status'],
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error processing payment from return URL', [
                'error' => $e->getMessage(),
                'url_params' => $urlParams,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Extraer payment ID desde diferentes formatos de webhook según documentación oficial
     */
    private function extractPaymentIdFromWebhook(array $data): ?string
    {
        // Formato oficial MercadoPago (2024): {"type": "payment", "action": "payment.updated", "data": {"id": "123456"}}
        if (isset($data['type']) && $data['type'] === 'payment' && isset($data['data']['id'])) {
            Log::info('Webhook oficial MercadoPago detectado', [
                'type' => $data['type'],
                'action' => $data['action'] ?? 'unknown',
                'payment_id' => $data['data']['id']
            ]);
            return (string) $data['data']['id'];
        }
        
        // Formato alternativo: {"id": "1338891807", "topic": "payment"} - Para compatibilidad
        if (isset($data['id']) && isset($data['topic']) && $data['topic'] === 'payment') {
            Log::info('Webhook formato alternativo detectado', [
                'topic' => $data['topic'],
                'payment_id' => $data['id']
            ]);
            return (string) $data['id'];
        }
        
        // Formato de URL de retorno pasado como webhook (para procesamiento manual)
        if (isset($data['payment_id'])) {
            Log::info('Datos de URL de retorno detectados', [
                'payment_id' => $data['payment_id']
            ]);
            return (string) $data['payment_id'];
        }
        
        // Formato de URL de retorno con collection_id
        if (isset($data['collection_id'])) {
            Log::info('Collection ID detectado', [
                'collection_id' => $data['collection_id']
            ]);
            return (string) $data['collection_id'];
        }
        
        Log::warning('No se pudo extraer payment ID del webhook', [
            'available_fields' => array_keys($data),
            'data' => $data
        ]);
        return null;
    }

    /**
     * Limpiar payment ID para manejar formatos no estándar
     */
    private function cleanPaymentId(string $paymentId): string
    {
        // Si el ID contiene guiones, extraer solo la parte numérica inicial
        if (strpos($paymentId, '-') !== false) {
            $parts = explode('-', $paymentId);
            $numericPart = $parts[0];
            
            // Verificar que la primera parte sea numérica
            if (is_numeric($numericPart)) {
                Log::info('Cleaned payment ID', [
                    'original' => $paymentId,
                    'cleaned' => $numericPart
                ]);
                return $numericPart;
            }
        }
        
        // Si no hay guiones o no es el formato esperado, devolver el ID original
        return $paymentId;
    }
}