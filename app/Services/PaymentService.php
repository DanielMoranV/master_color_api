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
            // Usar llamada directa con CURL en lugar del SDK problemático
            return $this->createPaymentPreferenceWithCurl($order);
        } catch (Exception $e) {
            // Obtener más detalles del error si es una excepción de MercadoPago
            $errorDetails = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

            // Hacer una llamada de prueba simple para obtener más información del error
            try {
                $curlInfo = $this->makeTestCall();
                $errorDetails['curl_test'] = $curlInfo;
            } catch (\Exception $curlException) {
                $errorDetails['curl_test_error'] = $curlException->getMessage();
            }

            // Si es una excepción HTTP, capturar detalles
            if (method_exists($e, 'getResponse')) {
                $response = $e->getResponse();
                if ($response) {
                    $errorDetails['http_status'] = $response->getStatusCode();
                    $errorDetails['http_body'] = $response->getBody()->getContents();
                }
            }

            Log::error('Error creating MercadoPago preference: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'error_details' => $errorDetails,
                'preference_data' => $preference_data ?? null,
                'access_token' => substr(config('mercadopago.access_token'), 0, 15) . '...',
                'environment' => app()->environment()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'details' => $errorDetails['api_response_body'] ?? $errorDetails['http_body'] ?? null
            ];
        }
    }

    /**
     * Procesar notificación de webhook de MercadoPago
     */
    public function processWebhookNotification(array $data): bool
    {
        try {
            // Detectar formato del webhook y extraer datos necesarios
            $paymentId = null;
            $topic = null;

            // Formato nuevo: {"action": "payment.created", "data": {"id": "1338744613"}, "type": "payment"}
            if (isset($data['action']) && isset($data['type']) && isset($data['data']['id'])) {
                $paymentId = $data['data']['id'];
                $topic = $data['type'];
                Log::info('Processing new format webhook', [
                    'action' => $data['action'],
                    'type' => $data['type'],
                    'payment_id' => $paymentId
                ]);
            }
            // Formato antiguo: {"id": "1338744613", "topic": "payment"}
            elseif (isset($data['id']) && isset($data['topic'])) {
                $paymentId = $data['id'];
                $topic = $data['topic'];
                Log::info('Processing old format webhook', [
                    'payment_id' => $paymentId,
                    'topic' => $topic
                ]);
            }
            else {
                Log::warning('Invalid webhook data received - unknown format', $data);
                return false;
            }

            if ($topic !== 'payment') {
                Log::info('Webhook topic not handled: ' . $topic);
                return true;
            }

            // Usar CURL directo en lugar del SDK para obtener información del pago
            $mercadoPagoPayment = $this->getPaymentWithCurl($paymentId);
            
            if (!$mercadoPagoPayment) {
                Log::error('Failed to retrieve payment from MercadoPago API', ['payment_id' => $paymentId]);
                return false;
            }

            // Buscar el pago en nuestra base de datos
            $externalReference = $mercadoPagoPayment['external_reference'];
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
                'status' => $this->mapMercadoPagoStatus($mercadoPagoPayment['status']),
                'external_id' => $mercadoPagoPayment['id'],
                'payment_code' => $mercadoPagoPayment['id'],
                'external_response' => $mercadoPagoPayment
            ]);

            // Actualizar estado de la orden según el estado del pago
            $this->updateOrderStatus($order, $mercadoPagoPayment['status']);

            Log::info('Payment processed successfully via webhook', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'mp_payment_id' => $mercadoPagoPayment['id'],
                'status' => $mercadoPagoPayment['status'],
                'webhook_format' => isset($data['action']) ? 'new' : 'old'
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
                // Solo descontar stock la primera vez que se marca como aprobada
                if ($order->status !== 'pendiente') {
                    $order->update(['status' => 'pendiente']);
                    app(StockMovementService::class)->processOrderStockReduction($order);
                }
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
     * Verificar estado de pago directamente con MercadoPago
     * 
     * @param string|null $preferenceId ID de la preferencia o external_id del pago
     * @param Order $order Orden asociada al pago
     * @return bool true si se actualizó el estado, false si no
     */
    public function checkPaymentStatus(?string $preferenceId, Order $order): bool
    {
        try {
            // Validar que tengamos un preferenceId válido
            if (empty($preferenceId)) {
                Log::warning('checkPaymentStatus called with empty preferenceId', [
                    'order_id' => $order->id,
                    'preference_id' => $preferenceId
                ]);
                return false;
            }
            
            // Verificar si la orden ya fue cancelada o expiró
            if ($this->isOrderExpiredOrCancelled($order)) {
                Log::info('Order expired or cancelled, skipping payment check', [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'created_at' => $order->created_at
                ]);
                return false;
            }

            // Intentar obtener pago directamente por preference_id
            $searchUrl = "https://api.mercadopago.com/v1/payments/search?external_reference=" . $order->id;

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $searchUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15, // Timeout de 15 segundos
                CURLOPT_CONNECTTIMEOUT => 10, // Timeout de conexión de 10 segundos
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . config('mercadopago.access_token'),
                    'Content-Type: application/json'
                ],
                CURLOPT_USERAGENT => 'MasterColorAPI/1.0'
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            // Manejo de errores de conexión
            if ($curlError) {
                Log::error('CURL error checking payment status', [
                    'order_id' => $order->id,
                    'curl_error' => $curlError,
                    'preference_id' => $preferenceId
                ]);
                return false;
            }

            // Manejo de respuestas HTTP no exitosas
            if ($httpCode !== 200) {
                Log::error('MercadoPago API error', [
                    'http_code' => $httpCode,
                    'response' => substr($response, 0, 500), // Limitar tamaño del log
                    'order_id' => $order->id
                ]);

                // Si es 404, marcar como no encontrado pero no como error
                if ($httpCode === 404) {
                    Log::info('Payment not found yet in MercadoPago', ['order_id' => $order->id]);
                    return false;
                }

                return false;
            }

            $searchResult = json_decode($response, true);

            // Validar estructura de respuesta
            if (!is_array($searchResult) || !isset($searchResult['results'])) {
                Log::error('Invalid MercadoPago API response structure', [
                    'order_id' => $order->id,
                    'response' => substr($response, 0, 200)
                ]);
                return false;
            }

            if (empty($searchResult['results'])) {
                Log::info('No payments found for order', ['order_id' => $order->id]);
                return false;
            }

            // Obtener el pago más reciente y validar estructura
            $latestPayment = $searchResult['results'][0];

            if (!isset($latestPayment['id']) || !isset($latestPayment['status'])) {
                Log::error('Invalid payment structure from MercadoPago', [
                    'order_id' => $order->id,
                    'payment_data' => $latestPayment
                ]);
                return false;
            }

            Log::info('Payment status check from MercadoPago', [
                'order_id' => $order->id,
                'payment_id' => $latestPayment['id'],
                'status' => $latestPayment['status'],
                'status_detail' => $latestPayment['status_detail'] ?? null,
                'date_approved' => $latestPayment['date_approved'] ?? null
            ]);

            // Actualizar pago en nuestra base de datos
            $payment = Payment::where('order_id', $order->id)
                ->where('payment_method', 'MercadoPago')
                ->first();

            if ($payment) {
                // Verificar si el estado realmente cambió
                $newStatus = $this->mapMercadoPagoStatus($latestPayment['status']);
                $statusChanged = $payment->status !== $newStatus;

                $payment->update([
                    'status' => $newStatus,
                    'external_id' => $latestPayment['id'],
                    'payment_code' => $latestPayment['id'],
                    'external_response' => $latestPayment
                ]);

                // Solo actualizar estado de orden si el estado del pago cambió
                if ($statusChanged) {
                    $this->updateOrderStatus($order, $latestPayment['status']);

                    Log::info('Payment status updated', [
                        'order_id' => $order->id,
                        'old_status' => $payment->status,
                        'new_status' => $newStatus,
                        'mp_status' => $latestPayment['status']
                    ]);
                }

                return true;
            } else {
                Log::error('Payment record not found in database', [
                    'order_id' => $order->id,
                    'mp_payment_id' => $latestPayment['id']
                ]);
            }

            return false;
        } catch (Exception $e) {
            Log::error('Error checking payment status: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'preference_id' => $preferenceId,
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Verificar si la orden expiró o fue cancelada
     */
    private function isOrderExpiredOrCancelled(Order $order): bool
    {
        // Si está cancelada
        if (in_array($order->status, ['cancelado', 'pago_fallido'])) {
            return true;
        }

        // Si tiene más de 24 horas y sigue pendiente de pago
        if (
            $order->status === 'pendiente_pago' &&
            $order->created_at->diffInHours(now()) > 24
        ) {

            // Auto-cancelar órdenes expiradas
            $order->update(['status' => 'cancelado']);

            Log::info('Order auto-cancelled due to expiration', [
                'order_id' => $order->id,
                'created_at' => $order->created_at,
                'hours_elapsed' => $order->created_at->diffInHours(now())
            ]);

            return true;
        }

        return false;
    }

    /**
     * Crear preferencia de prueba simple para verificar configuración
     */
    public function testMercadoPagoConnection(): array
    {
        try {
            $client = new PreferenceClient();

            // Preferencia de prueba mínima
            $testPreference = [
                'items' => [
                    [
                        'id' => 'test-item',
                        'title' => 'Producto de Prueba',
                        'quantity' => 1,
                        'unit_price' => 10.00,
                        'currency_id' => 'PEN'
                    ]
                ],
                'payer' => [
                    'email' => 'test@test.com'
                ],
                'external_reference' => 'test-' . time()
            ];

            Log::info('Testing MercadoPago connection', $testPreference);

            $preference = $client->create($testPreference);

            Log::info('MercadoPago test successful', [
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point
            ]);

            return [
                'success' => true,
                'preference_id' => $preference->id,
                'message' => 'Conexión exitosa con MercadoPago'
            ];
        } catch (Exception $e) {
            Log::error('MercadoPago test failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error de conexión con MercadoPago'
            ];
        }
    }

    /**
     * Hacer una llamada de prueba para obtener más información del error
     */
    private function makeTestCall(): array
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.mercadopago.com/checkout/preferences',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . config('mercadopago.access_token'),
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'items' => [
                    [
                        'id' => 'test-item',
                        'title' => 'Test Product',
                        'quantity' => 1,
                        'unit_price' => 10.0,
                        'currency_id' => 'PEN'
                    ]
                ],
                'payer' => [
                    'email' => 'test@test.com'
                ],
                'external_reference' => 'test-' . time()
            ])
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        return [
            'http_code' => $httpCode,
            'response' => $response,
            'curl_error' => $curlError,
            'response_decoded' => json_decode($response, true)
        ];
    }

    /**
     * Crear preferencia de pago usando CURL directo (bypass del SDK problemático)
     */
    private function createPaymentPreferenceWithCurl(Order $order): array
    {
        // Validar datos del cliente
        if (!$order->client->email || !filter_var($order->client->email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email del cliente inválido o faltante');
        }

        if (!$order->client->name || strlen($order->client->name) < 2) {
            throw new Exception('Nombre del cliente inválido o faltante');
        }

        // Preparar items de la orden
        $items = [];
        foreach ($order->orderDetails as $detail) {
            $title = trim($detail->product->name);
            if (strlen($title) > 256) {
                $title = substr($title, 0, 253) . '...';
            }

            $unitPrice = round((float) $detail->unit_price, 2);

            $items[] = [
                'id' => (string) $detail->product_id,
                'title' => $title,
                'description' => substr($title, 0, 100),
                'quantity' => (int) $detail->quantity,
                'unit_price' => $unitPrice,
                'currency_id' => 'PEN'
            ];
        }

        // Configurar URLs
        $backendUrl = env('APP_URL', 'http://localhost:8000');
        $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:5173');

        // Preparar datos del pagador
        $payerName = trim($order->client->name);
        $payerEmail = trim(strtolower($order->client->email));

        // Estructura de la preferencia
        $preference_data = [
            'items' => $items,
            'payer' => [
                'name' => $payerName,
                'surname' => '',
                'email' => $payerEmail
            ],
            'external_reference' => (string) $order->id,
            'expires' => false,
            'back_urls' => [
                'success' => config('mercadopago.success_url'),
                'failure' => config('mercadopago.failure_url'),
                'pending' => config('mercadopago.pending_url')
            ]
        ];

        // Agregar siempre notification_url para recibir webhooks incluso en desarrollo
        $preference_data['notification_url'] = $backendUrl . '/api/webhooks/mercadopago';
        $preference_data['statement_descriptor'] = 'MasterColor';

        Log::info('Creating MercadoPago preference with CURL', [
            'order_id' => $order->id,
            'preference_data' => $preference_data
        ]);

        // Realizar llamada CURL directa
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.mercadopago.com/checkout/preferences',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . config('mercadopago.access_token'),
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($preference_data)
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        // Verificar errores de CURL
        if ($curlError) {
            throw new Exception('CURL Error: ' . $curlError);
        }

        // Verificar código de respuesta HTTP
        if ($httpCode !== 201) {
            Log::error('MercadoPago API Error', [
                'http_code' => $httpCode,
                'response' => $response,
                'order_id' => $order->id
            ]);
            throw new Exception('MercadoPago API Error - HTTP ' . $httpCode . ': ' . $response);
        }

        $preference = json_decode($response, true);

        // Verificar que la respuesta sea válida
        if (!$preference || !isset($preference['id'])) {
            throw new Exception('Invalid response from MercadoPago API');
        }

        // Crear registro de pago
        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'MercadoPago',
            'amount' => $order->total,
            'currency' => 'PEN',
            'status' => 'pending',
            'external_id' => $preference['id'],
            'external_response' => $preference
        ]);

        Log::info('MercadoPago preference created successfully with CURL', [
            'order_id' => $order->id,
            'preference_id' => $preference['id'],
            'payment_id' => $payment->id
        ]);

        return [
            'success' => true,
            'preference_id' => $preference['id'],
            'init_point' => $preference['init_point'],
            'payment_id' => $payment->id
        ];
    }

    /**
     * Obtener información de pago usando CURL directo (bypass del SDK)
     */
    private function getPaymentWithCurl(string $paymentId): ?array
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . $paymentId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . config('mercadopago.access_token'),
                    'Content-Type: application/json'
                ]
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                Log::error('CURL error getting payment info', [
                    'payment_id' => $paymentId,
                    'curl_error' => $curlError
                ]);
                return null;
            }

            if ($httpCode !== 200) {
                Log::error('MercadoPago API error getting payment', [
                    'payment_id' => $paymentId,
                    'http_code' => $httpCode,
                    'response' => substr($response, 0, 500)
                ]);
                return null;
            }

            $payment = json_decode($response, true);

            if (!$payment || !isset($payment['id'])) {
                Log::error('Invalid payment response from MercadoPago', [
                    'payment_id' => $paymentId,
                    'response' => substr($response, 0, 200)
                ]);
                return null;
            }

            Log::info('Payment retrieved successfully with CURL', [
                'payment_id' => $paymentId,
                'status' => $payment['status'] ?? 'unknown',
                'external_reference' => $payment['external_reference'] ?? 'none'
            ]);

            return $payment;

        } catch (Exception $e) {
            Log::error('Exception getting payment with CURL: ' . $e->getMessage(), [
                'payment_id' => $paymentId,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
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
