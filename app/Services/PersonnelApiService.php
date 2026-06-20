<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use App\Models\DepartmentMap;

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

        // map internal department id to external id; require mapping exists
        $departmentMap = DepartmentMap::query()
            ->where('id_in', $departmentId)
            ->first();

        if (!$departmentMap) {
            throw new Exception('Department mapping not found for id: ' . $departmentId);
        }

        $outDeptId = $departmentMap->id_out;

        $response = Http::withOptions([
            'verify' => false,
        ])
            ->timeout(15)
            ->get(
                $baseUrl . $endpoint,
                [
                    'department_id' => $outDeptId,
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