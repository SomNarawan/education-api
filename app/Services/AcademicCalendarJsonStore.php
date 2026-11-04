<?php

namespace App\Services;

use App\Services\Storage\LocalJsonFileStore;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AcademicCalendarJsonStore
{
    private const DIRECTORY = 'data/academic_calendars';

    public function __construct(
        private readonly LocalJsonFileStore $jsonFiles,
    ) {}

    /**
     * @return array<int, int>
     */
    public function years(): array
    {
        $years = [];

        foreach (Storage::disk('local')->files(self::DIRECTORY) as $path) {
            if (preg_match('#^'.preg_quote(self::DIRECTORY, '#').'/([0-9]{4})\.json$#', $path, $matches) !== 1) {
                continue;
            }

            $academicYear = (int) $matches[1];

            if ($this->isValidAcademicYear($academicYear)) {
                $years[] = $academicYear;
            }
        }

        rsort($years, SORT_NUMERIC);

        return array_values(array_unique($years));
    }

    public function get(int $academicYear): ?array
    {
        return $this->jsonFiles->read($this->path($academicYear));
    }

    /**
     * @return array<int, array{academic_year: int, payload: array}>
     */
    public function all(): array
    {
        $calendars = [];

        foreach ($this->years() as $academicYear) {
            $payload = $this->get($academicYear);

            if ($payload !== null) {
                $calendars[] = [
                    'academic_year' => $academicYear,
                    'payload' => $payload,
                ];
            }
        }

        return $calendars;
    }

    public function put(int $academicYear, array $payload): void
    {
        $this->jsonFiles->write($this->path($academicYear), $payload);
    }

    public function path(int $academicYear): string
    {
        if (! $this->isValidAcademicYear($academicYear)) {
            throw new RuntimeException('Academic year must be between 2000 and 2100.');
        }

        return self::DIRECTORY."/{$academicYear}.json";
    }

    private function isValidAcademicYear(int $academicYear): bool
    {
        return $academicYear >= 2000 && $academicYear <= 2100;
    }
}
