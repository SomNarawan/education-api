<?php

namespace App\Services\StudentAcademicPeriods;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EligibleStudentQuery
{
    private const CURRICULUM_CODES = [
        'CURR-KPS-ENG-COMP-2565',
    ];

    public function builder(): Builder
    {
        return Student::query()
            ->studying()
            ->whereIn('curriculum_code', self::CURRICULUM_CODES);
    }

    public function get(): Collection
    {
        return $this->builder()
            ->orderByDesc('entry_year')
            ->orderBy('id')
            ->get([
                'id',
                'entry_year',
                'study_year',
                'study_semester',
                'study_period',
            ]);
    }
}
