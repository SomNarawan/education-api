<?php

namespace App\Services\PortalMain;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalMainStudentService
{
    public const MAX_SEARCH_RESULTS = 100;

    private const NAME_COLUMNS = ['first_name_th', 'last_name_th', 'first_name_en', 'last_name_en'];

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

    /**
     * Every word of the keyword must match the student code, a Thai or English
     * name, or the email, so "สมชาย ใจดี" finds the student named สมชาย ใจดี.
     */
    public function searchNontriIdsByAnyKeyword(string $keyword): array
    {
        $query = Student::query();

        foreach ($this->words($keyword) as $word) {
            $this->whereAnyLike($query, ['student_code', ...self::NAME_COLUMNS, 'email'], $word);
        }

        return $this->pluckNontriIds($query);
    }

    public function searchNontriIdsByFields(?string $nontriId, ?string $fullName, ?string $agency): array
    {
        $query = Student::query();

        if ($nontriId !== null && $nontriId !== '') {
            $this->whereAnyLike($query, ['student_code'], $nontriId);
        }

        foreach ($this->words($fullName ?? '') as $word) {
            $this->whereAnyLike($query, self::NAME_COLUMNS, $word);
        }

        if ($agency !== null && $agency !== '') {
            $query->whereHas(
                'systemDepartment',
                fn (Builder $departmentQuery) => $this->whereAnyLike(
                    $departmentQuery->withInactive(),
                    ['th_name', 'en_name'],
                    $agency,
                )
            );
        }

        return $this->pluckNontriIds($query);
    }

    /**
     * @return array<int, string>
     */
    private function words(string $text): array
    {
        return preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Adds "any of the columns contains the value". `%` and `_` in the value
     * match literally; `!` is the escape character because both MySQL and
     * SQLite accept it in an ESCAPE clause.
     *
     * @param  array<int, string>  $columns
     */
    private function whereAnyLike(Builder $query, array $columns, string $value): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';

        return $query->where(function (Builder $subQuery) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $subQuery->orWhereRaw("{$column} like ? escape '!'", [$pattern]);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function pluckNontriIds(Builder $query): array
    {
        return $query
            ->orderBy('student_code')
            ->limit(self::MAX_SEARCH_RESULTS)
            ->pluck('student_code')
            ->all();
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
