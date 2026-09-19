<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * API: GET /api/me
     */
    public function show(Request $request)
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $roles = isset($claims['role'])
            ? (is_array($claims['role']) ? $claims['role'] : [$claims['role']])
            : [];

        return ApiResponse::success([
            'nontri_id' => $claims['nontri_id'] ?? null,
            'teacher_id' => $claims['teacher_id'] ?? null,
            'name' => $claims['name'] ?? ($claims['given_name'] ?? null),
            'role' => $roles,
            'current_role' => $claims['current_role'] ?? ($roles[0] ?? null),
            'department_id' => $claims['department_id'] ?? null,
            'faculty_id' => $claims['faculty_id'] ?? null,
            'iat' => isset($claims['iat']) ? (int) $claims['iat'] : null,
            'exp' => isset($claims['exp']) ? (int) $claims['exp'] : null,
        ], HttpStatus::OK['message'], HttpStatus::OK['code']);
    }
}
