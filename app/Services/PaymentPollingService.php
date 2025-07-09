<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Facades\MercadoPago;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentPollingService
{
    public function __construct()
    {
        // Ya no necesitamos PaymentService, usamos MercadoPagoWrapper
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
            // Validar que tengamos external_id o payment_code antes de hacer la verificación
            $paymentId = $payment->external_id ?? $payment->payment_code;
            
            // Limpiar el payment ID para manejar formatos no estándar
            if (!empty($paymentId)) {
                $originalPaymentId = $paymentId;
                $paymentId = $this->cleanPaymentId($paymentId);
                
                // Verificar si el ID limpiado parece ser un preference_id (números largos que empiezan con 204)
                if ($this->looksLikePreferenceId($paymentId)) {
                    Log::warning('Payment ID parece ser preference_id, no payment_id real', [
                        'order_id' => $order->id,
                        'payment_id' => $paymentId,
                        'original_id' => $originalPaymentId,
                        'message' => 'Se necesita el payment_id real desde la URL de retorno'
                    ]);
                    
                    return [
                        'success' => false,
                        'payment_status' => $payment->status,
                        'order_status' => $order->status,
                        'should_continue_polling' => false,
                        'message' => 'Se necesita payment_id real desde URL de retorno'
                    ];
                }
            }
            
            if (empty($paymentId)) {
                Log::warning('Payment external_id and payment_code are null or empty, skipping MercadoPago check', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id
                ]);
                
                $this->incrementBackoff($order->id);
                
                return [
                    'success' => true,
                    'payment_status' => $payment->status,
                    'order_status' => $order->status,
                    'should_continue_polling' => true,
                    'next_check_in' => $this->getNextCheckTime($order->id),
                    'message' => 'No hay ID de pago para verificar con MercadoPago'
                ];
            }
            
            // Usar MercadoPagoWrapper para obtener información del pago
            $paymentData = MercadoPago::getPayment($paymentId);
            
            if (!$paymentData) {
                Log::warning('Could not fetch payment data from MercadoPago', [
                    'order_id' => $order->id,
                    'payment_id' => $paymentId
                ]);
                
                $this->incrementBackoff($order->id);
                
                return [
                    'success' => false,
                    'payment_status' => $payment->status,
                    'order_status' => $order->status,
                    'should_continue_polling' => true,
                    'next_check_in' => $this->getNextCheckTime($order->id),
                    'message' => 'No se pudo obtener información del pago de MercadoPago'
                ];
            }
            
            // Verificar si el estado cambió
            $newStatus = $this->mapMercadoPagoStatus($paymentData['status']);
            $updated = false;
            
            if ($payment->status !== $newStatus) {
                $payment->update([
                    'status' => $newStatus,
                    'external_id' => $paymentData['id'],
                    'external_response' => json_encode($paymentData),
                ]);
                
                // Actualizar estado de la orden
                $this->updateOrderStatus($order, $paymentData['status']);
                
                $updated = true;
                
                Log::info('Payment status updated via polling', [
                    'order_id' => $order->id,
                    'payment_id' => $paymentId,
                    'old_status' => $payment->status,
                    'new_status' => $newStatus,
                    'mp_status' => $paymentData['status']
                ]);
            }
            
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
    
    /**
     * Mapear estados de MercadoPago a nuestros estados (igual que MercadoPagoWrapper)
     */
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
    
    /**
     * Actualizar estado de la orden basado en el estado del pago
     */
    private function updateOrderStatus(Order $order, string $mercadoPagoStatus): void
    {
        switch ($mercadoPagoStatus) {
            case 'approved':
                $order->update(['status' => 'pendiente']);
                // Descontar stock automáticamente
                app(\App\Services\StockMovementService::class)->processOrderStockReduction($order);
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
                Log::info('Cleaned payment ID in polling service', [
                    'original' => $paymentId,
                    'cleaned' => $numericPart
                ]);
                return $numericPart;
            }
        }
        
        // Si no hay guiones o no es el formato esperado, devolver el ID original
        return $paymentId;
    }
    
    /**
     * Verificar si un ID parece ser un preference_id en lugar de payment_id
     */
    private function looksLikePreferenceId(string $paymentId): bool
    {
        // Los preference_id suelen ser números largos que empiezan con 204
        // Los payment_id reales suelen ser números más cortos (7-10 dígitos)
        return is_numeric($paymentId) && 
               strlen($paymentId) >= 9 && 
               str_starts_with($paymentId, '204');
    }
}
