<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use JsonException;

class StudentJsonDataController extends Controller
{
    /**
     * API: GET /api/students/json-data/enrollment/{studentCode}
     */
    public function enrollment(string $studentCode): JsonResponse
    {
        $path = "data/enrollments/{$studentCode}.json";

        if (! Storage::disk('local')->exists($path)) {
            return ApiResponse::error('Enrollment data not found', 404);
        }

        try {
            $enrollment = $this->readJson($path);
        } catch (JsonException) {
            return ApiResponse::error('Enrollment JSON data is invalid', 500);
        }

        return ApiResponse::success([
            'student_code' => $studentCode,
            'enrollment' => $enrollment,
        ], 'Load enrollment successfully');
    }

    /**
     * API: GET /api/students/json-data/enrollment-statuses/{studentCode}
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
     * API: GET /api/students/json-data/graphs/{studentCode}
     */
    public function graphs(string $studentCode): JsonResponse
    {
        return $this->loadBundle(
            $studentCode,
            [
                'by_credit' => "data/graph/by_credit/{$studentCode}.json",
                'by_group' => [
                    'group_1' => "data/graph/by_group/{$studentCode}_1.json",
                    'group_2' => "data/graph/by_group/{$studentCode}_2.json",
                    'group_3' => "data/graph/by_group/{$studentCode}_3.json",
                ],
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
                500,
                ['section' => $exception->getMessage()],
            );
        }

        if ($loadedFiles === 0) {
            return ApiResponse::error($notFoundMessage, 404);
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
        if (! Storage::disk('local')->exists($path)) {
            $missingSections[] = $section;

            return [[], false];
        }

        try {
            return [$this->readJson($path), true];
        } catch (JsonException) {
            throw new JsonException($section);
        }
    }

    /**
     * @return array<mixed>
     *
     * @throws JsonException
     */
    private function readJson(string $path): array
    {
        return json_decode(
            Storage::disk('local')->get($path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
