<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Client;
use Illuminate\Support\Facades\Session;

class ClientCartController extends Controller
{
    /**
     * Display the client's shopping cart.
     */
    public function index(Request $request)
    {
        try {
            $client = $this->getAuthenticatedClient($request);
            
            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $cartItems = $this->getCart($request);
            $cartTotal = 0;
            $cartProducts = [];

            foreach ($cartItems as $item) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $subtotal = $product->price * $item['quantity'];
                    $cartTotal += $subtotal;
                    
                    $cartProducts[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'image' => $product->image,
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                    ];
                }
            }

            $cartData = [
                'items' => $cartProducts,
                'total' => $cartTotal,
                'item_count' => count($cartProducts),
            ];

            return ApiResponseClass::sendResponse(
                $cartData, 
                'Carrito de compras', 
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al obtener carrito', 500, [$e->getMessage()]);
        }
    }

    /**
     * Add a product to the shopping cart.
     */
    public function addToCart(Request $request)
    {
        try {
            $client = $this->getAuthenticatedClient($request);
            
            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return ApiResponseClass::errorResponse('Error de validación', 422, $validator->errors());
            }

            $product = Product::find($request->product_id);
            
            if (!$product) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }
            
            // Check stock availability
            $stock = $product->stocks()->sum('quantity');
            
            if ($stock < $request->quantity) {
                return ApiResponseClass::errorResponse('Stock insuficiente para el producto: ' . $product->name, 400);
            }

            $cart = $this->getCart($request);
            $productExists = false;

            // Update quantity if product already exists in cart
            foreach ($cart as $key => $item) {
                if ($item['product_id'] == $request->product_id) {
                    $cart[$key]['quantity'] += $request->quantity;
                    $productExists = true;
                    break;
                }
            }

            // Add new product if it doesn't exist in cart
            if (!$productExists) {
                $cart[] = [
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                ];
            }

            $this->saveCart($request, $cart);

            return ApiResponseClass::sendResponse(
                [], 
                'Producto agregado al carrito', 
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al agregar producto al carrito', 500, [$e->getMessage()]);
        }
    }

    /**
     * Update the quantity of a product in the shopping cart.
     */
    public function updateQuantity(Request $request, $productId)
    {
        try {
            $client = $this->getAuthenticatedClient($request);
            
            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return ApiResponseClass::errorResponse('Error de validación', 422, $validator->errors());
            }

            $product = Product::find($productId);
            
            if (!$product) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }
            
            // Check stock availability
            $stock = $product->stocks()->sum('quantity');
            
            if ($stock < $request->quantity) {
                return ApiResponseClass::errorResponse('Stock insuficiente para el producto: ' . $product->name, 400);
            }

            $cart = $this->getCart($request);
            $productFound = false;

            foreach ($cart as $key => $item) {
                if ($item['product_id'] == $productId) {
                    $cart[$key]['quantity'] = $request->quantity;
                    $productFound = true;
                    break;
                }
            }

            if (!$productFound) {
                return ApiResponseClass::errorResponse('Producto no encontrado en el carrito', 404);
            }

            $this->saveCart($request, $cart);

            return ApiResponseClass::sendResponse(
                [], 
                'Cantidad actualizada', 
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al actualizar cantidad', 500, [$e->getMessage()]);
        }
    }

    /**
     * Remove a product from the shopping cart.
     */
    public function removeFromCart(Request $request, $productId)
    {
        try {
            $client = $this->getAuthenticatedClient($request);
            
            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $cart = $this->getCart($request);
            $updatedCart = [];
            $productFound = false;

            foreach ($cart as $item) {
                if ($item['product_id'] != $productId) {
                    $updatedCart[] = $item;
                } else {
                    $productFound = true;
                }
            }

            if (!$productFound) {
                return ApiResponseClass::errorResponse('Producto no encontrado en el carrito', 404);
            }

            $this->saveCart($request, $updatedCart);

            return ApiResponseClass::sendResponse(
                [], 
                'Producto eliminado del carrito', 
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al eliminar producto del carrito', 500, [$e->getMessage()]);
        }
    }

    /**
     * Clear the shopping cart.
     */
    public function clearCart(Request $request)
    {
        try {
            $client = $this->getAuthenticatedClient($request);
            
            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $this->saveCart($request, []);

            return ApiResponseClass::sendResponse(
                [], 
                'Carrito vaciado', 
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al vaciar carrito', 500, [$e->getMessage()]);
        }
    }

    /**
     * Get the cart from the session or database.
     */
    private function getCart(Request $request)
    {
        $client = $this->getAuthenticatedClient($request);
        
        if (!$client) {
            return [];
        }

        // In a real application, you might store the cart in a database
        // For this example, we'll use the session
        $cartKey = 'cart_' . $client->id;
        
        if ($request->session()->has($cartKey)) {
            return $request->session()->get($cartKey);
        }
        
        return [];
    }

    /**
     * Save the cart to the session or database.
     */
    private function saveCart(Request $request, $cart)
    {
        $client = $this->getAuthenticatedClient($request);
        
        if (!$client) {
            return;
        }

        // In a real application, you might store the cart in a database
        // For this example, we'll use the session
        $cartKey = 'cart_' . $client->id;
        $request->session()->put($cartKey, $cart);
    }

    /**
     * Get authenticated client from token
     */
    private function getAuthenticatedClient($request)
    {
        try {
            $token = str_replace('Bearer ', '', $request->header('Authorization'));
            
            if (!$token) {
                return null;
            }

            $decoded = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
            
            if ($decoded->type !== 'client') {
                return null;
            }

            return Client::find($decoded->sub);
        } catch (\Exception $e) {
            return null;
        }
    }
}
