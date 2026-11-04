<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

class AcademicCalendarProviderService
{
    public function getAcademicCalendar(int $academicYear): array
    {
        $response = Http::withOptions([
            'verify' => (bool) config('services.office_ku.verify_ssl', true),
        ])
            ->acceptJson()
            ->timeout(max(
                1,
                (int) config('services.office_ku.timeout', 30)
            ))
            ->get($this->url(), [
                'academicYear' => $academicYear,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Cannot sync academic calendar for {$academicYear}. Provider status: {$response->status()}"
            );
        }

        $data = $response->json('data');

        if (
            ! is_array($data) ||
            ! isset($data['academicYear']) ||
            ! isset($data['terms']) ||
            ! is_array($data['terms'])
        ) {
            throw new UnexpectedValueException(
                "Academic calendar provider returned an invalid payload for {$academicYear}."
            );
        }

        if ((int) $data['academicYear'] !== $academicYear) {
            throw new UnexpectedValueException(
                "Academic calendar provider returned year {$data['academicYear']} instead of {$academicYear}."
            );
        }

        return [
            ...$data,
            'academicYear' => $academicYear,
            'availableAcademicYears' => array_values(array_map(
                'intval',
                is_array($data['availableAcademicYears'] ?? null)
                    ? $data['availableAcademicYears']
                    : [$academicYear]
            )),
            'terms' => array_values($data['terms']),
        ];
    }

    private function url(): string
    {
        $baseUrl = config('services.office_ku.url');
        $endpoint = config('services.academic_calendar_api.endpoint');

        if (
            ! is_string($baseUrl) ||
            ! is_string($endpoint) ||
            $baseUrl === '' ||
            $endpoint === ''
        ) {
            throw new RuntimeException('Academic calendar provider config is missing.');
        }

        return rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');
    }
}
