<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ClientAuthController;
use App\Http\Controllers\ClientAddressController;
use App\Http\Controllers\ClientOrderController;
use App\Http\Controllers\ClientCartController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

    // Rutas que requieren autenticación
    Route::middleware(['jwt.auth', 'check.token.version'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('/me', [AuthController::class, 'me'])->name('auth.me');
    });
});

/*
|--------------------------------------------------------------------------
| CLIENT AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('client/auth')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register'])->name('client.auth.register');
    Route::post('/login', [ClientAuthController::class, 'login'])->name('client.auth.login');
    Route::post('/verify-email', [ClientAuthController::class, 'verifyEmail'])->name('client.auth.verify-email');
    Route::post('/resend-verification', [ClientAuthController::class, 'resendVerificationEmail'])->name('client.auth.resend-verification');
    Route::post('/forgot-password', [ClientAuthController::class, 'forgotPassword'])->name('client.auth.forgot-password');
    Route::post('/reset-password', [ClientAuthController::class, 'resetPassword'])->name('client.auth.reset-password');

    // Rutas que requieren autenticación de cliente
    Route::middleware([\App\Http\Middleware\ClientAuth::class])->group(function () {
        Route::get('/profile', [ClientAuthController::class, 'profile'])->name('client.profile');
        Route::post('/me', [ClientAuthController::class, 'me'])->name('client.me');
        Route::put('/profile', [ClientAuthController::class, 'updateProfile'])->name('client.profile.update');
        Route::post('/change-password', [ClientAuthController::class, 'changePassword'])->name('client.password.change');
        Route::post('/refresh', [ClientAuthController::class, 'refresh'])->name('client.auth.refresh');
        Route::post('/logout', [ClientAuthController::class, 'logout'])->name('client.auth.logout');
    });
});

/*
|--------------------------------------------------------------------------
| CLIENT ADDRESS ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('client/addresses')->middleware([\App\Http\Middleware\ClientAuth::class])->group(function () {
    Route::get('/', [ClientAddressController::class, 'index'])->name('client.addresses.index');
    Route::post('/', [ClientAddressController::class, 'store'])->name('client.addresses.store');
    Route::get('/{id}', [ClientAddressController::class, 'show'])->name('client.addresses.show');
    Route::put('/{id}', [ClientAddressController::class, 'update'])->name('client.addresses.update');
    Route::delete('/{id}', [ClientAddressController::class, 'destroy'])->name('client.addresses.destroy');
    Route::put('/{id}/set-main', [ClientAddressController::class, 'setAsMain'])->name('client.addresses.set-main');
});

/*
|--------------------------------------------------------------------------
| CLIENT ORDER ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('client/orders')->middleware([\App\Http\Middleware\ClientAuth::class])->group(function () {
    Route::get('/', [ClientOrderController::class, 'index'])->name('client.orders.index');
    Route::post('/', [ClientOrderController::class, 'store'])->name('client.orders.store');
    Route::get('/{id}', [ClientOrderController::class, 'show'])->name('client.orders.show');
    Route::get('/{id}/track', [ClientOrderController::class, 'trackOrder'])->name('client.orders.track');
    Route::put('/{id}/cancel', [ClientOrderController::class, 'cancelOrder'])->name('client.orders.cancel');
    Route::post('/{id}/payment', [ClientOrderController::class, 'createPayment'])->name('client.orders.payment');
});

/*
|--------------------------------------------------------------------------
| CLIENT CART ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('client/cart')->middleware([\App\Http\Middleware\ClientAuth::class])->group(function () {
    Route::get('/', [ClientCartController::class, 'index'])->name('client.cart.index');
    Route::post('/add', [ClientCartController::class, 'addToCart'])->name('client.cart.add');
    Route::put('/update/{productId}', [ClientCartController::class, 'updateQuantity'])->name('client.cart.update');
    Route::delete('/remove/{productId}', [ClientCartController::class, 'removeFromCart'])->name('client.cart.remove');
    Route::delete('/clear', [ClientCartController::class, 'clearCart'])->name('client.cart.clear');
});

/*
|--------------------------------------------------------------------------
| USER ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['jwt.auth', 'check.token.version', 'admin.only'])->group(function () {
    Route::apiResource('users', UserController::class);
});

/*
|--------------------------------------------------------------------------
| ROLE ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['jwt.auth', 'check.token.version', 'admin.only'])->group(function () {
    Route::apiResource('roles', RoleController::class);
});

/*
|--------------------------------------------------------------------------
| PRODUCT ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('products')->group(function () {
    Route::get('/public', [ProductController::class, 'publicIndex'])->name('products.public.index');
    Route::post('/updateProduct/{id}', [ProductController::class, 'updateProduct'])->name('products.updateProduct');
});

Route::middleware(['jwt.auth', 'check.token.version', 'admin.only'])->group(function () {
    Route::apiResource('products', ProductController::class);
});

/*
|--------------------------------------------------------------------------
| STOCK MOVEMENT ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['jwt.auth', 'check.token.version', 'admin.only'])->group(function () {
    Route::apiResource('stock-movements', StockMovementController::class);
    Route::patch('stock-movements/{stockMovement}/cancel', [StockMovementController::class, 'cancel'])->name('stock-movements.cancel');
});

/*
|--------------------------------------------------------------------------
| WEBHOOK ROUTES
|--------------------------------------------------------------------------
*/

// MercadoPago webhook (sin autenticación para permitir notificaciones)
Route::post('webhooks/mercadopago', [WebhookController::class, 'mercadoPago'])->name('webhooks.mercadopago');
// Endpoint fallback que recibe payment_id desde el frontend tras el retorno de pago
Route::post('payment-return', [WebhookController::class, 'paymentReturn'])->name('payment.return');

// Payment status check (con autenticación para clientes)
Route::get('payment-status/{orderId}', [WebhookController::class, 'getPaymentStatus'])
    ->middleware([\App\Http\Middleware\ClientAuth::class])
    ->name('payment.status');

// Ruta de prueba para MercadoPago (solo desarrollo)
if (app()->environment('local')) {
    Route::get('test/mercadopago', function () {
        $paymentService = app(\App\Services\PaymentService::class);
        $result = $paymentService->testMercadoPagoConnection();
        
        return response()->json($result);
    })->name('test.mercadopago');
    
    // Test webhook endpoint (temporal para debugging)
    Route::post('/test/webhook', function(Request $request) {
        \Log::info('🧪 TEST WEBHOOK RECIBIDO', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'raw_body' => $request->getContent(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return response()->json(['status' => 'received', 'message' => 'Test webhook logged']);
    })->name('test.webhook');
}
