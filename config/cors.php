<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Covers /api/* (the JWT-protected app API) and /mock-login/* (dev-only
    | login helper, see config/mock_login.php) since both are called directly
    | from the React frontend running on a different origin.
    |
    */

    'paths' => ['api/*', 'mock-login/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', 'https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
