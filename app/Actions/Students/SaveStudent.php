<?php

namespace App\Actions\Students;

use App\Constants\StudySemester;
use App\Models\Student;
use App\Models\StudentGuardianPhoneHistory;
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

    public function create(array $attributes): Student
    {
        $attributes = $this->addAcademicStanding($attributes);

        $student = new Student;
        $student->fill($attributes);
        $student->save();

        return $student->refresh();
    }

    public function update(Student $student, array $attributes, ?string $updatedBy = null): Student
    {
        return DB::transaction(function () use ($student, $attributes, $updatedBy): Student {
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

            return $student->refresh();
        });
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
