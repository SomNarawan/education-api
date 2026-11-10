<?php

namespace App\Actions\Students;

use App\Constants\Status;
use App\Models\Student;
use App\Models\StudentGuardianPhoneHistory;
use App\Models\StudentStatus;
use App\Services\StudentAcademicPeriods\AcademicStandingCalculator;
use App\Services\StudentAcademicPeriodSyncService;
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
        private readonly StudentAcademicPeriodSyncService $academicPeriods,
        private readonly AcademicStandingCalculator $standingCalculator,
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
        return $this->updateWithStudyPlan(
            $student,
            $attributes,
            $updatedBy,
            $studyPlan,
            true,
        );
    }

    private function createWithStudyPlan(array $attributes, array $studyPlan): Student
    {
        $attributes = $this->isStudyingStatus(
            (int) ($attributes['student_status_id'] ?? 0),
        )
            ? $this->addCurrentAcademicStanding($attributes)
            : $this->clearAcademicStanding($attributes);

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
        bool $refreshCurrentStanding = false,
    ): Student {
        return DB::transaction(function () use (
            $student,
            $attributes,
            $updatedBy,
            $studyPlan,
            $refreshCurrentStanding,
        ): Student {
            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($student->getKey());

            $statusId = (int) ($attributes['student_status_id'] ?? $student->student_status_id);
            $statusChanged = array_key_exists('student_status_id', $attributes)
                && $statusId !== (int) $student->student_status_id;

            if (! $this->isStudyingStatus($statusId)) {
                $attributes = $this->clearAcademicStanding($attributes);
            } elseif (
                $refreshCurrentStanding
                || $statusChanged
                || $this->requiresCurrentStandingRefresh($student, $attributes)
            ) {
                $attributes = $this->addCurrentAcademicStanding([
                    ...$student->only(['entry_year', 'is_international']),
                    ...$attributes,
                ]);
            } elseif (
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

    private function requiresCurrentStandingRefresh(Student $student, array $attributes): bool
    {
        $entryYearChanged = array_key_exists('entry_year', $attributes)
            && (int) $attributes['entry_year'] !== (int) $student->entry_year;
        $calendarTypeChanged = array_key_exists('is_international', $attributes)
            && (bool) $attributes['is_international'] !== (bool) $student->is_international;

        return $entryYearChanged || $calendarTypeChanged;
    }

    private function isStudyingStatus(int $statusId): bool
    {
        return StudentStatus::query()
            ->whereKey($statusId)
            ->value('status_name') === Status::STUDYING;
    }

    private function clearAcademicStanding(array $attributes): array
    {
        return [
            ...Arr::except($attributes, ['study_year', 'study_semester', 'study_period']),
            'study_year' => null,
            'study_semester' => null,
            'study_period' => null,
        ];
    }

    private function addAcademicStanding(array $attributes, ?Student $student = null): array
    {
        $studyYear = (int) ($attributes['study_year'] ?? $student?->study_year);
        $studySemester = (int) ($attributes['study_semester'] ?? $student?->study_semester);

        return [
            ...Arr::except($attributes, self::MANAGED_ACADEMIC_FIELDS),
            'study_period' => $this->standingCalculator->studyPeriod(
                $studyYear,
                $studySemester,
            ),
        ];
    }

    private function addCurrentAcademicStanding(array $attributes): array
    {
        $standing = $this->academicPeriods->currentStandingForEntryYear(
            (int) ($attributes['entry_year'] ?? 0),
            (bool) ($attributes['is_international'] ?? false),
        );

        return $this->addAcademicStanding([
            ...Arr::except($attributes, ['study_year', 'study_semester', 'study_period']),
            ...$standing,
        ]);
    }
}
