<?php

namespace App\Services\Grades;

use App\Models\Student;
use App\Services\StudyPlans\StudentStudyPlanStore;
use Illuminate\Contracts\Cache\LockTimeoutException;
use RuntimeException;

class StudentGradePlanResolver
{
    /** @var array<int, array|RuntimeException|LockTimeoutException> */
    private array $importPlans = [];

    public function __construct(private readonly StudentStudyPlanStore $studyPlans) {}

    public function resolve(?Student $student, int $curriculumId, bool $forGradeImport = false): array
    {
        if ($student === null) {
            throw new RuntimeException('ไม่พบข้อมูลนิสิตในระบบ');
        }

        if ((int) $student->curriculum_id !== $curriculumId) {
            throw new RuntimeException('นิสิตไม่ได้อยู่ในหลักสูตรที่เลือก');
        }

        $studyPlanId = (int) $student->study_plan_id;

        if ($studyPlanId < 1) {
            throw new RuntimeException('นิสิตยังไม่มีข้อมูลแผนการเรียน');
        }

        return [
            'study_plan_id' => $studyPlanId,
            'courses' => $forGradeImport
                ? $this->importCourses($studyPlanId)
                : $this->studyPlans->courses($studyPlanId, refresh: true),
        ];
    }

    private function importCourses(int $studyPlanId): array
    {
        if (! isset($this->importPlans[$studyPlanId])) {
            try {
                $this->importPlans[$studyPlanId] = $this->studyPlans->refreshForGradeImport($studyPlanId);
            } catch (RuntimeException|LockTimeoutException $exception) {
                $this->importPlans[$studyPlanId] = $exception;
            }
        }

        $result = $this->importPlans[$studyPlanId];

        if ($result instanceof RuntimeException || $result instanceof LockTimeoutException) {
            throw $result;
        }

        return $result;
    }
}
