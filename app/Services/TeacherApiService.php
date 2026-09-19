<?php

namespace App\Services;

use App\Contracts\TeacherApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TeacherApiService implements TeacherApi
{
    public function getTeachers(): array
    {
        return $this->get();
    }

    public function findTeacher(int $teacherId): ?array
    {
        return collect($this->getTeachers())->firstWhere('id', $teacherId);
    }

    public function findTeacherByNontriId(string $nontriId): ?array
    {
        return collect($this->getTeachers())
            ->firstWhere('nontri_id', $nontriId);
    }

    private function get(): array
    {
        try {
            $response = Http::withOptions([
                'verify' => (bool) config('cmis.verify_ssl', true),
            ])->acceptJson()
                ->timeout((int) config('cmis.timeout', 15))
                ->get($this->apiUrl());
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to connect to the Teacher API.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Teacher API request failed with status '.$response->status());
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['data']) || ! is_array($payload['data'])) {
            throw new RuntimeException('Teacher API returned an invalid response');
        }

        return collect($payload['data'])
            ->filter(fn (mixed $teacher): bool => is_array($teacher))
            ->map(fn (array $teacher): ?array => $this->normalizeTeacher($teacher))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeTeacher(array $teacher): ?array
    {
        $id = $teacher['personnel_id'] ?? null;
        $nontriId = $teacher['external_id'] ?? null;
        $fullName = $teacher['full_name'] ?? null;

        if (! is_numeric($id)
            || ! is_scalar($nontriId) || trim((string) $nontriId) === ''
            || ! is_scalar($fullName) || trim((string) $fullName) === '') {
            return null;
        }

        return [
            'id' => (int) $id,
            'nontri_id' => (string) $nontriId,
            'full_name_th' => (string) $fullName,
            'department_name' => isset($teacher['department_name']) && is_scalar($teacher['department_name'])
                ? (string) $teacher['department_name']
                : null,
            'roles' => is_array($teacher['roles'] ?? null)
                ? array_values($teacher['roles'])
                : [],
        ];
    }

    private function apiUrl(): string
    {
        $baseUrl = config('cmis.url');
        $basePath = config('cmis.base_path');
        $endpoint = config('cmis.endpoints.teachers');

        if (! is_string($baseUrl) || ! is_string($basePath) || ! is_string($endpoint)
            || $baseUrl === '' || $endpoint === '') {
            throw new RuntimeException('Teacher API config is missing');
        }

        return implode('/', array_filter([
            rtrim($baseUrl, '/'),
            trim($basePath, '/'),
            ltrim($endpoint, '/'),
        ], fn (string $part): bool => $part !== ''));
    }
}
