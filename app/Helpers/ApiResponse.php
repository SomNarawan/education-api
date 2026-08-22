<?php

namespace App\Helpers;

use App\Constants\HttpStatus;

class ApiResponse
{
    public static function success($data = null, ?string $message = null, ?int $status = null)
    {
        $message ??= HttpStatus::OK['message'];
        $status ??= HttpStatus::OK['code'];

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status, [], JSON_UNESCAPED_UNICODE);
    }

    public static function error(?string $message = null, ?int $status = null, $errors = null)
    {
        $message ??= HttpStatus::INTERNAL_SERVER_ERROR['message'];
        $status ??= HttpStatus::INTERNAL_SERVER_ERROR['code'];

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status, [], JSON_UNESCAPED_UNICODE);
    }
}
