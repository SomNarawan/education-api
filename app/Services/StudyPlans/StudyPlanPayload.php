<?php

namespace App\Services\StudyPlans;

use RuntimeException;

class StudyPlanPayload
{
    public function selected(array $payload, int $studyPlanId): array
    {
        $plans = array_is_list($payload) ? $payload : [$payload];

        foreach ($plans as $plan) {
            if (is_array($plan)
                && (int) ($plan['curriculum_plan_id'] ?? 0) === $studyPlanId) {
                return $plan;
            }
        }

        return count($plans) === 1 && is_array($plans[0] ?? null)
            ? $plans[0]
            : [];
    }

    /**
     * @return array{0: array<int, array>, 1: float}
     */
    public function coursesAndTotalCredits(array $payload, int $studyPlanId): array
    {
        $plan = $this->selected($payload, $studyPlanId);

        if (! isset($plan['data']) || ! is_array($plan['data'])) {
            throw new RuntimeException('ไม่พบข้อมูลรายวิชาในแผนการเรียนที่เลือก');
        }

        return [
            array_values(array_filter($plan['data'], 'is_array')),
            (float) ($plan['total_credits_min'] ?? 0),
        ];
    }
}
