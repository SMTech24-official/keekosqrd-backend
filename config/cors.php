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
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Allowed origins for CORS requests
    'allowed_origins' => [
        // 'https://orfa-ai-frontend.vercel.app', // Next.js frontend
        // 'https://darkgreen-wasp-153824.hostingersite.com', // Laravel backend (optional, for self-origin requests)
        'https://ksquaredsourcedcity.com',
        'https://dashboard.ksquaredsourcedcity.com',
        'http://localhost:3000',
        'http://localhost:3001',
    ],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => true,

];
