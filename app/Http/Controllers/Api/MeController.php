<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SystemDepartment;
use App\Models\Teacher;
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
        $nontriId = $claims['nontri_id'] ?? null;
        $teacher = $this->findTeacher($nontriId);
        $departmentId = $teacher?->department_id
            ?? $this->validSystemDepartmentId($claims['department_id'] ?? null);

        return ApiResponse::success([
            'nontri_id' => $nontriId,
            'teacher_id' => $teacher?->id,
            'name' => $claims['name'] ?? ($claims['given_name'] ?? null),
            'role' => $roles,
            'current_role' => $claims['current_role'] ?? ($roles[0] ?? null),
            'department_id' => $departmentId,
            'faculty_id' => $this->getFacultyIdByDepartmentId($departmentId),
            'iat' => isset($claims['iat']) ? (int) $claims['iat'] : null,
            'exp' => isset($claims['exp']) ? (int) $claims['exp'] : null,
        ], 'OK', 200);
    }

    private function getFacultyIdByDepartmentId(mixed $departmentId): ?int
    {
        if (! is_numeric($departmentId)) {
            return null;
        }

        $facultyId = SystemDepartment::query()
            ->where('id', $departmentId)
            ->value('system_faculty_id');

        return $facultyId === null ? null : (int) $facultyId;
    }

    private function findTeacher(mixed $nontriId): ?Teacher
    {
        if (! is_scalar($nontriId) || trim((string) $nontriId) === '') {
            return null;
        }

        return Teacher::query()
            ->where('nontri_id', $nontriId)
            ->first(['id', 'department_id']);
    }

    private function validSystemDepartmentId(mixed $departmentId): ?int
    {
        if (! is_numeric($departmentId)) {
            return null;
        }

        return SystemDepartment::query()->whereKey($departmentId)->exists()
            ? (int) $departmentId
            : null;
    }
}
