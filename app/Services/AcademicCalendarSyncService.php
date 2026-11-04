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

            foreach ($payloads as $academicYear => $payload) {
                $existingPayload = $this->calendarStore->get($academicYear);

                if ($existingPayload === $payload) {
                    $counts['skipped']++;

                    continue;
                }

                $this->calendarStore->put($academicYear, $payload);
                $counts[$existingPayload === null ? 'inserted' : 'updated']++;
            }

            return $sync->markAsSuccess(
                $counts['inserted'],
                $counts['updated'],
                $counts['inactivated'],
                $counts['skipped'],
                $actor
            );
        } catch (Throwable $exception) {
            try {
                $sync->markAsFailed($exception->getMessage(), $actor);
            } catch (Throwable) {
                // Keep the provider or persistence error for the API response.
            }

            throw $exception;
        }
    }
}
