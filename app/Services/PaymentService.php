<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Services\StockMovementService;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentService
{
    public function __construct()
    {
        // Configurar MercadoPago con solo el access token
        MercadoPagoConfig::setAccessToken(config('mercadopago.access_token'));
    }

    /**
     * Crear preferencia de pago para MercadoPago
     */
    public function createPaymentPreference(Order $order): array
    {
        try {
            $client = new PreferenceClient();
            
            // Preparar items de la orden
            $items = [];
            foreach ($order->orderDetails as $detail) {
                $items[] = [
                    'id' => (string) $detail->product_id,
                    'title' => $detail->product->name,
                    'quantity' => $detail->quantity,
                    'unit_price' => (float) $detail->unit_price,
                    'currency_id' => 'PEN'
                ];
            }

            // Configurar preferencia
            $preference_data = [
                'items' => $items,
                'payer' => [
                    'name' => $order->client->name,
                    'email' => $order->client->email,
                    'phone' => [
                        'number' => $order->client->phone ?? ''
                    ],
                    'identification' => [
                        'type' => $this->getDocumentType($order->client->document_type),
                        'number' => $order->client->identity_document
                    ]
                ],
                'back_urls' => [
                    'success' => env('APP_FRONTEND_URL', 'http://localhost:3000') . '/payment/success',
                    'failure' => env('APP_FRONTEND_URL', 'http://localhost:3000') . '/payment/failure',
                    'pending' => env('APP_FRONTEND_URL', 'http://localhost:3000') . '/payment/pending'
                ],
                'auto_return' => 'approved',
                'notification_url' => route('webhooks.mercadopago'),
                'external_reference' => (string) $order->id,
                'statement_descriptor' => 'Master Color',
                'payment_methods' => [
                    'excluded_payment_types' => [],
                    'installments' => 12
                ],
                'shipments' => [
                    'cost' => (float) $order->shipping_cost,
                    'mode' => 'not_specified'
                ]
            ];

            $preference = $client->create($preference_data);

            // Crear registro de pago
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'MercadoPago',
                'amount' => $order->total,
                'currency' => 'PEN',
                'status' => 'pending',
                'external_id' => $preference->id,
                'external_response' => json_decode(json_encode($preference), true)
            ]);

            return [
                'success' => true,
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point,
                'payment_id' => $payment->id
            ];

        } catch (Exception $e) {
            Log::error('Error creating MercadoPago preference: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Procesar notificación de webhook de MercadoPago
     */
    public function processWebhookNotification(array $data): bool
    {
        try {
            if (!isset($data['id']) || !isset($data['topic'])) {
                Log::warning('Invalid webhook data received', $data);
                return false;
            }

            if ($data['topic'] !== 'payment') {
                Log::info('Webhook topic not handled: ' . $data['topic']);
                return true;
            }

            $paymentClient = new PaymentClient();
            $mercadoPagoPayment = $paymentClient->get($data['id']);

            // Buscar el pago en nuestra base de datos
            $externalReference = $mercadoPagoPayment->external_reference;
            $order = Order::find($externalReference);

            if (!$order) {
                Log::error('Order not found for external reference: ' . $externalReference);
                return false;
            }

            $payment = Payment::where('order_id', $order->id)
                             ->where('payment_method', 'MercadoPago')
                             ->first();

            if (!$payment) {
                Log::error('Payment record not found for order: ' . $order->id);
                return false;
            }

            // Actualizar estado del pago
            $payment->update([
                'status' => $this->mapMercadoPagoStatus($mercadoPagoPayment->status),
                'external_id' => $mercadoPagoPayment->id,
                'payment_code' => $mercadoPagoPayment->id,
                'external_response' => json_decode(json_encode($mercadoPagoPayment), true)
            ]);

            // Actualizar estado de la orden según el estado del pago
            $this->updateOrderStatus($order, $mercadoPagoPayment->status);

            Log::info('Payment processed successfully', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'mp_payment_id' => $mercadoPagoPayment->id,
                'status' => $mercadoPagoPayment->status
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Error processing webhook notification: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Actualizar estado de la orden basado en el estado del pago
     */
    private function updateOrderStatus(Order $order, string $mercadoPagoStatus): void
    {
        switch ($mercadoPagoStatus) {
            case 'approved':
                $order->update(['status' => 'pendiente']);
                // Descontar stock automáticamente
                app(StockMovementService::class)->processOrderStockReduction($order);
                break;
            
            case 'rejected':
            case 'cancelled':
                $order->update(['status' => 'pago_fallido']);
                break;
            
            case 'pending':
            case 'in_process':
                // Mantener en pendiente_pago
                break;
        }
    }

    /**
     * Mapear estados de MercadoPago a nuestros estados
     */
    private function mapMercadoPagoStatus(string $mercadoPagoStatus): string
    {
        $statusMap = [
            'pending' => 'pending',
            'approved' => 'approved',
            'authorized' => 'approved',
            'in_process' => 'pending',
            'in_mediation' => 'pending',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'charged_back' => 'refunded'
        ];

        return $statusMap[$mercadoPagoStatus] ?? 'pending';
    }

    /**
     * Obtener tipo de documento para MercadoPago
     */
    private function getDocumentType(string $documentType): string
    {
        $typeMap = [
            'DNI' => 'DNI',
            'CE' => 'CE',
            'RUC' => 'RUC',
            'Pasaporte' => 'PAS'
        ];

        return $typeMap[$documentType] ?? 'DNI';
    }
}