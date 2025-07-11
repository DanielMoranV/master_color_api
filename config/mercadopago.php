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

    // Configuración del entorno
    'sandbox' => env('MERCADOPAGO_SANDBOX', true),

    // Access token según entorno
    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),

    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),

    // Clave secreta para validar x-signature
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),

    // URLs de retorno - sin parámetros (MercadoPago agregará los suyos)
    'success_url' => env('APP_FRONTEND_URL', 'http://localhost:5173') . '/payment-return/success',
    'failure_url' => env('APP_FRONTEND_URL', 'http://localhost:5173') . '/payment-return/failure',
    'pending_url' => env('APP_FRONTEND_URL', 'http://localhost:5173') . '/payment-return/pending',

    // Configuración de la aplicación (máximo 13 caracteres, solo letras y números)
    'statement_descriptor' => 'MasterColor',
    'currency' => 'PEN',
    'country' => 'PE',
];
