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

    /**
     * $code is a machine-readable reason for clients, e.g. PORTAL_SESSION_ENDED.
     */
    public static function error(?string $message = null, ?int $status = null, $errors = null, ?string $code = null)
    {
        $message ??= HttpStatus::INTERNAL_SERVER_ERROR['message'];
        $status ??= HttpStatus::INTERNAL_SERVER_ERROR['code'];

        $body = ['success' => false];

        if ($code !== null) {
            $body['code'] = $code;
        }

        return response()->json($body + [
            'message' => $message,
            'errors' => $errors,
        ], $status, [], JSON_UNESCAPED_UNICODE);
    }
}
