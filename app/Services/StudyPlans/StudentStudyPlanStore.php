<?php

namespace App\Services\StudyPlans;

use App\Services\Storage\LocalJsonFileStore;
use RuntimeException;

class StudentStudyPlanStore
{
    private const DIRECTORY = 'data/study_plan';

    public function __construct(
        private readonly LocalJsonFileStore $jsonFiles,
    ) {}

    public function get(string $studentCode): ?array
    {
        return $this->jsonFiles->read($this->path($studentCode));
    }

    public function put(string $studentCode, array $payload): void
    {
        $this->jsonFiles->write($this->path($studentCode), $payload);
    }

    public function path(string $studentCode): string
    {
        $studentCode = trim($studentCode);

        if ($studentCode === '' || ! ctype_digit($studentCode)) {
            throw new RuntimeException('Student code is invalid');
        }

        return self::DIRECTORY."/{$studentCode}.json";
    }
}
