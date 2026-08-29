<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'OPTIONS'],

    /*
     * Alleen de dashboard- en websitedomeinen mogen de API aanroepen.
     * Komma-gescheiden in DASHBOARD_ORIGINS; leeg betekent alleen lokaal.
     */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DASHBOARD_ORIGINS', 'http://localhost:3010,http://localhost:3000')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
