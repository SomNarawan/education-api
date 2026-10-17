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
        $studentCode = trim((string) $student->student_code);

        $this->store->put($studentCode, $studyPlan);
        $this->gradeGenerator->initializeEnrollments(
            $studentCode,
            $studyPlan,
            (int) $student->study_plan_id,
        );
    }

    public function saveAndRecalculate(Student $student, array $studyPlan): void
    {
        $studentCode = trim((string) $student->student_code);

        $this->store->put($studentCode, $studyPlan);
        $recalculated = $this->gradeGenerator->recalculate(
            $studentCode,
            $studyPlan,
            (int) $student->study_plan_id,
        );

        if (! $recalculated) {
            $this->gradeGenerator->initializeEnrollments(
                $studentCode,
                $studyPlan,
                (int) $student->study_plan_id,
            );
        }
    }
}
