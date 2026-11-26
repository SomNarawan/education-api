<?php

namespace App\Services\Students;

use App\Contracts\CmisApi;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** Units for the advisor report. The legacy CMIS course list calls every placement "credit". */
class AdvisorPlanUnits
{
    private array $plans = [];

    private array $offlinePlans = [];

    public bool $lookupFailed = false;

    public function __construct(private readonly CmisApi $cmis) {}

    public static function normalize(mixed $unit): ?string
    {
        return match (strtolower(trim((string) $unit))) {
            'credit', 'credits' => 'credit',
            'course', 'courses' => 'course',
            'hour', 'hours' => 'hour',
            'day', 'days' => 'day',
            'week', 'weeks' => 'week',
            'audit' => 'audit',
            default => null,
        };
    }

    public function forSlot(array $slot, int $planId, bool &$curriculumUnavailable = false): ?string
    {
        $explicit = $slot['effective_unit'] ?? $slot['requirement_unit'] ?? null;
        if ($explicit !== null) {
            return self::normalize($explicit);
        }
        $node = $slot['curriculum_structure_node_id'] ?? null;
        $stored = self::normalize($slot['enrollment_type'] ?? 'credit');
        if ($node === null || ! empty($slot['subject_id']) || $stored !== 'credit') {
            return $stored;
        }
        if ($planId <= 0) {
            return null;
        }
        if (! array_key_exists($planId, $this->plans)) {
            $snapshotKey = "advisor-dashboard:plan-units:last-success:v1:$planId";
            try {
                $this->plans[$planId] = $this->fetchUnits($planId);
                if (Cache::get($snapshotKey) !== $this->plans[$planId]) {
                    Cache::forever($snapshotKey, $this->plans[$planId]);
                }
            } catch (RuntimeException) {
                $this->lookupFailed = true;
                $this->offlinePlans[$planId] = true;
                $this->plans[$planId] = Cache::get($snapshotKey) ?? Cache::get("advisor-dashboard:plan-units:v1:$planId", []);
            }
        }

        if (isset($this->offlinePlans[$planId]) && ! array_key_exists($node, $this->plans[$planId])) {
            $curriculumUnavailable = true;
        }

        return $this->plans[$planId][$node] ?? null;
    }

    private function fetchUnits(int $planId): array
    {
        $tree = $this->cmis->getCurriculumCategories($planId);
        if (! is_array($tree['data'] ?? null) || $tree['data'] === []) {
            throw new RuntimeException('No curriculum structure for advisor report');
        }
        $units = [];
        $nodes = $tree['data'];
        while ($nodes !== []) {
            $item = array_pop($nodes);
            $id = $item['curriculum_structure_node_id'] ?? null;
            if ($id !== null && empty($item['is_pool_subject'])) {
                $units[$id] = self::normalize($item['requirement_unit'] ?? $item['children_total_unit'] ?? null);
            }
            foreach ($item['children'] ?? [] as $child) {
                $nodes[] = $child;
            }
        }

        if ($units === []) {
            throw new RuntimeException('No curriculum nodes for advisor report');
        }

        return $units;
    }
}
