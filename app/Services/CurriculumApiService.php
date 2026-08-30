<?php

namespace App\Services;

use App\Contracts\CurriculumApi;
use Exception;
use Illuminate\Support\Facades\Http;

class CurriculumApiService implements CurriculumApi
{
    private ?array $studyPlans = null;

    public function getStudyPlans(): array
    {
        return $this->studyPlans ??= $this->get('study_plans');
    }

    public function findStudyPlan(int $studyPlanId): ?array
    {
        $studyPlan = collect($this->getStudyPlans())->firstWhere('id', $studyPlanId);

        if ($studyPlan === null) {
            return null;
        }

        return $this->normalizeStudyPlan($studyPlan);
    }

    public function getCurriculumCategories(int $studyPlanId): array
    {
        return $this->get('curriculum_categories', ['study_plan_id' => $studyPlanId]);
    }

    private function get(string $endpointKey, array $query = []): array
    {
        $response = Http::withOptions([
            'verify' => (bool) config('curriculum_api.verify_ssl', true),
        ])->timeout((int) config('curriculum_api.timeout', 15))
            ->get($this->getApiUrl($endpointKey), $query);

        if (! $response->successful()) {
            throw new Exception('Curriculum API request failed');
        }

        return $this->responseData($response->json());
    }

    private function responseData(mixed $payload): array
    {
        $data = is_array($payload) && array_key_exists('data', $payload)
            ? $payload['data']
            : $payload;

        if (! is_array($data)) {
            throw new Exception('Curriculum API returned an invalid response');
        }

        return $data;
    }

    private function normalizeStudyPlan(array $studyPlan): array
    {
        return [
            ...$studyPlan,
            'curriculum_type' => $studyPlan['curriculum_type']
                ?? data_get($studyPlan, 'curriculum.degree_short_th'),
            'required_credits' => $studyPlan['required_credits']
                ?? data_get($studyPlan, 'curriculum.total_credits_min'),
            'department_id' => $studyPlan['department_id']
                ?? data_get($studyPlan, 'curriculum.department_id'),
            'department_name_th' => $studyPlan['department_name_th']
                ?? data_get($studyPlan, 'curriculum.department.name_th'),
        ];
    }

    private function getApiUrl(string $endpointKey): string
    {
        $baseUrl = config('curriculum_api.url');
        $basePath = config('curriculum_api.base_path');
        $endpoint = config('curriculum_api.endpoints.'.$endpointKey);

        if (! is_string($baseUrl) || ! is_string($basePath) || ! is_string($endpoint)
            || $baseUrl === '' || $endpoint === '') {
            throw new Exception('Curriculum API config is missing');
        }

        return implode('/', array_filter([
            rtrim($baseUrl, '/'),
            trim($basePath, '/'),
            ltrim($endpoint, '/'),
        ], fn (string $part) => $part !== ''));
    }
}
