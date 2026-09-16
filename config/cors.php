<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Covers /api/* — the JWT-protected app API and, since it now lives at
    | /api/mock-login/*, the dev-only login helper too (see
    | config/mock_login.php) — since both are called directly from the React
    | frontend running on a different origin.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URL', 'https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
