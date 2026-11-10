<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class PersonnelApiService
{
    public function getDepartments(): array
    {
        return $this->get('all_department', 'departments');
    }

    public function getFaculties(): array
    {
        return $this->get('all_faculty', 'faculties');
    }

    /**
     * Active lecturers and support staff.
     *
     * @return list<array{nontri_id: string, full_name: string, department_id: int|null}>
     */
    public function getUsers(?int $timeout = null): array
    {
        return collect($this->get('all_user', 'users', $timeout))
            ->map(function (mixed $user): ?array {
                $nontriId = is_array($user) && is_scalar($user['nontri_id'] ?? null) ? trim((string) $user['nontri_id']) : '';
                $fullName = is_array($user) && is_scalar($user['full_name'] ?? null) ? trim((string) $user['full_name']) : '';

                if ($nontriId === '' || $fullName === '') {
                    return null;
                }

                $departmentId = $user['department_id'] ?? null;

                return [
                    'nontri_id' => $nontriId,
                    'full_name' => $fullName,
                    'department_id' => is_numeric($departmentId) ? (int) $departmentId : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function get(string $endpointKey, string $resource, ?int $timeout = null): array
    {
        $url = $this->getApiUrl(config("services.personnel_api.endpoints.{$endpointKey}"));

        $response = Http::withOptions([
            'verify' => (bool) config('services.office_ku.verify_ssl', true),
        ])
            ->timeout(max(
                1,
                $timeout ?? (int) config('services.office_ku.timeout', 30)
            ))
            ->get($url);

        if (! $response->successful()) {
            throw new Exception(
                "Cannot sync {$resource} from external API. Status: {$response->status()}"
            );
        }

        $data = $response->json();

        return $data['data'] ?? $data ?? [];
    }

    private function getApiUrl(mixed $endpoint): string
    {
        $baseUrl = config('services.office_ku.url');
        $basePath = config('services.personnel_api.base_path');

        if (! is_string($baseUrl) || ! is_string($basePath) || ! is_string($endpoint)
            || $baseUrl === '' || $basePath === '' || $endpoint === '') {
            throw new Exception('Personnel API config is missing');
        }

        return implode('/', [
            rtrim($baseUrl, '/'),
            trim($basePath, '/'),
            ltrim($endpoint, '/'),
        ]);
    }
}
