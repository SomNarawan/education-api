<?php

namespace App\Actions\Students;

use App\Models\Student;
use App\Services\Students\AcademicStandingCalculator;
use App\Services\Students\StudentDepartmentResolver;
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

    public function __construct(
        private readonly StudentDepartmentResolver $departmentResolver,
        private readonly AcademicStandingCalculator $standingCalculator,
    ) {}

    public function create(array $attributes): Student
    {
        $attributes['system_department_id'] = $this->departmentResolver->resolve($attributes);
        $attributes = $this->addAcademicStanding($attributes);

        $student = new Student;
        $student->fill($this->databaseAttributes($attributes));
        $student->save();

        return $student->refresh();
    }

    public function update(Student $student, array $attributes): Student
    {
        if (array_key_exists('department_id', $attributes)) {
            $attributes['system_department_id'] = $attributes['department_id'];
        } elseif (array_key_exists('teacher_id', $attributes) && $attributes['teacher_id'] !== null) {
            $attributes['system_department_id'] = $this->departmentResolver->resolve($attributes + [
                'study_plan_id' => $student->study_plan_id,
            ]);
        } elseif (
            array_key_exists('study_plan_id', $attributes)
            && (int) $attributes['study_plan_id'] !== (int) $student->study_plan_id
        ) {
            $attributes['system_department_id'] = $this->departmentResolver->resolve($attributes);
        }

        if (array_key_exists('entry_year', $attributes)) {
            $attributes = $this->addAcademicStanding($attributes);
        }

        $student->update($this->databaseAttributes($attributes));

        return $student->refresh();
    }

    private function addAcademicStanding(array $attributes): array
    {
        return $attributes + $this->standingCalculator->calculate((int) $attributes['entry_year']);
    }

    private function databaseAttributes(array $attributes): array
    {
        return Arr::except($attributes, [
            'department_id',
            ...self::MANAGED_ACADEMIC_FIELDS,
        ]);
    }
}
