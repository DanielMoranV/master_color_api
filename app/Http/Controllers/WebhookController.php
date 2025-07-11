<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Services\PaymentService;
use App\Facades\MercadoPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessMercadoPagoWebhook;

class WebhookController extends Controller
{
    /**
     * Handle MercadoPago webhook notifications
     */
    public function mercadoPago(Request $request)
    {
        try {
            Log::info([
                'driver' => 'single',
                'path' => storage_path('logs/mercadoPagoWebhook.log'),
                'request' => $request->all(),
            ]);
            // Log webhook received with comprehensive details
            Log::info('🔔 MercadoPago webhook recibido - ANÁLISIS COMPLETO', [
                'timestamp' => now()->toISOString(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'headers' => $request->headers->all(),
                'query_params' => $request->query(),
                'body_raw' => $request->getContent(),
                'body_parsed' => $request->all(),
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
            ]);

            // Análisis detallado del payload
            $webhookData = $request->all();

            // Si es un webhook de prueba de Mercado Pago (id 123456), responder OK sin procesar
            $testId = '123456';
            $incomingId = $webhookData['id'] ?? ($webhookData['data']['id'] ?? null);
            if ($incomingId === $testId) {
                Log::info('🧪 Webhook de prueba recibido (id 123456) - se responde 200 OK');
                return response()->json(['status' => 'success', 'message' => 'Test webhook acknowledged'], 200);
            }
            $this->analyzeWebhookPayload($webhookData);

            // Validaciones de seguridad
            if (!$this->validateWebhookSecurity($request)) {
                Log::warning('Webhook security validation failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'headers' => $request->headers->all()
                ]);
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            // Detectar formato del webhook según documentación oficial
            $webhookData = $request->all();
            $webhookId = null;

            // Formato oficial MercadoPago (2024): {"type": "payment", "action": "payment.updated", "data": {"id": "123456"}}
            if (isset($webhookData['type']) && isset($webhookData['data']['id'])) {
                $webhookId = $webhookData['data']['id'] . '_' . $webhookData['type'] . '_' . ($webhookData['action'] ?? 'unknown');
                
                // Validar que sea una notificación de pago
                if ($webhookData['type'] !== 'payment') {
                    Log::info('Webhook no es de tipo payment, ignorando', [
                        'type' => $webhookData['type'],
                        'action' => $webhookData['action'] ?? 'unknown',
                        'webhook_id' => $webhookId
                    ]);
                    return response()->json(['status' => 'success', 'message' => 'Non-payment webhook ignored'], 200);
                }
            }
            // Formato alternativo para compatibilidad: {"id": "xxx", "topic": "payment"}
            elseif (isset($webhookData['id']) && isset($webhookData['topic'])) {
                $webhookId = $webhookData['id'] . '_' . $webhookData['topic'];
                
                // Validar que sea una notificación de pago
                if ($webhookData['topic'] !== 'payment') {
                    Log::info('Webhook topic no es payment, ignorando', [
                        'topic' => $webhookData['topic'],
                        'webhook_id' => $webhookId
                    ]);
                    return response()->json(['status' => 'success', 'message' => 'Non-payment webhook ignored'], 200);
                }
            } else {
                Log::warning('⚠️ FORMATO DE WEBHOOK NO RECONOCIDO', [
                    'available_fields' => array_keys($webhookData),
                    'webhook_data' => $webhookData
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid webhook format'], 400);
            }

            // Verificar duplicados (idempotencia)
            if ($this->isDuplicateWebhook($webhookId)) {
                Log::info('Duplicate webhook ignored', ['webhook_id' => $webhookId]);
                return response()->json(['status' => 'success', 'message' => 'Already processed'], 200);
            }

            // --- Nueva estrategia: encolar el procesamiento y responder 200 rápidamente
            // Encolar Job para procesamiento asíncrono
            \App\Jobs\ProcessMercadoPagoWebhook::dispatchAfterResponse($request->all());
            
            // Marcar como procesado para evitar duplicados
            $this->markWebhookAsProcessed($webhookId);

            Log::info('Webhook encolado para procesamiento asíncrono', [
                'webhook_id' => $webhookId,
            ]);

            return response()->json(['status' => 'queued'], 200);

            // Procesar la notificación usando el wrapper
            $result = MercadoPago::processWebhookNotification($request->all());

            if ($result) {
                // Marcar webhook como procesado
                $this->markWebhookAsProcessed($webhookId);

                // Extraer información para logging según el formato
                $logInfo = ['webhook_id' => $webhookId];
                if (isset($webhookData['action']) && isset($webhookData['data']['id'])) {
                    $logInfo['payment_id'] = $webhookData['data']['id'];
                    $logInfo['action'] = $webhookData['action'];
                    $logInfo['type'] = $webhookData['type'];
                } else {
                    $logInfo['payment_id'] = $request->input('id');
                    $logInfo['topic'] = $request->input('topic');
                }

                Log::info('Webhook processed successfully', $logInfo);

                return response()->json(['status' => 'success'], 200);
            } else {
                // Extraer información para error logging según el formato
                $errorInfo = [];
                if (isset($webhookData['action']) && isset($webhookData['data']['id'])) {
                    $errorInfo['payment_id'] = $webhookData['data']['id'];
                    $errorInfo['action'] = $webhookData['action'];
                    $errorInfo['type'] = $webhookData['type'];
                } else {
                    $errorInfo['payment_id'] = $request->input('id');
                    $errorInfo['topic'] = $request->input('topic');
                }

                Log::error('Webhook processing failed', $errorInfo);

                return response()->json(['status' => 'error', 'message' => 'Processing failed'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Webhook exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json(['status' => 'error', 'message' => 'Internal error'], 500);
        }
    }

    /**
     * Validar seguridad del webhook
     */
    private function validateWebhookSecurity(Request $request): bool
    {
        // En entorno local, permitir todos los requests para facilitar pruebas
        if (app()->environment('local')) {
            return true;
        }

        // 0. Verificar firma x-signature
        if (!$this->verifySignature($request)) {
            Log::warning('x-signature verification failed');
            return false; // detiene el flujo de security validation
        }

        // 1. Verificar User-Agent de MercadoPago
        $userAgent = $request->userAgent();
        if (!str_contains($userAgent, 'MercadoPago') && !str_contains($userAgent, 'Mercado')) {
            // En desarrollo, permitir postman/curl para testing
            if (
                !app()->environment('local') ||
                (!str_contains($userAgent, 'Postman') && !str_contains($userAgent, 'curl'))
            ) {
                return false;
            }
        }

        // 2. Verificar rango de IPs de MercadoPago (opcional)
        $ip = $request->ip();
        $allowedIPs = [
            '209.225.49.0/24',
            '216.33.197.0/24',
            '216.33.196.0/24',
            '127.0.0.1', // localhost para desarrollo
            '::1' // localhost IPv6
        ];

        // En producción, verificar IPs
        if (!app()->environment('local')) {
            $isAllowedIP = false;
            foreach ($allowedIPs as $allowedIP) {
                if ($this->ipInRange($ip, $allowedIP)) {
                    $isAllowedIP = true;
                    break;
                }
            }
            if (!$isAllowedIP) {
                return false;
            }
        }

        // 3. Rate limiting básico
        $rateLimitKey = 'webhook_rate_limit_' . $ip;
        $attempts = \Illuminate\Support\Facades\Cache::get($rateLimitKey, 0);
        if ($attempts > 50) { // Máximo 50 webhooks por minuto por IP
            return false;
        }
        \Illuminate\Support\Facades\Cache::put($rateLimitKey, $attempts + 1, now()->addMinute());

        return true;
    }

    /**
     * Verificar si una IP está en un rango CIDR
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $mask) = explode('/', $range);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6 - implementación básica
            return $ip === $subnet;
        }

        // IPv4
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $mask);

        return ($ip & $mask) === ($subnet & $mask);
    }

    /**
     * Verificar si el webhook ya fue procesado (idempotencia)
     */
    private function isDuplicateWebhook(string $webhookId): bool
    {
        return \Illuminate\Support\Facades\Cache::has('processed_webhook_' . $webhookId);
    }

    /**
     * Marcar webhook como procesado
     */
    private function markWebhookAsProcessed(string $webhookId): void
    {
        // Guardar por 24 horas para evitar duplicados
        \Illuminate\Support\Facades\Cache::put('processed_webhook_' . $webhookId, true, now()->addHours(24));
    }

    /**
     * Validar firma HMAC enviada en header x-signature
     * Documentación oficial:
     *   ts=TIMESTAMP,v1=HASH_SHA256_HEX
     * Template: id:[data.id_url];request-id:[x-request-id_header];ts:[ts_header];
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('mercadopago.webhook_secret');
        if (!$secret) {
            // Si no se configuró, omitir validación (desarrollo)
            return app()->environment('local');
        }

        $header = $request->header('x-signature');
        if (!$header || !str_contains($header, ',')) {
            return false;
        }

        [$tsPart, $v1Part] = explode(',', $header, 2);
        $ts = (string)substr($tsPart, 3); // quitar 'ts='
        $v1 = (string)substr($v1Part, 3); // quitar 'v1='

        $requestId = $request->header('x-request-id');
        $dataId = strtolower($request->input('data.id') ?? $request->query('data.id') ?? $request->input('id'));

        $template = "id:{$dataId};request-id:{$requestId};ts:{$ts};";

        $expected = hash_hmac('sha256', $template, $secret);

        return hash_equals($expected, $v1);
    }

    /**
     * Analizar payload del webhook para entender la estructura
     */
    private function analyzeWebhookPayload(array $data): void
    {
        $analysis = [
            'payload_structure' => [],
            'detected_format' => 'unknown',
            'payment_id_candidates' => [],
            'topic_candidates' => [],
            'action_candidates' => [],
        ];

        // Analizar estructura de primer nivel
        foreach ($data as $key => $value) {
            $analysis['payload_structure'][$key] = [
                'type' => gettype($value),
                'value' => is_array($value) ? '[array]' : (is_string($value) ? $value : json_encode($value)),
                'is_array' => is_array($value),
                'array_keys' => is_array($value) ? array_keys($value) : null,
            ];
        }

        // Detectar formato del webhook según documentación oficial
        if (isset($data['type']) && isset($data['data']['id'])) {
            $analysis['detected_format'] = 'official_format_2024';
            $analysis['action_candidates'][] = $data['action'] ?? 'no_action';
            $analysis['topic_candidates'][] = $data['type'];
            
            $analysis['payment_id_candidates'][] = [
                'source' => 'data.id',
                'value' => $data['data']['id'],
                'type' => gettype($data['data']['id']),
                'is_official' => true
            ];
            
            // Verificar campos adicionales del formato oficial
            $officialFields = ['api_version', 'live_mode', 'date_created', 'user_id', 'id'];
            foreach ($officialFields as $field) {
                if (isset($data[$field])) {
                    $analysis['official_fields'][$field] = $data[$field];
                }
            }
        }

        if (isset($data['id']) && isset($data['topic'])) {
            $analysis['detected_format'] = 'legacy_format';
            $analysis['topic_candidates'][] = $data['topic'];
            $analysis['payment_id_candidates'][] = [
                'source' => 'id',
                'value' => $data['id'],
                'type' => gettype($data['id']),
                'is_official' => false
            ];
        }

        // Buscar otros campos que puedan contener IDs
        $possibleIdFields = ['payment_id', 'collection_id', 'external_reference', 'preference_id'];
        foreach ($possibleIdFields as $field) {
            if (isset($data[$field])) {
                $analysis['payment_id_candidates'][] = [
                    'source' => $field,
                    'value' => $data[$field],
                    'type' => gettype($data[$field])
                ];
            }
        }

        // Log análisis detallado
        Log::info('🔍 ANÁLISIS DETALLADO DEL WEBHOOK', $analysis);

        // Sugerencias basadas en el análisis
        $suggestions = [];
        if ($analysis['detected_format'] === 'unknown') {
            $suggestions[] = 'Formato de webhook no reconocido - revisar documentación de MercadoPago';
        }
        if (empty($analysis['payment_id_candidates'])) {
            $suggestions[] = 'No se encontraron candidatos para payment_id';
        }
        if (count($analysis['payment_id_candidates']) > 1) {
            $suggestions[] = 'Múltiples candidatos para payment_id - determinar cuál usar';
        }

        if (!empty($suggestions)) {
            Log::warning('⚠️ SUGERENCIAS PARA WEBHOOK', [
                'suggestions' => $suggestions,
                'payload' => $data
            ]);
        }
    }

    /**
     * Get payment status for a specific order (for frontend polling)
     * Also checks MercadoPago API directly if payment is still pending
     */
    /**
     * Fallback endpoint para procesar payment_id enviado desde el frontend
     * cuando el webhook de MercadoPago no llega (por firewalls o entornos locales).
     * Espera order_id y payment_id en el body (JSON o x-www-form-urlencoded).
     * No requiere autenticación para simplificar el flujo después del redirect.
     */
    public function paymentReturn(Request $request)
    {
        // Log detallado de la petición completa
        Log::info('🔄 PAYMENT RETURN - Petición recibida desde frontend', [
            'timestamp' => now()->toISOString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'query_params' => $request->query(),
            'body_raw' => $request->getContent(),
            'body_parsed' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'user_agent' => $request->header('User-Agent'),
            'ip' => $request->ip(),
        ]);

        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'payment_id' => 'required|string',
        ]);

        Log::info('🔄 PAYMENT RETURN - Datos validados', [
            'order_id' => $validated['order_id'],
            'payment_id' => $validated['payment_id'],
            'payment_id_length' => strlen($validated['payment_id']),
            'payment_id_is_numeric' => is_numeric($validated['payment_id']),
            'payment_id_contains_dashes' => str_contains($validated['payment_id'], '-'),
        ]);

        try {
            // Disparar procesamiento manual reutilizando lógica del service
            $paymentService = app(PaymentService::class);
            
            Log::info('🔄 PAYMENT RETURN - Iniciando procesamiento con PaymentService', [
                'service_class' => get_class($paymentService),
                'simulated_webhook_payload' => [
                    'id' => $validated['payment_id'],
                    'topic' => 'payment',
                ]
            ]);
            
            // Simular payload de webhook de formato antiguo
            $processed = $paymentService->processWebhookNotification([
                'id' => $validated['payment_id'],
                'topic' => 'payment',
            ]);

            Log::info('🔄 PAYMENT RETURN - Resultado del procesamiento', [
                'processed' => $processed,
                'order_id' => $validated['order_id'],
                'payment_id' => $validated['payment_id'],
            ]);

            return ApiResponseClass::sendResponse([
                'processed' => $processed,
            ], $processed ? 'Pago procesado correctamente' : 'No se pudo procesar el pago', 200);
        } catch (\Exception $e) {
            Log::error('🔄 PAYMENT RETURN - Error en procesamiento', [
                'order_id' => $validated['order_id'],
                'payment_id' => $validated['payment_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponseClass::errorResponse('Error interno del servidor', 500, [$e->getMessage()]);
        }
    }

    public function getPaymentStatus($orderId)
    {
        try {
            $order = \App\Models\Order::with('payments')->find($orderId);

            if (!$order) {
                return ApiResponseClass::errorResponse('Orden no encontrada', 404);
            }

            $payment = $order->payments()->where('payment_method', 'MercadoPago')->first();

            if (!$payment) {
                return ApiResponseClass::sendResponse([
                    'order_status' => $order->status,
                    'payment_status' => null,
                    'has_payment' => false,
                    'polling' => [
                        'should_poll' => false,
                        'message' => 'No hay registro de pago'
                    ]
                ], 'Estado de la orden', 200);
            }

            // Usar servicio de polling inteligente
            $pollingService = app(\App\Services\PaymentPollingService::class);
            $pollingResult = $pollingService->checkPaymentWithBackoff($order);
            $pollingRecommendations = $pollingService->getPollingRecommendations($order);

            // Preparar respuesta con información de polling
            $response = [
                'order_status' => $order->status,
                'payment_status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'payment_code' => $payment->payment_code,
                'external_id' => $payment->external_id,
                'has_payment' => true,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
                'polling' => $pollingRecommendations,
                'last_check_result' => [
                    'success' => $pollingResult['success'],
                    'message' => $pollingResult['message'],
                    'should_continue_polling' => $pollingResult['should_continue_polling'] ?? true
                ]
            ];

            // Añadir información de próxima verificación si aplica
            if (isset($pollingResult['next_check_in'])) {
                $response['polling']['next_check_in_seconds'] = $pollingResult['next_check_in'];
            }

            return ApiResponseClass::sendResponse($response, 'Estado del pago', 200);
        } catch (\Exception $e) {
            Log::error('Error in getPaymentStatus', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponseClass::errorResponse('Error interno del servidor', 500, [$e->getMessage()]);
        }
    }
}
