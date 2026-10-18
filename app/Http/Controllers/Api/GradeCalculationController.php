<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GradeCalculation\ListGradeCalculationsRequest;
use App\Http\Requests\GradeCalculation\StoreGradeCalculationRequest;
use App\Models\Student;
use App\Services\GradeCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeCalculationController extends Controller
{
    public function index(
        ListGradeCalculationsRequest $request,
        GradeCalculationService $service
    ): JsonResponse {
        if ($this->currentRole($request) === null) {
            return ApiResponse::error(
                'Role is not allowed to view grade calculations',
                HttpStatus::FORBIDDEN['code']
            );
        }

        $studentCode = $this->studentCode($request);

        if ($studentCode === null && $request->filled('student_id')) {
            $studentCode = Student::query()
                ->whereKey($request->integer('student_id'))
                ->value('student_code');

            if ($studentCode === null) {
                return $this->historyResponse([], $request);
            }
        }

        return $this->historyResponse(
            $service->history($studentCode),
            $request
        );
    }

    private function historyResponse(
        array $calculations,
        ListGradeCalculationsRequest $request
    ): JsonResponse {
        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 20);
        $total = count($calculations);

        return ApiResponse::success([
            'items' => array_slice($calculations, ($page - 1) * $perPage, $perPage),
            'pagination' => [
                'current_page' => $page,
                'last_page' => max((int) ceil($total / $perPage), 1),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ], 'Load grade calculation history successfully');
    }

    public function store(
        StoreGradeCalculationRequest $request,
        GradeCalculationService $service
    ): JsonResponse {
        $studentCode = $this->studentCode($request);

        if ($studentCode === null) {
            return ApiResponse::error(
                'Only the student role can save grade calculations',
                HttpStatus::FORBIDDEN['code']
            );
        }

        $student = Student::query()
            ->where('student_code', $studentCode)
            ->first();

        if ($student === null) {
            return ApiResponse::error('Student not found', HttpStatus::NOT_FOUND['code']);
        }

        $calculation = $service->store($student, $request->validated());

        return ApiResponse::success(
            $calculation,
            'Save grade calculation successfully',
            HttpStatus::CREATED['code']
        );
    }

    private function studentCode(Request $request): ?string
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $currentRole = $this->currentRole($request);
        $studentCode = $claims['nontri_id'] ?? null;

        if (
            $currentRole !== 'student'
            || ! is_scalar($studentCode)
            || trim((string) $studentCode) === ''
        ) {
            return null;
        }

        return trim((string) $studentCode);
    }

    private function currentRole(Request $request): ?string
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $roles = $claims['role'] ?? [];
        $roles = is_array($roles) ? $roles : [$roles];
        $currentRole = $claims['current_role'] ?? ($roles[0] ?? null);

        if (
            ! is_string($currentRole)
            || ! in_array($currentRole, ['admin', 'teacher', 'student'], true)
            || ! in_array($currentRole, $roles, true)
        ) {
            return null;
        }

        return $currentRole;
    }
}
