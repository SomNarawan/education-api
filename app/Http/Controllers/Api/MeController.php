<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class MeController extends Controller
{
    public function show(Request $request)
    {
        $auth = $request->header('Authorization', '');

        if (!preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return ApiResponse::error('Missing or invalid Authorization header', 401);
        }

        $token = $matches[1];

        try {
            $pat = PersonalAccessToken::findToken($token);
        } catch (\Exception $e) {
            $pat = null;
        }

        if ($pat) {
            $tokenable = $pat->tokenable;

            if (!$tokenable) {
                return ApiResponse::error('Token owner not found', 401);
            }

            $now = Carbon::now();
            $expiresAt = $pat->expires_at
                ? Carbon::parse($pat->expires_at)
                : null;

            if ($expiresAt && $now->greaterThan($expiresAt)) {
                return ApiResponse::error('Token expired', 401);
            }

            $name = $this->deriveName($tokenable);

            $abilities = $pat->abilities ?? [];

            if (is_string($abilities)) {
                $decoded = json_decode($abilities, true);
                $abilities = $decoded === null ? [] : $decoded;
            }

            $roles = array_values(array_filter($abilities));

            $nontriId = $tokenable->getKey();
            $departmentId = $tokenable->department_id ?? null;
            $facultyId = $this->getFacultyIdByDepartmentId($departmentId);
            $teacherId = $this->getTeacherIdByNontriId($nontriId);

            $payload = [
                'nontri_id' => $nontriId,
                'teacher_id' => $teacherId,
                'name' => $name,
                'role' => $roles,
                'current_role' => $roles[0] ?? null,
                'department_id' => $departmentId,
                'faculty_id' => $facultyId,
                'iat' => $pat->created_at
                    ? Carbon::parse($pat->created_at)->timestamp
                    : null,
                'exp' => $expiresAt ? $expiresAt->timestamp : null,
            ];

            return ApiResponse::success($payload, 'OK', 200);
        }

        $parts = explode('.', $token);

        if (count($parts) === 3) {
            $payloadJson = $this->base64UrlDecode($parts[1]);
            $claims = json_decode($payloadJson, true);

            if (!$claims) {
                return ApiResponse::error('Invalid token payload', 401);
            }

            if (isset($claims['exp']) && time() > (int) $claims['exp']) {
                return ApiResponse::error('Token expired', 401);
            }

            $roles = [];

            if (isset($claims['role'])) {
                $roles = is_array($claims['role'])
                    ? $claims['role']
                    : [$claims['role']];
            }

            $nontriId = $claims['nontri_id'] ?? null;
            $departmentId = $claims['department_id'] ?? null;
            $facultyId = $this->getFacultyIdByDepartmentId($departmentId);
            $teacherId = $this->getTeacherIdByNontriId($nontriId);

            $payload = [
                'nontri_id' => $nontriId,
                'teacher_id' => $teacherId,
                'name' => $claims['name'] ?? ($claims['given_name'] ?? null),
                'role' => $roles,
                'current_role' => $claims['current_role'] ?? ($roles[0] ?? null),
                'department_id' => $departmentId,
                'faculty_id' => $facultyId,
                'iat' => isset($claims['iat']) ? (int) $claims['iat'] : null,
                'exp' => isset($claims['exp']) ? (int) $claims['exp'] : null,
            ];

            return ApiResponse::success($payload, 'OK', 200);
        }

        return ApiResponse::error('Invalid token format', 401);
    }

    private function getFacultyIdByDepartmentId($departmentId)
    {
        if (!$departmentId) {
            return null;
        }

        return Department::query()
            ->where('id', $departmentId)
            ->value('faculty_id');
    }

    private function getTeacherIdByNontriId($nontriId)
    {
        if (!$nontriId) {
            return null;
        }

        return Teacher::query()
            ->where('nontri_id', $nontriId)
            ->value('id');
    }

    private function deriveName($model)
    {
        $candidates = [
            'name',
            'full_name',
            'full_name_th',
            'first_name_th',
            'first_name_en',
        ];

        foreach ($candidates as $key) {
            if (isset($model->{$key}) && $model->{$key}) {
                if (strpos($key, 'first_name') === 0) {
                    $last = $model->last_name_th
                        ?? $model->last_name_en
                        ?? '';

                    return trim($model->{$key} . ' ' . $last);
                }

                return $model->{$key};
            }
        }

        if (method_exists($model, '__toString')) {
            return (string) $model;
        }

        return (string) ($model->getKey() ?? '');
    }

    private function base64UrlDecode($input)
    {
        $remainder = strlen($input) % 4;

        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }

        $decoded = strtr($input, '-_', '+/');

        return base64_decode($decoded);
    }
}