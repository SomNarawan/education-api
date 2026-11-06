<?php

namespace App\Services\StudentAcademicPeriods;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EligibleStudentQuery
{
    public function builder(?bool $isInternational = null): Builder
    {
        return Student::query()
            ->studying()
            ->when(
                $isInternational !== null,
                fn (Builder $query): Builder => $query->where(
                    'is_international',
                    $isInternational,
                ),
            );
    }

    public function get(?bool $isInternational = null): Collection
    {
        return $this->builder($isInternational)
            ->orderByDesc('entry_year')
            ->orderBy('id')
            ->get([
                'id',
                'is_international',
                'entry_year',
                'study_year',
                'study_semester',
                'study_period',
            ]);
    }
}
