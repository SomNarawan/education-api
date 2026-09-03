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

    private function get(string $endpointKey, string $resource): array
    {
        $url = $this->getApiUrl(config("services.personnel_api.endpoints.{$endpointKey}"));

        $response = Http::withOptions([
            'verify' => false,
        ])
            ->timeout(15)
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
        $baseUrl = config('services.personnel_api.url');
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
