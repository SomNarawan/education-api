<?php

namespace App\Services\Grades;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Shuchkin\SimpleXLSXGen;

class GradeImportResultWriter
{
    private const HEADERS = [
        'รหัสนิสิต',
        'รหัสวิชา',
        'ประเภท',
        'เกรด',
        'ปี',
        'เทอม',
    ];

    public function write(int $importId, array $successRows, array $failedRows): string
    {
        $workbook = new SimpleXLSXGen;
        $workbook
            ->setDefaultFont('Tahoma')
            ->setDefaultFontSize(11);

        $workbook
            ->addSheet([
                $this->headers('#B6D7A8'),
                ...$this->excelRows($successRows),
            ], 'Success')
            ->setColWidth('A:F', 18)
            ->freezePanes('A2');

        $workbook
            ->addSheet([
                $this->headers('#F4CCCC', true),
                ...$this->excelRows($failedRows),
            ], 'Fail')
            ->setColWidth('A:F', 18)
            ->setColWidth('G', 60)
            ->freezePanes('A2');

        $path = "imports/grades/{$importId}/result.xlsx";
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($path));

        if (! $workbook->saveAs($disk->path($path))) {
            throw new RuntimeException('ไม่สามารถสร้างไฟล์ผลลัพธ์ import ได้');
        }

        return $path;
    }

    private function headers(string $backgroundColor, bool $includeFailureReason = false): array
    {
        $headers = self::HEADERS;

        if ($includeFailureReason) {
            $headers[] = 'สาเหตุที่ไม่สำเร็จ';
        }

        return array_map(
            fn (string $header): string => "<style bgcolor=\"{$backgroundColor}\" color=\"#000000\" border=\"#000000\"><b><center>{$header}</center></b></style>",
            $headers,
        );
    }

    private function excelRows(array $rows): array
    {
        return array_map(
            fn (array $row): array => array_map(
                fn (mixed $value) => SimpleXLSXGen::raw((string) $value),
                $row,
            ),
            $rows,
        );
    }
}
