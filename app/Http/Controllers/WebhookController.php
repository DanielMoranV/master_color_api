<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Services\PaymentService;
use App\Facades\MercadoPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle MercadoPago webhook notifications
     */
    public function mercadoPago(Request $request)
    {
        try {
            // Log webhook received
            Log::info('MercadoPago webhook recibido', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Validaciones de seguridad
            if (!$this->validateWebhookSecurity($request)) {
                Log::warning('Webhook security validation failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'headers' => $request->headers->all()
                ]);
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            // Detectar formato del webhook y validar
            $webhookData = $request->all();
            $webhookId = null;

            // Formato nuevo: {"action": "payment.created", "data": {"id": "xxx"}, "type": "payment"}
            if (isset($webhookData['action']) && isset($webhookData['type']) && isset($webhookData['data']['id'])) {
                $webhookId = $webhookData['data']['id'] . '_' . $webhookData['type'] . '_' . $webhookData['action'];
            }
            // Formato antiguo: {"id": "xxx", "topic": "payment"}
            elseif (isset($webhookData['id']) && isset($webhookData['topic'])) {
                $webhookId = $webhookData['id'] . '_' . $webhookData['topic'];
            } else {
                Log::warning('Invalid webhook payload - unknown format', $webhookData);
                return response()->json(['status' => 'error', 'message' => 'Invalid payload format'], 400);
            }

            // Verificar duplicados (idempotencia)
            if ($this->isDuplicateWebhook($webhookId)) {
                Log::info('Duplicate webhook ignored', ['webhook_id' => $webhookId]);
                return response()->json(['status' => 'success', 'message' => 'Already processed'], 200);
            }

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
     * Get payment status for a specific order (for frontend polling)
     * Also checks MercadoPago API directly if payment is still pending
     */
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
