<?php

namespace App\Services\Grades;

class StudentCreditSummaryCalculator
{
    public function calculate(array $assignedAggregates, array $overAggregates): array
    {
        $passedCredits = 0.0;
        $notPassedCredits = 0.0;
        $overedCredits = 0.0;

        foreach ($assignedAggregates as $aggregate) {
            if (! $this->countsForCredit($aggregate)) {
                continue;
            }

            if ($aggregate['is_passed']) {
                $passedCredits += (float) $aggregate['credit'];
            } else {
                $notPassedCredits += (float) $aggregate['credit'];
            }
        }

        foreach ($overAggregates as $aggregate) {
            if (! $this->countsForCredit($aggregate)) {
                continue;
            }

            if ($aggregate['is_passed']) {
                $overedCredits += (float) $aggregate['credit'];
            } else {
                $notPassedCredits += (float) $aggregate['credit'];
            }
        }

        return [
            'passed_credits' => $this->number($passedCredits),
            'not_passed_credits' => $this->number($notPassedCredits),
            'overed_credits' => $this->number($overedCredits),
        ];
    }

    private function countsForCredit(array $aggregate): bool
    {
        return ($aggregate['enrollment_type'] ?? null) === 'credit'
            && is_numeric($aggregate['credit'] ?? null);
    }

    private function number(float $value): float|int
    {
        return fmod($value, 1.0) === 0.0 ? (int) $value : round($value, 2);
    }
}
