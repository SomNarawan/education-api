<?php

namespace App\Services\StudyPlans;

use App\Models\Student;
use App\Services\Grades\StudentGradeJsonGenerator;

class StudentStudyPlanService
{
    public function __construct(
        private readonly StudentGradeJsonGenerator $gradeGenerator,
        private readonly StudentStudyPlanStore $store,
    ) {}

    public function fetch(int $studyPlanId): array
    {
        $courses = $this->store->courses($studyPlanId);
        $this->store->categories($studyPlanId);

        return $courses;
    }

    public function save(Student $student, array $studyPlan): void
    {
        $studentCode = trim((string) $student->student_code);

        $this->gradeGenerator->initializeEnrollments(
            $studentCode,
            $studyPlan,
            (int) $student->study_plan_id,
        );
    }

    public function saveAndRecalculate(Student $student, array $studyPlan): void
    {
        $studentCode = trim((string) $student->student_code);

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
