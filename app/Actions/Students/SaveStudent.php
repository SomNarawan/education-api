<?php

namespace App\Actions\Students;

use App\Constants\StudySemester;
use App\Models\Student;
use App\Models\StudentGuardianPhoneHistory;
use App\Services\StudyPlans\StudentStudyPlanService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveStudent
{
    private const MANAGED_ACADEMIC_FIELDS = [
        'gpa',
        'gpax',
        'passed_credits',
        'not_passed_credits',
        'overed_credits',
    ];

    public function __construct(
        private readonly StudentStudyPlanService $studyPlans,
    ) {}

    public function create(array $attributes): Student
    {
        $studyPlan = $this->studyPlans->fetch((int) ($attributes['study_plan_id'] ?? 0));

        return $this->createWithStudyPlan($attributes, $studyPlan);
    }

    public function createFromImport(array $attributes, array $studyPlan): Student
    {
        return $this->createWithStudyPlan($attributes, $studyPlan);
    }

    public function update(
        Student $student,
        array $attributes,
        ?string $updatedBy = null,
    ): Student {
        $studyPlan = $this->requiresStudyPlanRefresh($student, $attributes)
            ? $this->studyPlans->fetch(
                (int) ($attributes['study_plan_id'] ?? $student->study_plan_id),
            )
            : null;

        return $this->updateWithStudyPlan($student, $attributes, $updatedBy, $studyPlan);
    }

    public function updateFromImport(
        Student $student,
        array $attributes,
        string $updatedBy,
        array $studyPlan,
    ): Student {
        return $this->updateWithStudyPlan($student, $attributes, $updatedBy, $studyPlan);
    }

    private function createWithStudyPlan(array $attributes, array $studyPlan): Student
    {
        $attributes = $this->addAcademicStanding($attributes);

        return DB::transaction(function () use ($attributes, $studyPlan): Student {
            $student = new Student;
            $student->fill($attributes);
            $student->save();

            $this->studyPlans->save($student, $studyPlan);

            return $student->refresh();
        });
    }

    private function updateWithStudyPlan(
        Student $student,
        array $attributes,
        ?string $updatedBy,
        ?array $studyPlan,
    ): Student {
        return DB::transaction(function () use (
            $student,
            $attributes,
            $updatedBy,
            $studyPlan,
        ): Student {
            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($student->getKey());

            if (
                array_key_exists('study_year', $attributes) ||
                array_key_exists('study_semester', $attributes)
            ) {
                $attributes = $this->addAcademicStanding($attributes, $student);
            }

            $guardianPhoneChanged = array_key_exists('guardian_phone', $attributes)
                && $student->guardian_phone !== $attributes['guardian_phone'];

            if ($guardianPhoneChanged) {
                StudentGuardianPhoneHistory::query()->create([
                    'student_id' => $student->id,
                    'old_phone' => $student->guardian_phone,
                    'new_phone' => $attributes['guardian_phone'],
                    'updated_by' => $updatedBy,
                    'updated_at' => now(),
                ]);
            }

            $student->update($attributes);

            if ($studyPlan !== null) {
                $this->studyPlans->saveAndRecalculate($student, $studyPlan);
            }

            return $student->refresh();
        });
    }

    private function requiresStudyPlanRefresh(Student $student, array $attributes): bool
    {
        $studyPlanChanged = array_key_exists('study_plan_id', $attributes)
            && (int) $attributes['study_plan_id'] !== (int) $student->study_plan_id;
        $studentCodeChanged = array_key_exists('student_code', $attributes)
            && trim((string) $attributes['student_code']) !== trim((string) $student->student_code);

        return $studyPlanChanged || $studentCodeChanged;
    }

    private function addAcademicStanding(array $attributes, ?Student $student = null): array
    {
        $studyYear = (int) ($attributes['study_year'] ?? $student?->study_year);
        $studySemester = (int) ($attributes['study_semester'] ?? $student?->study_semester);

        return [
            ...Arr::except($attributes, self::MANAGED_ACADEMIC_FIELDS),
            'study_period' => sprintf(
                'ปีที่ %d %s',
                $studyYear,
                StudySemester::nameTh($studySemester),
            ),
        ];
    }
}
