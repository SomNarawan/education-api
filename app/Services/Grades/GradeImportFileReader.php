<?php

namespace App\Services\Grades;

use RuntimeException;
use Shuchkin\SimpleXLSX;

class GradeImportFileReader
{
    private const HEADER_ALIASES = [
        'student_code' => ['STDID', 'STUDENTID', 'STUDENTCODE', 'รหัสนิสิต'],
        'course_code' => ['SUBID', 'SUBJECTID', 'COURSEID', 'COURSECODE', 'รหัสวิชา'],
        'registration_type' => ['REGISTYPE', 'REGISTRATIONTYPE', 'ENROLLMENTTYPE', 'ประเภทลงทะเบียน', 'ประเภท'],
        'credit' => ['CREDIT', 'CREDITS', 'หน่วยกิต'],
        'section' => ['SEC', 'SECTION', 'หมู่เรียน'],
        'grade_letter' => ['GRADE', 'GRADELETTER', 'เกรด'],
        'academic_year' => ['YEAR', 'ACADEMICYEAR', 'ปีการศึกษา', 'ปี'],
        'semester' => ['SEMESTER', 'TERM', 'ภาคการศึกษา', 'เทอม'],
    ];

    private const REQUIRED_HEADERS = [
        'student_code',
        'course_code',
        'registration_type',
        'grade_letter',
        'academic_year',
        'semester',
    ];

    private const GRADE_POINTS = [
        'A' => 4.0,
        'B+' => 3.5,
        'B' => 3.0,
        'C+' => 2.5,
        'C' => 2.0,
        'D+' => 1.5,
        'D' => 1.0,
        'F' => 0.0,
    ];

    public function read(string $absolutePath): array
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if ($extension !== 'xlsx') {
            throw new RuntimeException('รองรับเฉพาะไฟล์ .xlsx');
        }

        return $this->normalizeRows($this->readXlsx($absolutePath));
    }

    private function readXlsx(string $path): array
    {
        $xlsx = SimpleXLSX::parse($path);

        if ($xlsx === false) {
            throw new RuntimeException('ไม่สามารถอ่านไฟล์ Excel ได้: '.SimpleXLSX::parseError());
        }

        return $xlsx->rows(0);
    }

    private function normalizeRows(array $rows): array
    {
        [$headerIndex, $columns] = $this->findHeader($rows);
        $result = [];
        $errors = [];

        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rowNumber = $index + 1;

            try {
                $result[] = $this->normalizeRow($row, $columns, $rowNumber);
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();

                if (count($errors) >= 10) {
                    break;
                }
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }

        if ($result === []) {
            throw new RuntimeException('ไม่พบข้อมูลผลการเรียนในไฟล์');
        }

        return $result;
    }

    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $normalized = array_map(fn (mixed $value) => $this->normalizeHeader($value), $row);
            $columns = [];

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $columnIndex = array_search($this->normalizeHeader($alias), $normalized, true);

                    if ($columnIndex !== false) {
                        $columns[$field] = $columnIndex;
                        break;
                    }
                }
            }

            if (array_diff(self::REQUIRED_HEADERS, array_keys($columns)) === []) {
                return [$index, $columns];
            }
        }

        throw new RuntimeException(
            'ไม่พบ header ที่จำเป็น: รหัสนิสิต, รหัสวิชา, ประเภท, เกรด, ปี, เทอม',
        );
    }

    private function normalizeRow(array $row, array $columns, int $rowNumber): array
    {
        $value = fn (string $field): string => array_key_exists($field, $columns)
            ? $this->cell($row[$columns[$field]] ?? null)
            : '';
        $studentCode = $this->digits($value('student_code'));
        $courseCode = $this->digits($value('course_code'));
        $creditValue = $value('credit');
        $credit = $creditValue === ''
            ? null
            : filter_var($creditValue, FILTER_VALIDATE_FLOAT);
        $grade = strtoupper($value('grade_letter'));
        $academicYear = filter_var($value('academic_year'), FILTER_VALIDATE_INT);

        if ($studentCode === '' || strlen($studentCode) > 10) {
            throw new RuntimeException("แถว {$rowNumber}: รหัสนิสิตไม่ถูกต้อง");
        }

        if ($courseCode === '' || strlen($courseCode) > 8) {
            throw new RuntimeException("แถว {$rowNumber}: รหัสวิชาไม่ถูกต้อง");
        }

        if ($credit !== null && ($credit === false || $credit < 0)) {
            throw new RuntimeException("แถว {$rowNumber}: หน่วยกิตไม่ถูกต้อง");
        }

        if (! array_key_exists($grade, self::GRADE_POINTS)
            && ! in_array($grade, ['P', 'NP', 'S', 'U', 'W'], true)) {
            throw new RuntimeException("แถว {$rowNumber}: เกรด {$grade} ไม่รองรับ");
        }

        if ($academicYear === false) {
            throw new RuntimeException("แถว {$rowNumber}: ปีการศึกษาไม่ถูกต้อง");
        }

        [$semesterOrder, $semesterName] = $this->semester($value('semester'), $rowNumber);

        return [
            'student_code' => str_pad($studentCode, 10, '0', STR_PAD_LEFT),
            'course_code' => str_pad($courseCode, 8, '0', STR_PAD_LEFT),
            'enrollment_type' => $this->enrollmentType($value('registration_type')),
            'credit' => $credit === null ? null : (float) $credit,
            'section' => $value('section'),
            'grade_letter' => $grade,
            'grade_point' => self::GRADE_POINTS[$grade] ?? null,
            'academic_year' => $this->academicYear((int) $academicYear),
            'semester_order' => $semesterOrder,
            'semester' => $semesterName,
        ];
    }

    private function semester(string $value, int $rowNumber): array
    {
        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            '1', 'ภาคต้น', 'ต้น', 'first' => [1, 'ภาคต้น'],
            '2', 'ภาคปลาย', 'ปลาย', 'second' => [2, 'ภาคปลาย'],
            '3', 'ภาคฤดูร้อน', 'ฤดูร้อน', 'summer' => [3, 'ภาคฤดูร้อน'],
            default => throw new RuntimeException("แถว {$rowNumber}: ภาคการศึกษาไม่ถูกต้อง"),
        };
    }

    private function academicYear(int $year): int
    {
        if ($year >= 2400) {
            return $year - 543;
        }

        if ($year >= 1900) {
            return $year;
        }

        if ($year >= 0 && $year <= 99) {
            return $year + 1957;
        }

        throw new RuntimeException('ปีการศึกษาไม่ถูกต้อง');
    }

    private function enrollmentType(string $value): string
    {
        return in_array(strtoupper(trim($value)), ['A', 'AUDIT'], true) ? 'audit' : 'credit';
    }

    private function normalizeHeader(mixed $value): string
    {
        $header = mb_strtoupper($this->cell($value));

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $header) ?? '';
    }

    private function digits(string $value): string
    {
        $value = preg_replace('/\.0+$/', '', trim($value)) ?? '';

        return preg_match('/^\d+$/', $value) === 1 ? $value : '';
    }

    private function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value, " \t\n\r\0\x0B\xEF\xBB\xBF");
    }

    private function isEmptyRow(array $row): bool
    {
        return count(array_filter($row, fn (mixed $value) => $this->cell($value) !== '')) === 0;
    }
}
