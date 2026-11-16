<?php

namespace App\Services;

use App\Constants\Status;

class SyncResultSummary
{
    private const SYSTEM_SKIP_REASON_LABELS = [
        'unchanged' => 'ข้อมูลเป็นปัจจุบันแล้ว',
        'invalid_source_data' => 'ข้อมูลจากระบบต้นทางไม่ครบหรือไม่ถูกต้อง',
        'duplicate_source_id' => 'พบรหัสซ้ำจากระบบต้นทาง',
        'faculty_not_found' => 'ไม่พบคณะที่ใช้งานอยู่สำหรับภาควิชา',
    ];

    /**
     * @param  array<int, array{code: string, count: int}>  $skipReasons
     * @return array{status: string, error_message: string|null}
     */
    public function systemMasterData(array $skipReasons): array
    {
        $hasProblematicSkip = false;
        $messages = [];

        foreach ($skipReasons as $reason) {
            $code = $reason['code'];
            $count = $reason['count'];
            $label = self::SYSTEM_SKIP_REASON_LABELS[$code] ?? $code;

            if ($code !== 'unchanged') {
                $hasProblematicSkip = true;
            }

            $messages[] = "ข้าม ({$label}): ".number_format($count).' รายการ';
        }

        return [
            'status' => $hasProblematicSkip
                ? Status::PARTIAL_SUCCESS
                : Status::SUCCESS,
            'error_message' => $messages === [] ? null : implode("\n", $messages),
        ];
    }

    /**
     * @param  array<int, array{academic_year: int, academic_year_be: int, status: string}>  $yearResults
     * @return array{status: string, error_message: string|null}
     */
    public function academicCalendars(array $yearResults): array
    {
        if ($yearResults === []) {
            return ['status' => Status::SUCCESS, 'error_message' => null];
        }

        $yearsByStatus = [
            'inserted' => [],
            'updated' => [],
            'skipped' => [],
            'no_data' => [],
        ];

        foreach ($yearResults as $result) {
            $yearsByStatus[$result['status']][] = $result['academic_year_be'];
        }

        $messages = array_values(array_filter([
            $this->yearMessage('เพิ่ม', $yearsByStatus['inserted']),
            $this->yearMessage('อัปเดต', $yearsByStatus['updated']),
            $this->yearMessage(
                'ข้าม (ข้อมูลเป็นปัจจุบันแล้ว)',
                $yearsByStatus['skipped']
            ),
            $this->yearMessage(
                'ข้าม (ไม่พบข้อมูลจากระบบต้นทาง)',
                $yearsByStatus['no_data']
            ),
        ]));

        $noDataCount = count($yearsByStatus['no_data']);
        $status = match (true) {
            $noDataCount === count($yearResults) => Status::FAILED,
            $noDataCount > 0 => Status::PARTIAL_SUCCESS,
            default => Status::SUCCESS,
        };

        return [
            'status' => $status,
            'error_message' => $messages === [] ? null : implode("\n", $messages),
        ];
    }

    private function yearMessage(string $label, array $years): ?string
    {
        return $years === [] ? null : $label.': '.implode(', ', $years);
    }
}
