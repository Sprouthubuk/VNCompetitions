<?php

return [

    // The storefront origin (CORS, links in emails).
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:4321'),

    // Seconds the public API responses are cached for.
    'api_cache_ttl' => (int) env('API_CACHE_TTL', 15),

    // First admin, created by AdminUserSeeder. An empty password means "generate one".
    'admin' => [
        'name' => env('ADMIN_NAME', 'Vault Nine Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@vaultninecompetitions.co.uk'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
