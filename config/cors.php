<?php
// config/cors.php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie', // Tambahkan ini untuk Laravel Sanctum
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173', // React Vite default
        'http://localhost:3000', // React CRA default
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
        'http://localhost:8000', // Laravel (untuk testing)
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // WAJIB true karena frontend pake withCredentials
];