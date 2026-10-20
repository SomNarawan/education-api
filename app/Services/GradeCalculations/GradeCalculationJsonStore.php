<?php

namespace App\Services\GradeCalculations;

use App\Services\Storage\LocalJsonFileStore;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GradeCalculationJsonStore
{
    private const DIRECTORY = 'data/grade_calculations';

    public function __construct(
        private readonly LocalJsonFileStore $jsonFiles,
    ) {}

    public function history(?string $studentCode = null): array
    {
        $directory = $studentCode === null
            ? self::DIRECTORY
            : $this->studentDirectory($studentCode);
        $calculations = [];

        foreach (Storage::disk('local')->allFiles($directory) as $path) {
            if (! str_ends_with($path, '.json')) {
                continue;
            }

            $calculation = $this->jsonFiles->read($path);

            if ($calculation !== null) {
                // Older JSON files do not contain the previous term's GPA.
                $calculation += ['current_gpa' => null];
                $calculations[] = $calculation;
            }
        }

        usort($calculations, $this->compareNewestFirst(...));

        return $calculations;
    }

    public function latest(string $studentCode): ?array
    {
        return $this->history($studentCode)[0] ?? null;
    }

    public function append(string $studentCode, array $attributes): array
    {
        $directory = $this->studentDirectory($studentCode);
        $disk = Storage::disk('local');
        $createdAt = now();
        $id = (int) $createdAt->format('Uu');
        $path = $directory.'/'.$id.'.json';

        while ($disk->exists($path)) {
            usleep(1);
            $createdAt = now();
            $id = (int) $createdAt->format('Uu');
            $path = $directory.'/'.$id.'.json';
        }

        $calculation = [
            'id' => $id,
            ...$attributes,
            'created_at' => $createdAt->toISOString(),
        ];
        $this->jsonFiles->write($path, $calculation);

        return $calculation;
    }

    private function studentDirectory(string $studentCode): string
    {
        $studentCode = trim($studentCode);

        if ($studentCode === '' || ! ctype_digit($studentCode)) {
            throw new RuntimeException('Student code is invalid');
        }

        return self::DIRECTORY.'/'.$studentCode;
    }

    private function compareNewestFirst(array $left, array $right): int
    {
        $createdAtComparison = strcmp(
            (string) ($right['created_at'] ?? ''),
            (string) ($left['created_at'] ?? '')
        );

        return $createdAtComparison !== 0
            ? $createdAtComparison
            : ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
    }
}
