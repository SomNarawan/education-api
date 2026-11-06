<?php

namespace App\Services;

use App\Models\Sync;
use Throwable;

class AcademicCalendarSyncService
{
    public function __construct(
        private readonly AcademicCalendarProviderService $providerService,
        private readonly AcademicCalendarJsonStore $calendarStore,
    ) {}

    /**
     * @param  array<int, int>  $academicYears
     */
    public function sync(array $academicYears, string $actor): Sync
    {
        $sync = Sync::start(Sync::TYPE_ACADEMIC_CALENDAR, $actor);

        try {
            $payloads = [];

            foreach ($academicYears as $academicYear) {
                $payloads[$academicYear] = $this->providerService
                    ->getAcademicCalendar($academicYear);
            }

            $counts = [
                'inserted' => 0,
                'updated' => 0,
                'inactivated' => 0,
                'skipped' => 0,
            ];
            $academicYearResults = [];

            foreach ($payloads as $academicYear => $payload) {
                if (($payload['terms'] ?? []) === []) {
                    $counts['skipped']++;
                    $academicYearResults[] = $this->yearResult(
                        $academicYear,
                        'no_data',
                        false,
                    );

                    continue;
                }

                $existingPayload = $this->calendarStore->get($academicYear);

                if ($existingPayload === $payload) {
                    $counts['skipped']++;
                    $academicYearResults[] = $this->yearResult(
                        $academicYear,
                        'skipped',
                        true,
                    );

                    continue;
                }

                $this->calendarStore->put($academicYear, $payload);
                $status = $existingPayload === null ? 'inserted' : 'updated';
                $counts[$status]++;
                $academicYearResults[] = $this->yearResult(
                    $academicYear,
                    $status,
                    true,
                );
            }

            $sync = $sync->markAsSuccess(
                $counts['inserted'],
                $counts['updated'],
                $counts['inactivated'],
                $counts['skipped'],
                $actor
            );
            $sync->setAttribute('academic_year_results', $academicYearResults);

            return $sync;
        } catch (Throwable $exception) {
            try {
                $sync->markAsFailed($exception->getMessage(), $actor);
            } catch (Throwable) {
                // Keep the provider or persistence error for the API response.
            }

            throw $exception;
        }
    }

    private function yearResult(
        int $academicYear,
        string $status,
        bool $hasData,
    ): array {
        return [
            'academic_year' => $academicYear,
            'academic_year_be' => $academicYear + 543,
            'status' => $status,
            'has_data' => $hasData,
        ];
    }
}
