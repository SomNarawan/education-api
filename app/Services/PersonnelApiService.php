<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class PersonnelApiService
{
    public function getTeachers(int $departmentId): array
    {
        $baseUrl = config('services.personnel_api.url');
        $endpoint = config('services.personnel_api.all_user_endpoint');

        if (!$baseUrl || !$endpoint) {
            throw new Exception('Personnel API config is missing');
        }

        // $certPath = storage_path('certs/office-ca.pem');

        // if (!file_exists($certPath)) {
        //     throw new Exception('Certificate file not found: ' . $certPath);
        // }

        $response = Http::withOptions([
            'verify' => false,
        ])
            ->timeout(15)
            ->get(
                $baseUrl . $endpoint,
                [
                    'department_id' => $departmentId,
                ]
            );

        if (!$response->successful()) {
            throw new Exception(
                'Cannot sync teachers from external API. Status: ' . $response->status()
            );
        }

        $teachers = $response->json();

        return $teachers['data'] ?? $teachers ?? [];
    }
}