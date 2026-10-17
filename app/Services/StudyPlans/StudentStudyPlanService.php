<?php

namespace App\Services\StudyPlans;

use App\Contracts\CmisApi;
use App\Models\Student;
use App\Services\Grades\StudentGradeJsonGenerator;
use RuntimeException;

class StudentStudyPlanService
{
    public function __construct(
        private readonly CmisApi $cmisApi,
        private readonly StudentGradeJsonGenerator $gradeGenerator,
        private readonly StudentStudyPlanStore $store,
    ) {}

    public function fetch(int $studyPlanId): array
    {
        if ($studyPlanId < 1) {
            throw new RuntimeException('Student study plan is missing');
        }

        return $this->cmisApi->getCurriculumPlanCourses($studyPlanId);
    }

    public function save(Student $student, array $studyPlan): void
    {
        $this->store->put(
            trim((string) $student->student_code),
            $studyPlan,
        );
    }

    public function saveAndRecalculate(Student $student, array $studyPlan): void
    {
        $studentCode = trim((string) $student->student_code);

        $this->save($student, $studyPlan);
        $this->gradeGenerator->recalculate(
            $studentCode,
            $studyPlan,
            (int) $student->study_plan_id,
        );
    }
}
