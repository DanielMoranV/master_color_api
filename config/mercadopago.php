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
    'success_url' => env('APP_FRONTEND_URL', 'http://localhost:5173') . '/payment/success',
    'failure_url' => env('APP_FRONTEND_URL', 'http://localhost:5173') . '/payment/failure',
    'pending_url' => env('APP_FRONTEND_URL', 'http://localhost:5173') . '/payment/pending',

    // Configuración de la aplicación (máximo 13 caracteres, solo letras y números)
    'statement_descriptor' => 'MasterColor',
    'currency' => 'PEN',
    'country' => 'PE',
];
