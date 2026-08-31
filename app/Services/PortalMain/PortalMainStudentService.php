<?php

namespace App\Services\PortalMain;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalMainStudentService
{
    public function nontriIdExists(string $nontriId): bool
    {
        return Student::query()->where('student_code', $this->normalizeNontriId($nontriId))->exists();
    }

    public function findByNontriId(string $nontriId): ?Student
    {
        return $this->baseQuery()->where('student_code', $this->normalizeNontriId($nontriId))->first();
    }

    /**
     * @param  array<int, string>  $nontriIds
     * @return array<string, Student> students found, keyed by the original requested nontriId
     */
    public function findManyByNontriIds(array $nontriIds): array
    {
        $originalByCode = [];
        foreach ($nontriIds as $nontriId) {
            $originalByCode[$this->normalizeNontriId($nontriId)] = $nontriId;
        }

        $students = $this->baseQuery()->whereIn('student_code', array_keys($originalByCode))->get();

        $result = [];
        foreach ($students as $student) {
            $result[$originalByCode[$student->student_code]] = $student;
        }

        return $result;
    }

    public function searchNontriIdsByAnyKeyword(string $keyword): array
    {
        $pattern = "%{$keyword}%";

        return Student::query()
            ->where(function (Builder $query) use ($pattern): void {
                $query
                    ->where('student_code', 'like', $pattern)
                    ->orWhere('first_name_th', 'like', $pattern)
                    ->orWhere('last_name_th', 'like', $pattern)
                    ->orWhere('first_name_en', 'like', $pattern)
                    ->orWhere('last_name_en', 'like', $pattern)
                    ->orWhere('email', 'like', $pattern);
            })
            ->orderBy('student_code')
            ->pluck('student_code')
            ->all();
    }

    public function searchNontriIdsByFields(?string $nontriId, ?string $fullName, ?string $agency): array
    {
        $query = Student::query();

        if ($nontriId !== null && $nontriId !== '') {
            $query->where('student_code', 'like', "%{$nontriId}%");
        }

        if ($fullName !== null && trim($fullName) !== '') {
            foreach (preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) as $token) {
                $pattern = "%{$token}%";
                $query->where(function (Builder $subQuery) use ($pattern): void {
                    $subQuery
                        ->where('first_name_th', 'like', $pattern)
                        ->orWhere('last_name_th', 'like', $pattern)
                        ->orWhere('first_name_en', 'like', $pattern)
                        ->orWhere('last_name_en', 'like', $pattern);
                });
            }
        }

        if ($agency !== null && $agency !== '') {
            $pattern = "%{$agency}%";
            $query->whereHas(
                'systemDepartment',
                fn (Builder $departmentQuery) => $departmentQuery
                    ->withInactive()
                    ->where('th_name', 'like', $pattern)
                    ->orWhere('en_name', 'like', $pattern)
            );
        }

        return $query->orderBy('student_code')->pluck('student_code')->all();
    }

    /**
     * nontri_id may carry a leading letter prefix (e.g. "b6020501361"),
     * while student_code is stored as the plain numeric code.
     */
    private function normalizeNontriId(string $nontriId): string
    {
        return preg_replace('/^[^0-9]+/', '', $nontriId);
    }

    private function baseQuery(): Builder
    {
        // withInactive() bypasses SystemDepartment's active-status global scope, which
        // queries a `status` column that does not exist on the real system_departments table.
        return Student::query()->with(['systemDepartment' => fn (BelongsTo $query) => $query->withInactive()]);
    }
}
