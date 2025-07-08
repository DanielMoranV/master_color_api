<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentPollingService
{
    private PaymentService $paymentService;
    
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Verificar estado de pago con backoff exponencial inteligente
     */
    public function checkPaymentWithBackoff(Order $order): array
    {
        $payment = $order->payments()->where('payment_method', 'MercadoPago')->first();
        
        if (!$payment) {
            return [
                'success' => false,
                'message' => 'No se encontró registro de pago',
                'should_continue_polling' => false
            ];
        }

        // Si ya está procesado, no hacer más polling
        if (in_array($payment->status, ['approved', 'rejected', 'cancelled', 'refunded'])) {
            return [
                'success' => true,
                'payment_status' => $payment->status,
                'order_status' => $order->status,
                'should_continue_polling' => false,
                'message' => 'Pago ya procesado'
            ];
        }

        // Verificar si debemos hacer polling según backoff
        if (!$this->shouldCheckNow($order->id)) {
            return [
                'success' => true,
                'payment_status' => $payment->status,
                'order_status' => $order->status,
                'should_continue_polling' => true,
                'next_check_in' => $this->getNextCheckTime($order->id),
                'message' => 'Esperando próxima verificación'
            ];
        }

        // Intentar verificar con MercadoPago
        try {
            $updated = $this->paymentService->checkPaymentStatus($payment->external_id, $order);
            
            if ($updated) {
                $order->refresh();
                $payment->refresh();
                
                // Reset backoff si se actualizó exitosamente
                $this->resetBackoff($order->id);
                
                return [
                    'success' => true,
                    'payment_status' => $payment->status,
                    'order_status' => $order->status,
                    'should_continue_polling' => !in_array($payment->status, ['approved', 'rejected', 'cancelled']),
                    'message' => 'Estado actualizado desde MercadoPago'
                ];
            } else {
                // Incrementar backoff si no se pudo verificar
                $this->incrementBackoff($order->id);
                
                return [
                    'success' => true,
                    'payment_status' => $payment->status,
                    'order_status' => $order->status,
                    'should_continue_polling' => true,
                    'next_check_in' => $this->getNextCheckTime($order->id),
                    'message' => 'No se pudo verificar con MercadoPago, reintentando'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error en polling de pago', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            
            $this->incrementBackoff($order->id);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'should_continue_polling' => true,
                'next_check_in' => $this->getNextCheckTime($order->id),
                'message' => 'Error al verificar pago'
            ];
        }
    }

    /**
     * Determinar si debemos verificar ahora según backoff exponencial
     */
    private function shouldCheckNow(int $orderId): bool
    {
        $lastCheck = Cache::get("payment_last_check_{$orderId}");
        $attempts = Cache::get("payment_check_attempts_{$orderId}", 0);
        
        if (!$lastCheck) {
            return true;
        }

        $backoffSeconds = $this->calculateBackoffSeconds($attempts);
        $nextCheckTime = $lastCheck + $backoffSeconds;
        
        return time() >= $nextCheckTime;
    }

    /**
     * Calcular segundos de backoff exponencial con límite máximo
     */
    private function calculateBackoffSeconds(int $attempts): int
    {
        // Backoff exponencial: 5, 10, 20, 40, 60, 120, 300 (máximo 5 minutos)
        $baseSeconds = 5;
        $backoff = $baseSeconds * pow(2, $attempts);
        
        // Límite máximo de 5 minutos
        return min($backoff, 300);
    }

    /**
     * Incrementar contador de intentos y actualizar timestamp
     */
    private function incrementBackoff(int $orderId): void
    {
        $attempts = Cache::get("payment_check_attempts_{$orderId}", 0) + 1;
        
        // Límite máximo de intentos (después de 24 horas de intentos)
        $maxAttempts = 20;
        if ($attempts > $maxAttempts) {
            $attempts = $maxAttempts;
        }
        
        Cache::put("payment_check_attempts_{$orderId}", $attempts, now()->addHours(24));
        Cache::put("payment_last_check_{$orderId}", time(), now()->addHours(24));
    }

    /**
     * Resetear backoff cuando se actualiza exitosamente
     */
    private function resetBackoff(int $orderId): void
    {
        Cache::forget("payment_check_attempts_{$orderId}");
        Cache::forget("payment_last_check_{$orderId}");
    }

    /**
     * Obtener tiempo hasta próxima verificación
     */
    private function getNextCheckTime(int $orderId): int
    {
        $lastCheck = Cache::get("payment_last_check_{$orderId}", time());
        $attempts = Cache::get("payment_check_attempts_{$orderId}", 0);
        
        $backoffSeconds = $this->calculateBackoffSeconds($attempts);
        $nextCheckTime = $lastCheck + $backoffSeconds;
        
        return max(0, $nextCheckTime - time());
    }

    /**
     * Obtener recomendaciones de polling para el frontend
     */
    public function getPollingRecommendations(Order $order): array
    {
        $payment = $order->payments()->where('payment_method', 'MercadoPago')->first();
        
        if (!$payment || in_array($payment->status, ['approved', 'rejected', 'cancelled'])) {
            return [
                'should_poll' => false,
                'message' => 'Pago finalizado, no necesita polling'
            ];
        }

        $attempts = Cache::get("payment_check_attempts_{$order->id}", 0);
        $nextCheckIn = $this->getNextCheckTime($order->id);
        
        return [
            'should_poll' => true,
            'current_status' => $payment->status,
            'next_check_in_seconds' => $nextCheckIn,
            'recommended_interval' => max(30, $nextCheckIn), // Mínimo 30 segundos
            'max_attempts_reached' => $attempts >= 20,
            'message' => $attempts >= 20 ? 
                'Demasiados intentos, considere contactar soporte' : 
                'Verificando estado de pago'
        ];
    }
}