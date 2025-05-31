<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Roles
Route::apiResource('roles', RoleController::class);

// Users
Route::apiResource('users', UserController::class);

// Clients
Route::apiResource('clients', ClientController::class);

// Client Addresses
Route::prefix('clients/{client}')->group(function () {
    Route::apiResource('addresses', AddressController::class);
    Route::put('addresses/{address}/set-as-main', [AddressController::class, 'setAsMain'])->name('addresses.set-as-main');
});

// Products
Route::apiResource('products', ProductController::class);
Route::post('products/{product}/stock', [ProductController::class, 'updateStock'])->name('products.stock.update');
Route::get('products/{product}/movements', [ProductController::class, 'stockMovements'])->name('products.stock.movements');

// Orders
Route::apiResource('orders', OrderController::class);
Route::post('orders/{order}/process-payment', [OrderController::class, 'processPayment'])->name('orders.process-payment');
Route::get('orders/status/{status}', [OrderController::class, 'byStatus'])->name('orders.by-status');
