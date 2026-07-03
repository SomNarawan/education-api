<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT authentication
    |--------------------------------------------------------------------------
    |
    | Keep this secret separate from APP_KEY. The service that issues tokens
    | must use the same secret and algorithm.
    |
    */
    'secret' => env('JWT_SECRET'),
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),
    'issuer' => env('JWT_ISSUER'),
    'audience' => env('JWT_AUDIENCE'),
    'leeway' => (int) env('JWT_LEEWAY', 0),
    'require_expiration' => filter_var(
        env('JWT_REQUIRE_EXPIRATION', false),
        FILTER_VALIDATE_BOOL
    ),
];
