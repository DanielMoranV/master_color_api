<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://laravel.com/docs/http#cors
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Specify the paths that should be accessible over CORS. Using "api/*"
    | is usually sufficient for REST APIs. You can add more paths or use
    | "*" to apply CORS to all routes.
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'products/*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | The HTTP methods that are allowed for cross-origin requests.
    | Use ['*'] to allow all standard methods.
    */
    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Origins that are allowed to access the application. You can either
    | allow all with ['*'] or be explicit for security.
    */
    'allowed_origins' => [
        env('FRONTEND_URL', 'https://master-color-frontend.vercel.app'),
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns
    |--------------------------------------------------------------------------
    |
    | Patterns that can be used to dynamically allow origins using wildcards.
    */
    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    */
    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Headers that are exposed to the browser.
    */
    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | Indicates how long the results of a preflight request can be cached.
    */
    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | Whether the response to the request can be exposed when the credentials
    | flag is true. When allowed, the browser may send cookies along with the
    | request if needed.
    */
    'supports_credentials' => true,
];
