<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GradeCalculation\ListGradeCalculationsRequest;
use App\Http\Requests\GradeCalculation\StoreGradeCalculationRequest;
use App\Http\Responses\GradeCalculationResponse;
use App\Models\GradeCalculation;
use App\Models\Student;
use App\Services\GradeCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeCalculationController extends Controller
{
    public function index(ListGradeCalculationsRequest $request): JsonResponse
    {
        if ($this->currentRole($request) === null) {
            return ApiResponse::error(
                'Role is not allowed to view grade calculations',
                HttpStatus::FORBIDDEN['code']
            );
        }

        $studentCode = $this->studentCode($request);
        $query = GradeCalculation::query()
            ->with('student:id,student_code,first_name_th,last_name_th')
            ->latest('created_at')
            ->latest('id');

        if ($studentCode !== null) {
            $query->whereHas(
                'student',
                fn ($studentQuery) => $studentQuery->where('student_code', $studentCode)
            );
        } elseif ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        $calculations = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success([
            'items' => GradeCalculationResponse::collection($calculations->items()),
            'pagination' => [
                'current_page' => $calculations->currentPage(),
                'last_page' => $calculations->lastPage(),
                'per_page' => $calculations->perPage(),
                'total' => $calculations->total(),
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

        $calculation = $service->store(
            $student,
            $request->validated()
        );
        $calculation->setRelation('student', $student);

        return ApiResponse::success(
            new GradeCalculationResponse($calculation),
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
