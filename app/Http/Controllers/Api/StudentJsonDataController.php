<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Storage\LocalJsonFileStore;
use App\Services\Students\StudentAcademicOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use JsonException;

class StudentJsonDataController extends Controller
{
    public function __construct(
        private readonly StudentAcademicOverviewService $academicOverview,
        private readonly LocalJsonFileStore $jsonFiles,
    ) {}

    /**
     * API: GET /api/students/{id}/academic-overview
     */
    public function academicOverview(int $id): JsonResponse
    {
        $student = Student::query()->find($id);

        if ($student === null) {
            return ApiResponse::error('Student not found', HttpStatus::NOT_FOUND['code']);
        }

        try {
            $data = $this->academicOverview->get($student);
        } catch (JsonException $exception) {
            return ApiResponse::error(
                'Academic overview JSON data is invalid',
                HttpStatus::INTERNAL_SERVER_ERROR['code'],
                ['section' => $exception->getMessage()],
            );
        }

        return ApiResponse::success($data, 'Load student academic overview successfully');
    }

    /**
     * API: GET /api/students/{id}/unattempted-courses
     */
    public function unattemptedCourses(int $id): JsonResponse
    {
        $student = Student::query()->find($id);

        if ($student === null) {
            return ApiResponse::error('Student not found', HttpStatus::NOT_FOUND['code']);
        }

        try {
            $data = $this->academicOverview->unattemptedCourses($student);
        } catch (JsonException $exception) {
            return ApiResponse::error(
                'Unattempted course JSON data is invalid',
                HttpStatus::INTERNAL_SERVER_ERROR['code'],
                ['section' => $exception->getMessage()],
            );
        }

        return ApiResponse::success($data, 'Load unattempted courses successfully');
    }

    /**
     * API: GET /api/students/{studentCode}/enrollments
     */
    public function enrollments(string $studentCode): JsonResponse
    {
        $path = "data/enrollments/{$studentCode}.json";

        try {
            $enrollment = $this->jsonFiles->read($path);
        } catch (JsonException) {
            return ApiResponse::error(
                'Enrollment JSON data is invalid',
                HttpStatus::INTERNAL_SERVER_ERROR['code']
            );
        }

        if ($enrollment === null) {
            return ApiResponse::error('Enrollment data not found', HttpStatus::NOT_FOUND['code']);
        }

        return ApiResponse::success([
            'student_code' => $studentCode,
            'enrollment' => $enrollment,
        ], 'Load enrollment successfully');
    }

    /**
     * API: GET /api/students/{studentCode}/enrollment-statuses
     */
    public function enrollmentStatuses(string $studentCode): JsonResponse
    {
        return $this->loadBundle(
            $studentCode,
            [
                'enrollment_not_pass' => "data/enrollments_not_pass/{$studentCode}.json",
                'enrollment_pass' => "data/enrollments_pass/{$studentCode}.json",
                'enrollment_over' => "data/enrollments_over/{$studentCode}.json",
            ],
            'Enrollment status data not found',
            'Load enrollment statuses successfully',
        );
    }

    /**
     * API: GET /api/students/{studentCode}/performance-summary
     */
    public function performanceSummary(string $studentCode): JsonResponse
    {
        $groupFiles = array_values(array_filter(
            Storage::disk('local')->files('data/graph/by_group'),
            fn (string $path) => preg_match(
                '/\/'.preg_quote($studentCode, '/').'_(\d+)\.json$/',
                $path,
            ) === 1,
        ));
        usort($groupFiles, 'strnatcasecmp');
        $groupSections = [];

        foreach ($groupFiles as $index => $path) {
            $groupSections['group_'.($index + 1)] = $path;
        }

        return $this->loadBundle(
            $studentCode,
            [
                'by_credit' => "data/graph/by_credit/{$studentCode}.json",
                'by_group' => $groupSections,
                'by_semester' => "data/graph/by_semester/{$studentCode}.json",
            ],
            'Graph data not found',
            'Load graph data successfully',
        );
    }

    /**
     * @param  array<string, string|array<string, string>>  $sections
     */
    private function loadBundle(
        string $studentCode,
        array $sections,
        string $notFoundMessage,
        string $successMessage,
    ): JsonResponse {
        $data = ['student_code' => $studentCode];
        $missingSections = [];
        $loadedFiles = 0;

        try {
            foreach ($sections as $section => $path) {
                if (is_array($path)) {
                    $data[$section] = [];

                    foreach ($path as $childSection => $childPath) {
                        $fullSection = "{$section}.{$childSection}";
                        [$data[$section][$childSection], $loaded] = $this->readOptionalJson(
                            $childPath,
                            $fullSection,
                            $missingSections,
                        );
                        $loadedFiles += (int) $loaded;
                    }

                    continue;
                }

                [$data[$section], $loaded] = $this->readOptionalJson(
                    $path,
                    $section,
                    $missingSections,
                );
                $loadedFiles += (int) $loaded;
            }
        } catch (JsonException $exception) {
            return ApiResponse::error(
                'JSON data is invalid',
                HttpStatus::INTERNAL_SERVER_ERROR['code'],
                ['section' => $exception->getMessage()],
            );
        }

        if ($loadedFiles === 0) {
            return ApiResponse::error($notFoundMessage, HttpStatus::NOT_FOUND['code']);
        }

        $data['missing_sections'] = $missingSections;

        return ApiResponse::success($data, $successMessage);
    }

    /**
     * @param  array<int, string>  $missingSections
     * @return array{0: array<mixed>, 1: bool}
     *
     * @throws JsonException
     */
    private function readOptionalJson(
        string $path,
        string $section,
        array &$missingSections,
    ): array {
        try {
            $payload = $this->jsonFiles->read($path);
        } catch (JsonException) {
            throw new JsonException($section);
        }

        if ($payload === null) {
            $missingSections[] = $section;

            return [[], false];
        }

        return [$payload, true];
    }
}
