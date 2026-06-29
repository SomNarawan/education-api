<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class PersonnelApiService
{
    private function getApiUrl(string $endpoint): string
    {
        $baseUrl = config('services.personnel_api.url');
        $basePath = config('services.personnel_api.base_path');

        if (!$baseUrl || !$basePath || !$endpoint) {
            throw new Exception('Personnel API config is missing');
        }

        return rtrim($baseUrl, '/')
            . '/'
            . trim($basePath, '/')
            . '/'
            . ltrim($endpoint, '/');
    }

    public function getTeachers(int $departmentId): array
    {
        $endpoint = config('services.personnel_api.endpoints.all_user');
        $url = $this->getApiUrl($endpoint);

        $response = Http::withOptions([
            'verify' => false,
        ])
            ->timeout(15)
            ->get($url, [
                'department_id' => $departmentId,
            ]);

        if (!$response->successful()) {
            throw new Exception(
                'Cannot sync teachers from external API. Status: ' . $response->status()
            );
        }

        $teachers = $response->json();

        return $teachers['data'] ?? $teachers ?? [];
    }

    public function getDepartments(int $facultyId): array
    {
        $endpoint = config('services.personnel_api.endpoints.all_department');
        $url = $this->getApiUrl($endpoint);

        $response = Http::withOptions([
            'verify' => false,
        ])
            ->timeout(15)
            ->get($url, [
                'faculty_id' => $facultyId,
            ]);

        if (!$response->successful()) {
            throw new Exception(
                'Cannot sync departments from external API. Status: ' . $response->status()
            );
        }

        $departments = $response->json();

        return $departments['data'] ?? $departments ?? [];
    }

    public function getFaculties(): array
    {
        $endpoint = config('services.personnel_api.endpoints.all_faculty');
        $url = $this->getApiUrl($endpoint);

        $response = Http::withOptions([
            'verify' => false,
        ])
            ->timeout(15)
            ->get($url);

        if (!$response->successful()) {
            throw new Exception(
                'Cannot sync faculties from external API. Status: ' . $response->status()
            );
        }

        $faculties = $response->json();

        return $faculties['data'] ?? $faculties ?? [];
    }
}