<?php

namespace App\Actions\Students;

use App\Constants\StudySemester;
use App\Models\Student;
use Illuminate\Support\Arr;

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

    public function update(Student $student, array $attributes): Student
    {
        if (
            array_key_exists('study_year', $attributes) ||
            array_key_exists('study_semester', $attributes)
        ) {
            $attributes = $this->addAcademicStanding($attributes, $student);
        }

        $student->update($attributes);

        return $student->refresh();
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
