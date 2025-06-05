<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderDetailResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;

class ClientOrderController extends Controller
{
    /**
     * Display a listing of the client's orders.
     */
    public function index(Request $request)
    {
        try {
            $client = Auth::guard('client')->user();
            
            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $status = $request->query('status');
            
            $query = $client->orders()->with(['orderDetails.product']);
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $orders = $query->orderBy('created_at', 'desc')->paginate(10);
            
            return ApiResponseClass::sendResponse(
                OrderResource::collection($orders), 
                'Historial de pedidos', 
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al obtener pedidos', 500, [$e->getMessage()]);
        }
    }

    /**
     * Display the specified order with details.
     */
    public function show(Request $request, $id)
    {
        try {
            $client = Auth::guard('client')->user();
            
            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $order = $client->orders()->with(['orderDetails.product'])->find($id);
            
            if (!$order) {
                return ApiResponseClass::errorResponse('Pedido no encontrado', 404);
            }

            return ApiResponseClass::sendResponse(
                new OrderResource($order), 
                'Detalle de pedido', 
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al obtener pedido', 500, [$e->getMessage()]);
        }
    }

    /**
     * Track the status of an order.
     */
    public function trackOrder(Request $request, $id)
    {
        try {
            $client = Auth::guard('client')->user();
            
            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $order = $client->orders()->find($id);
            
            if (!$order) {
                return ApiResponseClass::errorResponse('Pedido no encontrado', 404);
            }

            $trackingInfo = [
                'order_id' => $order->id,
                'status' => $order->status,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'tracking_history' => $this->getTrackingHistory($order),
                'estimated_delivery' => $this->getEstimatedDelivery($order),
            ];

            return ApiResponseClass::sendResponse(
                $trackingInfo, 
                'Seguimiento de pedido', 
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al rastrear pedido', 500, [$e->getMessage()]);
        }
    }

    /**
     * Create a new order.
     */
    public function store(Request $request)
    {
        try {
            $client = Auth::guard('client')->user();
            
            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $validator = Validator::make($request->all(), [
                'delivery_address_id' => 'required|exists:addresses,id,client_id,' . $client->id,
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'observations' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponseClass::errorResponse('Error de validación', 422, $validator->errors());
            }

            DB::beginTransaction();

            // Calculate order total
            $subtotal = 0;
            $productDetails = [];

            foreach ($request->products as $item) {
                $product = Product::find($item['product_id']);
                
                if (!$product) {
                    DB::rollBack();
                    return ApiResponseClass::errorResponse('Producto no encontrado: ' . $item['product_id'], 404);
                }
                
                // Check stock availability
                $stock = $product->stocks()->sum('quantity');
                
                if ($stock < $item['quantity']) {
                    DB::rollBack();
                    return ApiResponseClass::errorResponse('Stock insuficiente para el producto: ' . $product->name, 400);
                }
                
                $itemSubtotal = $product->price * $item['quantity'];
                $subtotal += $itemSubtotal;
                
                $productDetails[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $itemSubtotal,
                ];
            }

            // Create order
            $order = Order::create([
                'client_id' => $client->id,
                'user_id' => 1, // Default system user ID, update as needed
                'delivery_address_id' => $request->delivery_address_id,
                'subtotal' => $subtotal,
                'shipping_cost' => 0, // You can calculate this based on your business logic
                'discount' => 0, // You can apply discounts based on your business logic
                'status' => 'pendiente',
                'observations' => $request->observations,
            ]);

            // Create order details
            foreach ($productDetails as $detail) {
                $order->orderDetails()->create($detail);
            }

            DB::commit();

            return ApiResponseClass::sendResponse(
                new OrderResource($order->load('orderDetails')), 
                'Pedido creado exitosamente', 
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseClass::errorResponse('Error al crear pedido', 500, [$e->getMessage()]);
        }
    }

    /**
     * Cancel an order if it's in a cancellable state.
     */
    public function cancelOrder(Request $request, $id)
    {
        try {
            $client = Auth::guard('client')->user();
            
            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $order = $client->orders()->find($id);
            
            if (!$order) {
                return ApiResponseClass::errorResponse('Pedido no encontrado', 404);
            }

            // Check if order can be cancelled (only pending or confirmed orders)
            $cancellableStatuses = ['pendiente', 'confirmado'];
            
            if (!in_array($order->status, $cancellableStatuses)) {
                return ApiResponseClass::errorResponse('Este pedido no puede ser cancelado en su estado actual', 400);
            }

            $order->status = 'cancelado';
            $order->save();

            return ApiResponseClass::sendResponse(
                new OrderResource($order), 
                'Pedido cancelado exitosamente', 
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al cancelar pedido', 500, [$e->getMessage()]);
        }
    }

    /**
     * Get tracking history for an order.
     */
    private function getTrackingHistory($order)
    {
        // In a real application, you would have a table to track status changes
        // For now, we'll simulate with the current status
        $history = [];
        
        switch ($order->status) {
            case 'entregado':
                $history[] = [
                    'status' => 'entregado',
                    'date' => $order->updated_at,
                    'description' => 'Pedido entregado al cliente'
                ];
                // Fall through to add previous statuses
            case 'enviado':
                $history[] = [
                    'status' => 'enviado',
                    'date' => $order->updated_at,
                    'description' => 'Pedido en ruta de entrega'
                ];
                // Fall through to add previous statuses
            case 'procesando':
                $history[] = [
                    'status' => 'procesando',
                    'date' => $order->updated_at,
                    'description' => 'Pedido en preparación'
                ];
                // Fall through to add previous statuses
            case 'confirmado':
                $history[] = [
                    'status' => 'confirmado',
                    'date' => $order->updated_at,
                    'description' => 'Pedido confirmado'
                ];
                // Fall through to add previous statuses
            case 'pendiente':
                $history[] = [
                    'status' => 'pendiente',
                    'date' => $order->created_at,
                    'description' => 'Pedido recibido'
                ];
                break;
            case 'cancelado':
                $history[] = [
                    'status' => 'cancelado',
                    'date' => $order->updated_at,
                    'description' => 'Pedido cancelado'
                ];
                $history[] = [
                    'status' => 'pendiente',
                    'date' => $order->created_at,
                    'description' => 'Pedido recibido'
                ];
                break;
        }
        
        return array_reverse($history);
    }

    /**
     * Get estimated delivery date based on order status.
     */
    private function getEstimatedDelivery($order)
    {
        if ($order->status === 'cancelado') {
            return null;
        }
        
        if ($order->status === 'entregado') {
            return $order->updated_at;
        }
        
        // Calculate estimated delivery date (e.g., 3 days from order date)
        return $order->created_at->addDays(3)->format('Y-m-d');
    }


}
