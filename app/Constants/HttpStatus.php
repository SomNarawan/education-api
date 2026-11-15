<?php

namespace App\Constants;

final class HttpStatus
{
    public const OK = [
        'code' => 200,
        'message' => 'OK',
    ];

    public const CREATED = [
        'code' => 201,
        'message' => 'Created',
    ];

    public const UNAUTHORIZED = [
        'code' => 401,
        'message' => 'Unauthorized',
    ];

    public const FORBIDDEN = [
        'code' => 403,
        'message' => 'Forbidden',
    ];

    public const NOT_FOUND = [
        'code' => 404,
        'message' => 'Not Found',
    ];

    public const UNPROCESSABLE_ENTITY = [
        'code' => 422,
        'message' => 'Unprocessable Entity',
    ];

    public const INTERNAL_SERVER_ERROR = [
        'code' => 500,
        'message' => 'Internal Server Error',
    ];

    public const SERVICE_UNAVAILABLE = [
        'code' => 503,
        'message' => 'Service Unavailable',
    ];
}
