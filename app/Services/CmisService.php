<?php

namespace App\Services;

use App\Contracts\CmisApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CmisService implements CmisApi
{
    public function getCurriculums(): array
    {
        return $this->get('curriculums', [])['data'];
    }

    public function getCurriculumCategories(int $studyPlanId): array
    {
        return $this->get('curriculum_categories', [
            'study_plan_id' => $studyPlanId,
        ]);
    }

    public function getCurriculumPlans(int $curriculumId): array
    {
        return $this->get('curriculum_plans', [
            'curriculums_id' => $curriculumId,
        ])['data'];
    }

    public function findStudyPlan(int $studyPlanId): ?array
    {
        $payload = $this->getCurriculumCategories($studyPlanId);
        $studyPlan = data_get($payload, 'meta.study_plan');

        if (! is_array($studyPlan)) {
            return null;
        }

        $curriculum = data_get($payload, 'meta.curriculum');

        return $this->normalizeStudyPlan(
            $studyPlan,
            is_array($curriculum) ? $curriculum : [],
        );
    }

    public function getCurriculumPersonnel(int $curriculumId): array
    {
        return $this->get('curriculum_personnel', [
            'curriculum_id' => $curriculumId,
        ])['data'];
    }

    private function get(string $endpointKey, array $query): array
    {
        try {
            $response = Http::withOptions([
                'verify' => (bool) config('cmis.verify_ssl', true),
            ])->acceptJson()
                ->timeout((int) config('cmis.timeout', 15))
                ->get($this->apiUrl($endpointKey), $query);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to connect to the CMIS service.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('CMIS service request failed with status '.$response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)
            || ! array_key_exists('data', $payload)
            || ! is_array($payload['data'])
            || (array_key_exists('meta', $payload) && ! is_array($payload['meta']))) {
            throw new RuntimeException('CMIS service returned an invalid response');
        }

        return $payload;
    }

    private function normalizeStudyPlan(array $studyPlan, array $curriculum): array
    {
        return [
            ...$studyPlan,
            'curriculum_id' => $studyPlan['curriculum_id']
                ?? $curriculum['id']
                ?? null,
            'curriculum_type' => $studyPlan['curriculum_type']
                ?? $curriculum['degree_short_th']
                ?? null,
            'required_credits' => $studyPlan['required_credits']
                ?? $curriculum['total_credits_min']
                ?? null,
            'department_id' => $studyPlan['department_id']
                ?? $curriculum['department_id']
                ?? data_get($curriculum, 'department.id'),
            'department_name_th' => $studyPlan['department_name_th']
                ?? data_get($curriculum, 'department.name_th'),
        ];
    }

    private function apiUrl(string $endpointKey): string
    {
        $baseUrl = config('cmis.url');
        $basePath = config('cmis.base_path');
        $endpoint = config('cmis.endpoints.'.$endpointKey);

        if (! is_string($baseUrl) || ! is_string($basePath) || ! is_string($endpoint)
            || $baseUrl === '' || $endpoint === '') {
            throw new RuntimeException('CMIS service config is missing');
        }

        return implode('/', array_filter([
            rtrim($baseUrl, '/'),
            trim($basePath, '/'),
            ltrim($endpoint, '/'),
        ], fn (string $part) => $part !== ''));
    }
}
