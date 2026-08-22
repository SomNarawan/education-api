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

    public const NO_CONTENT = [
        'code' => 204,
        'message' => 'No Content',
    ];

    public const BAD_REQUEST = [
        'code' => 400,
        'message' => 'Bad Request',
    ];

    public const UNAUTHORIZED = [
        'code' => 401,
        'message' => 'Unauthorized',
    ];

    public const PAYMENT_REQUIRED = [
        'code' => 402,
        'message' => 'Payment Required',
    ];

    public const FORBIDDEN = [
        'code' => 403,
        'message' => 'Forbidden',
    ];

    public const NOT_FOUND = [
        'code' => 404,
        'message' => 'Not Found',
    ];

    public const METHOD_NOT_ALLOWED = [
        'code' => 405,
        'message' => 'Method Not Allowed',
    ];

    public const CONFLICT = [
        'code' => 409,
        'message' => 'Conflict',
    ];

    public const UNPROCESSABLE_ENTITY = [
        'code' => 422,
        'message' => 'Unprocessable Entity',
    ];

    public const INTERNAL_SERVER_ERROR = [
        'code' => 500,
        'message' => 'Internal Server Error',
    ];
}
