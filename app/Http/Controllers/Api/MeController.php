<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Teacher;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request)
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $roles = isset($claims['role'])
            ? (is_array($claims['role']) ? $claims['role'] : [$claims['role']])
            : [];
        $nontriId = $claims['nontri_id'] ?? null;
        $departmentId = $claims['department_id'] ?? null;

        return ApiResponse::success([
            'nontri_id' => $nontriId,
            'teacher_id' => $this->getTeacherIdByNontriId($nontriId),
            'name' => $claims['name'] ?? ($claims['given_name'] ?? null),
            'role' => $roles,
            'current_role' => $claims['current_role'] ?? ($roles[0] ?? null),
            'department_id' => $departmentId,
            'faculty_id' => $this->getFacultyIdByDepartmentId($departmentId),
            'iat' => isset($claims['iat']) ? (int) $claims['iat'] : null,
            'exp' => isset($claims['exp']) ? (int) $claims['exp'] : null,
        ], 'OK', 200);
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

}
