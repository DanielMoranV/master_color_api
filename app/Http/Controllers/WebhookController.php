<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Services\PaymentService;
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
            Log::info('MercadoPago webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
                'ip' => $request->ip()
            ]);

            // Verificar que sea una notificación válida
            if (!$request->has(['id', 'topic'])) {
                Log::warning('Invalid webhook payload', $request->all());
                return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
            }

            // Procesar la notificación
            $paymentService = app(PaymentService::class);
            $result = $paymentService->processWebhookNotification($request->all());

            if ($result) {
                Log::info('Webhook processed successfully', [
                    'payment_id' => $request->input('id'),
                    'topic' => $request->input('topic')
                ]);
                
                return response()->json(['status' => 'success'], 200);
            } else {
                Log::error('Webhook processing failed', [
                    'payment_id' => $request->input('id'),
                    'topic' => $request->input('topic')
                ]);
                
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
     * Get payment status for a specific order (for frontend polling)
     */
    public function getPaymentStatus($orderId)
    {
        try {
            $order = \App\Models\Order::with('payment')->find($orderId);
            
            if (!$order) {
                return ApiResponseClass::errorResponse('Orden no encontrada', 404);
            }

            $payment = $order->payment()->where('payment_method', 'MercadoPago')->first();
            
            if (!$payment) {
                return ApiResponseClass::sendResponse([
                    'order_status' => $order->status,
                    'payment_status' => null,
                    'has_payment' => false
                ], 'Estado de la orden', 200);
            }

            return ApiResponseClass::sendResponse([
                'order_status' => $order->status,
                'payment_status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'payment_code' => $payment->payment_code,
                'has_payment' => true,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at
            ], 'Estado del pago', 200);

        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error interno del servidor', 500, [$e->getMessage()]);
        }
    }
}