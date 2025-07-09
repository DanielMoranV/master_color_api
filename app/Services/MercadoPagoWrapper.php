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

            // Agregar URLs de retorno
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
            $client = new PaymentClient();
            $payment = $client->get($paymentId);
            
            return [
                'id' => $payment->id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'transaction_amount' => $payment->transaction_amount,
                'currency_id' => $payment->currency_id,
                'external_reference' => $payment->external_reference,
                'payment_method' => $payment->payment_method_id,
                'installments' => $payment->installments,
                'date_created' => $payment->date_created,
                'date_last_updated' => $payment->date_last_updated,
            ];

        } catch (MPApiException $e) {
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
            $paymentId = $data['data']['id'] ?? null;
            
            if (!$paymentId) {
                Log::warning('Webhook notification without payment ID', $data);
                return false;
            }

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

            $payment = Payment::where('external_id', $paymentId)->first();
            
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
                $payment->order->update(['status' => 'paid']);
            } elseif (in_array($paymentData['status'], ['rejected', 'cancelled'])) {
                $payment->order->update(['status' => 'cancelled']);
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
}