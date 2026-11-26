<?php

namespace App\Services\StudyPlans;

use App\Contracts\CmisApi;
use App\Services\Storage\LocalJsonFileStore;
use Closure;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class StudentStudyPlanStore
{
    private const DIRECTORY = 'data/study_plans';

    public function __construct(
        private readonly LocalJsonFileStore $jsonFiles,
        private readonly CmisApi $cmisApi,
        private readonly StudyPlanPayload $studyPlanPayload,
    ) {}

    public function getCourses(int $studyPlanId): ?array
    {
        if ($studyPlanId < 1) {
            return null;
        }

        return $this->withLock($studyPlanId, fn (string $directory) => $this->jsonFiles->read("{$directory}/courses.json"));
    }

    public function courses(int $studyPlanId, bool $refresh = false): array
    {
        return $this->remember($studyPlanId, 'courses', $refresh, fn () => $this->fetchCourses($studyPlanId));
    }

    public function categories(int $studyPlanId): array
    {
        return $this->remember($studyPlanId, 'categories', false, fn () => $this->cmisApi->getCurriculumCategories($studyPlanId));
    }

    public function refreshForGradeImport(int $studyPlanId): array
    {
        return $this->withLock($studyPlanId, function (string $directory) use ($studyPlanId): array {
            $courses = $this->fetchCourses($studyPlanId);

            try {
                $categories = $this->cmisApi->getCurriculumCategories($studyPlanId);
            } catch (RuntimeException $exception) {
                throw new RuntimeException("ดึงโครงสร้างหลักสูตรของแผน {$studyPlanId} จาก CMIS ไม่สำเร็จ", previous: $exception);
            }

            $this->jsonFiles->write("{$directory}/categories.json", $categories);
            $this->jsonFiles->write("{$directory}/courses.json", $courses);

            return $courses;
        });
    }

    public function getLegacyCourses(string $studentCode): ?array
    {
        $studentCode = trim($studentCode);

        return ctype_digit($studentCode)
            ? $this->jsonFiles->read("data/study_plan/{$studentCode}.json")
            : null;
    }

    private function remember(int $studyPlanId, string $section, bool $refresh, Closure $load): array
    {
        return $this->withLock($studyPlanId, function (string $directory) use ($section, $refresh, $load): array {
            $path = "{$directory}/{$section}.json";
            $cached = $refresh ? null : $this->jsonFiles->read($path);
            if ($cached !== null) {
                return $cached;
            }

            $payload = $load();
            $this->jsonFiles->write($path, $payload);

            return $payload;
        });
    }

    private function fetchCourses(int $studyPlanId): array
    {
        $payload = $this->cmisApi->getCurriculumPlanCourses($studyPlanId);
        $this->studyPlanPayload->coursesAndTotalCredits($payload, $studyPlanId);

        return $payload;
    }

    private function withLock(int $studyPlanId, Closure $callback): mixed
    {
        if ($studyPlanId < 1) {
            throw new RuntimeException('Student study plan is missing');
        }

        $timeout = max(1, (int) config('cmis.timeout', 15)) * 2;
        $directory = self::DIRECTORY."/{$studyPlanId}";

        return Cache::store('file')->lock("study-plan:{$studyPlanId}", $timeout + 10)
            ->block($timeout + 5, fn () => $callback($directory));
    }
}
