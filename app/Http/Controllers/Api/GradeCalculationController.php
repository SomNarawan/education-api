<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GradeCalculation\ListGradeCalculationsRequest;
use App\Http\Requests\GradeCalculation\StoreGradeCalculationRequest;
use App\Models\Student;
use App\Services\GradeCalculationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeCalculationController extends Controller
{
    public function index(
        ListGradeCalculationsRequest $request,
        GradeCalculationService $service
    ): JsonResponse {
        $this->authorizeAction($request, 'view');
        $studentCode = $this->selectedStudentCode($request);

        // A selected but unknown student must return an empty list, not all history.
        $calculations = $studentCode === null && $request->filled('student_id')
            ? []
            : $service->history($studentCode);

        return $this->historyResponse($calculations, $request);
    }

    public function latest(
        ListGradeCalculationsRequest $request,
        GradeCalculationService $service
    ): JsonResponse {
        $this->authorizeAction($request, 'view');
        $this->requireStudentSelection($request);
        $studentCode = $this->selectedStudentCode($request);

        if ($studentCode === null) {
            return ApiResponse::success(null, 'No grade calculation history found');
        }

        return ApiResponse::success(
            $service->latest($studentCode),
            'Load latest grade calculation successfully'
        );
    }

    public function store(
        StoreGradeCalculationRequest $request,
        GradeCalculationService $service
    ): JsonResponse {
        $this->authorizeAction($request, 'save');
        $this->requireStudentSelection($request);
        $student = $this->selectedStudent($request);

        if ($student === null) {
            return ApiResponse::error('Student not found', HttpStatus::NOT_FOUND['code']);
        }

        return ApiResponse::success(
            $service->store($student, $request->validated()),
            'Save grade calculation successfully',
            HttpStatus::CREATED['code']
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

    private function authorizeAction(Request $request, string $action): void
    {
        if ($this->currentRole($request) === null) {
            throw new HttpResponseException(ApiResponse::error(
                'Role is not allowed to '.$action.' grade calculations',
                HttpStatus::FORBIDDEN['code']
            ));
        }
    }

    private function requireStudentSelection(Request $request): void
    {
        if ($this->studentCode($request) === null && ! $request->filled('student_id')) {
            throw new HttpResponseException(ApiResponse::error(
                'student_id is required',
                HttpStatus::UNPROCESSABLE_ENTITY['code']
            ));
        }
    }

    private function selectedStudentCode(Request $request): ?string
    {
        $studentCode = $this->studentCode($request);

        if ($studentCode !== null) {
            return $studentCode;
        }

        if (! $request->filled('student_id')) {
            return null;
        }

        return Student::query()
            ->whereKey($request->integer('student_id'))
            ->value('student_code');
    }

    private function selectedStudent(Request $request): ?Student
    {
        $studentCode = $this->studentCode($request);

        if ($studentCode !== null) {
            return Student::query()->where('student_code', $studentCode)->first();
        }

        return Student::query()->find($request->integer('student_id'));
    }

    private function studentCode(Request $request): ?string
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $studentCode = $claims['nontri_id'] ?? null;

        if (
            $this->currentRole($request) !== 'student'
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
