<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MercadoPago Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para la integración con MercadoPago
    |
    */

    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
    
    // Configuración del entorno
    'sandbox' => env('MERCADOPAGO_SANDBOX', true),
    
    // URLs de retorno
    'success_url' => env('APP_FRONTEND_URL', 'http://localhost:3000') . '/payment/success',
    'failure_url' => env('APP_FRONTEND_URL', 'http://localhost:3000') . '/payment/failure',
    'pending_url' => env('APP_FRONTEND_URL', 'http://localhost:3000') . '/payment/pending',
    
    // Configuración de la aplicación
    'statement_descriptor' => 'Master Color',
    'currency' => 'PEN',
    'country' => 'PE',
];