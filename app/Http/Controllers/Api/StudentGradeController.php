<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ResetStudentGradesRequest;
use App\Models\Student;
use App\Services\Grades\StudentGradeJsonGenerator;
use App\Services\Grades\StudentGradePlanResolver;
use Illuminate\Http\JsonResponse;

class StudentGradeController extends Controller
{
    public function __construct(
        private readonly StudentGradePlanResolver $planResolver,
        private readonly StudentGradeJsonGenerator $generator,
    ) {}

    /**
     * API: DELETE /api/students/{studentCode}/grades
     */
    public function reset(
        ResetStudentGradesRequest $request,
        string $studentCode,
    ): JsonResponse {
        $student = Student::query()
            ->where('student_code', $studentCode)
            ->first([
                'student_code',
                'curriculum_id',
                'study_plan_id',
                'study_year',
                'study_semester',
            ]);

        if ($student === null) {
            return ApiResponse::error(
                'Student not found',
                HttpStatus::NOT_FOUND['code'],
            );
        }

        $validated = $request->validated();
        $scope = $validated['scope'];

        if ($scope === 'all') {
            $resetCount = $this->generator->resetAll($studentCode);

            return $this->resetResponse(
                $studentCode,
                $scope,
                $resetCount,
            );
        }

        $studyYear = (int) $validated['study_year'];
        $semester = (int) $validated['semester'];

        if (! $this->isPreviousSemester($student, $studyYear, $semester)) {
            return ApiResponse::error(
                'Only a previous semester can be reset',
                HttpStatus::UNPROCESSABLE_ENTITY['code'],
                [
                    'study_year' => ['The selected study year and semester must be before the current semester.'],
                ],
            );
        }

        $resolvedPlan = $this->planResolver->resolve(
            $student,
            (int) $student->curriculum_id,
        );
        $resetCount = $this->generator->resetSemester(
            $studentCode,
            $studyYear,
            $semester,
            $resolvedPlan['courses'],
            $resolvedPlan['study_plan_id'],
        );

        return $this->resetResponse(
            $studentCode,
            $scope,
            $resetCount,
            $studyYear,
            $semester,
        );
    }

    private function isPreviousSemester(
        Student $student,
        int $studyYear,
        int $semester,
    ): bool {
        $currentStudyYear = (int) $student->study_year;
        $currentSemester = (int) $student->study_semester;

        if ($currentStudyYear < 1 || $currentSemester < 1 || $currentSemester > 3) {
            return false;
        }

        return $studyYear < $currentStudyYear
            || ($studyYear === $currentStudyYear && $semester < $currentSemester);
    }

    private function resetResponse(
        string $studentCode,
        string $scope,
        ?int $resetCount,
        ?int $studyYear = null,
        ?int $semester = null,
    ): JsonResponse {
        if ($resetCount === null) {
            return ApiResponse::error(
                'Student grade data not found',
                HttpStatus::NOT_FOUND['code'],
            );
        }

        return ApiResponse::success(
            [
                'student_code' => $studentCode,
                'scope' => $scope,
                'study_year' => $studyYear,
                'semester' => $semester,
                'reset_count' => $resetCount,
            ],
            $resetCount > 0
                ? 'Reset student grades successfully'
                : 'No student grades found for the selected semester',
        );
    }
}
