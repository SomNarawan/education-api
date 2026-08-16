<?php

namespace App\Services\Students;

use App\Actions\Students\SaveStudent;
use App\Models\DataImport;
use App\Models\ImportType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLSXGen;
use Throwable;

class StudentImportService
{
    private const HEADERS = [
        'รหัสนิสิต',
        'เลขบัตรประชาชน',
        'คำนำหน้า',
        'ชื่อภาษาไทย',
        'นามสกุลภาษาไทย',
        'ชื่อภาษาอังกฤษ',
        'นามสกุลภาษาอังกฤษ',
        'เบอร์โทร',
        'อีเมล',
        'แผนการเรียน',
        'ปีเข้าเรียน (พ.ศ.)',
        'อาจารย์ที่ปรึกษา',
        'ช่องทางรับเข้า',
        'โรงเรียน ม.ปลาย',
        'คำนำหน้าผู้ปกครอง',
        'ชื่อผู้ปกครอง',
        'นามสกุลผู้ปกครอง',
        'ความสัมพันธ์',
        'เบอร์โทรผู้ปกครอง',
        'สถานะปัจจุบัน',
        'GPA',
        'GPAX',
        'จำนวนหน่วยกิตที่ผ่าน',
        'จำนวนหน่วยกิตที่ไม่ผ่าน',
        'จำนวนหน่วยกิตที่เกิน',
    ];

    public function __construct(private readonly SaveStudent $saveStudent) {}

    public function import(UploadedFile $file, array $claims): array
    {
        $importType = ImportType::query()
            ->where('type', 'student')
            ->where('status', ImportType::STATUS_ACTIVE)
            ->first();

        if ($importType === null) {
            throw ValidationException::withMessages([
                'file' => 'ไม่พบ import type "student" ที่เปิดใช้งาน',
            ]);
        }

        $import = DataImport::query()->create([
            'import_type_id' => $importType->id,
            'file_name' => $file->getClientOriginalName(),
            'status' => 'processing',
            'imported_by' => $this->importedBy($claims),
            'started_at' => now(),
        ]);

        try {
            $rows = $this->readRows($file);
            $this->validateHeaders($rows);

            $masterData = $this->masterData();
            $successRows = [];
            $failedRows = [];

            foreach (array_slice($rows, 2, null, true) as $index => $row) {
                $sourceRow = $this->sourceRow($row);

                if ($this->isEmptyRow($sourceRow)) {
                    continue;
                }

                $rowNumber = $index + 1;
                [$attributes, $masterErrors] = $this->attributes($sourceRow, $masterData);
                $validator = Validator::make(
                    $attributes,
                    $this->studentRules(),
                    $this->validationMessages(),
                    $this->attributeNames(),
                );
                $errors = [...$masterErrors, ...$validator->errors()->all()];

                if ($errors !== []) {
                    $failedRows[] = [...$sourceRow, $this->failureReason($rowNumber, $errors)];

                    continue;
                }

                try {
                    DB::transaction(fn () => $this->saveStudent->create($validator->validated()));
                    $successRows[] = $sourceRow;
                } catch (ValidationException $exception) {
                    $failedRows[] = [
                        ...$sourceRow,
                        $this->failureReason($rowNumber, $exception->validator->errors()->all()),
                    ];
                } catch (Throwable $exception) {
                    report($exception);
                    $failedRows[] = [
                        ...$sourceRow,
                        $this->failureReason($rowNumber, ['ไม่สามารถบันทึกข้อมูลลงฐานข้อมูลได้']),
                    ];
                }
            }

            $resultPath = $this->writeResult($import->id, $successRows, $failedRows);
            $total = count($successRows) + count($failedRows);

            $import->update([
                'file_result_path' => $resultPath,
                'total_count' => $total,
                'success_count' => count($successRows),
                'failed_count' => count($failedRows),
                'status' => $failedRows === [] ? 'completed' : 'completed_with_errors',
                'completed_at' => now(),
            ]);

            return [
                'import' => $import->refresh(),
                'absolute_path' => Storage::disk('local')->path($resultPath),
                'download_name' => "student_import_result_{$import->id}.xlsx",
            ];
        } catch (Throwable $exception) {
            $import->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function readRows(UploadedFile $file): array
    {
        $xlsx = SimpleXLSX::parse($file->getRealPath());

        if ($xlsx === false) {
            throw ValidationException::withMessages([
                'file' => 'ไม่สามารถอ่านไฟล์ Excel ได้: '.SimpleXLSX::parseError(),
            ]);
        }

        $sheetIndex = array_search('Students', $xlsx->sheetNames(), true);

        if ($sheetIndex === false) {
            throw ValidationException::withMessages([
                'file' => 'ไม่พบชีต Students ในไฟล์ Excel',
            ]);
        }

        return $xlsx->rows($sheetIndex);
    }

    private function validateHeaders(array $rows): void
    {
        if (! isset($rows[1])) {
            throw ValidationException::withMessages([
                'file' => 'ไม่พบ header ที่แถว 2 ในชีต Students',
            ]);
        }

        $actual = array_map(
            fn (mixed $header) => preg_replace('/\s*\*\s*$/u', '', trim((string) $header)),
            array_slice($rows[1], 0, count(self::HEADERS)),
        );

        if ($actual !== self::HEADERS) {
            throw ValidationException::withMessages([
                'file' => 'รูปแบบ header ไม่ตรงกับ Import Student Template',
            ]);
        }
    }

    private function sourceRow(array $row): array
    {
        $row = array_slice(array_pad($row, count(self::HEADERS), ''), 0, count(self::HEADERS));

        return array_map(fn (mixed $value) => $this->cellValue($value), $row);
    }

    private function attributes(array $row, array $masterData): array
    {
        $masterErrors = [];
        $titleId = $this->masterId($row[2], $masterData['titles'], 'คำนำหน้า', true, $masterErrors);
        $studyPlanId = $this->masterId($row[9], $masterData['study_plans'], 'แผนการเรียน', true, $masterErrors);
        $teacherId = $this->masterId($row[11], $masterData['teachers'], 'อาจารย์ที่ปรึกษา', false, $masterErrors);
        $admissionChannelId = $this->masterId($row[12], $masterData['admission_channels'], 'ช่องทางรับเข้า', true, $masterErrors);
        $highSchoolId = $this->masterId($row[13], $masterData['high_schools'], 'โรงเรียน ม.ปลาย', true, $masterErrors);
        $guardianTitleId = $this->masterId($row[14], $masterData['titles'], 'คำนำหน้าผู้ปกครอง', true, $masterErrors);
        $relationshipId = $this->masterId($row[17], $masterData['relationships'], 'ความสัมพันธ์', true, $masterErrors);
        $studentStatusId = $this->masterId($row[19], $masterData['student_statuses'], 'สถานะปัจจุบัน', true, $masterErrors);

        return [[
            'student_code' => $row[0],
            'student_id_card' => $row[1],
            'title_id' => $titleId,
            'first_name_th' => $row[3],
            'last_name_th' => $row[4],
            'first_name_en' => $row[5],
            'last_name_en' => $row[6],
            'phone' => $this->phone($row[7]),
            'email' => $row[8],
            'study_plan_id' => $studyPlanId,
            'entry_year' => $this->entryYear($row[10]),
            'teacher_id' => $teacherId,
            'admission_channel_id' => $admissionChannelId,
            'high_school_id' => $highSchoolId,
            'guardian_title_id' => $guardianTitleId,
            'guardian_first_name_th' => $row[15],
            'guardian_last_name_th' => $row[16],
            'guardian_relationship_id' => $relationshipId,
            'guardian_phone' => $this->phone($row[18]),
            'student_status_id' => $studentStatusId,
            'gpa' => $row[20],
            'gpax' => $row[21],
            'passed_credits' => $this->nullableValue($row[22]),
            'not_passed_credits' => $this->nullableValue($row[23]),
            'overed_credits' => $this->nullableValue($row[24]),
        ], $masterErrors];
    }

    private function masterData(): array
    {
        return [
            'titles' => $this->lookup('titles', ['title_abbr_th', 'title_name_th'], true),
            'study_plans' => $this->lookup('curriculum_plans', ['name_th', 'code'], true),
            'teachers' => $this->lookup('teachers', ['full_name_th'], false, true),
            'admission_channels' => $this->lookup('admission_channels', ['channel_name'], true),
            'high_schools' => $this->lookup('high_schools', ['school_name'], true),
            'relationships' => $this->lookup('relationships', ['relationship_name'], true),
            'student_statuses' => $this->lookup('student_statuses', ['status_name'], true),
        ];
    }

    private function lookup(
        string $table,
        array $columns,
        bool $activeOnly,
        bool $withoutDeleted = false,
    ): array {
        $query = DB::table($table)->select(['id', ...$columns]);

        if ($activeOnly) {
            $query->where('status', 'active');
        }

        if ($withoutDeleted) {
            $query->whereNull('deleted_at');
        }

        $lookup = [];

        foreach ($query->get() as $row) {
            foreach ($columns as $column) {
                $key = $this->normalizedKey($row->{$column});

                if ($key !== '') {
                    $lookup[$key] ??= (int) $row->id;
                }
            }
        }

        return $lookup;
    }

    private function masterId(
        string $value,
        array $lookup,
        string $label,
        bool $required,
        array &$errors,
    ): ?int {
        $key = $this->normalizedKey($value);

        if ($key === '') {
            if ($required) {
                $errors[] = "{$label} จำเป็นต้องระบุ";
            }

            return null;
        }

        if (! isset($lookup[$key])) {
            $errors[] = "ไม่พบ {$label} \"{$value}\" ในข้อมูล master ที่เปิดใช้งาน";

            return null;
        }

        return $lookup[$key];
    }

    private function studentRules(): array
    {
        return [
            'student_code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('students', 'student_code')->whereNull('deleted_at'),
            ],
            'student_id_card' => ['required', 'string', 'max:13', Rule::unique('students', 'student_id_card')],
            'title_id' => ['nullable', 'integer'],
            'first_name_th' => ['required', 'string', 'max:50'],
            'last_name_th' => ['required', 'string', 'max:50'],
            'first_name_en' => ['required', 'string', 'max:50'],
            'last_name_en' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:10'],
            'email' => ['required', 'email', 'max:50'],
            'study_plan_id' => ['nullable', 'integer'],
            'entry_year' => ['required', 'integer', 'between:1901,2155'],
            'teacher_id' => ['nullable', 'integer'],
            'admission_channel_id' => ['nullable', 'integer'],
            'high_school_id' => ['nullable', 'integer'],
            'guardian_title_id' => ['nullable', 'integer'],
            'guardian_first_name_th' => ['required', 'string', 'max:50'],
            'guardian_last_name_th' => ['required', 'string', 'max:50'],
            'guardian_relationship_id' => ['nullable', 'integer'],
            'guardian_phone' => ['required', 'string', 'max:10'],
            'student_status_id' => ['nullable', 'integer'],
            'gpa' => ['required', 'numeric', 'between:0,4'],
            'gpax' => ['required', 'numeric', 'between:0,4'],
            'passed_credits' => ['nullable', 'integer', 'min:0'],
            'not_passed_credits' => ['nullable', 'integer', 'min:0'],
            'overed_credits' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function validationMessages(): array
    {
        return [
            'required' => ':attribute จำเป็นต้องระบุ',
            'string' => ':attribute ต้องเป็นข้อความ',
            'integer' => ':attribute ต้องเป็นจำนวนเต็ม',
            'numeric' => ':attribute ต้องเป็นตัวเลข',
            'email' => ':attribute รูปแบบไม่ถูกต้อง',
            'max' => ':attribute ต้องยาวไม่เกิน :max ตัวอักษร',
            'between' => ':attribute ต้องอยู่ระหว่าง :min ถึง :max',
            'min' => ':attribute ต้องไม่น้อยกว่า :min',
            'unique' => ':attribute มีอยู่ในระบบแล้ว',
        ];
    }

    private function attributeNames(): array
    {
        return [
            'student_code' => 'รหัสนิสิต',
            'student_id_card' => 'เลขบัตรประชาชน',
            'first_name_th' => 'ชื่อภาษาไทย',
            'last_name_th' => 'นามสกุลภาษาไทย',
            'first_name_en' => 'ชื่อภาษาอังกฤษ',
            'last_name_en' => 'นามสกุลภาษาอังกฤษ',
            'phone' => 'เบอร์โทร',
            'email' => 'อีเมล',
            'entry_year' => 'ปีเข้าเรียน',
            'guardian_first_name_th' => 'ชื่อผู้ปกครอง',
            'guardian_last_name_th' => 'นามสกุลผู้ปกครอง',
            'guardian_phone' => 'เบอร์โทรผู้ปกครอง',
            'gpa' => 'GPA',
            'gpax' => 'GPAX',
            'passed_credits' => 'จำนวนหน่วยกิตที่ผ่าน',
            'not_passed_credits' => 'จำนวนหน่วยกิตที่ไม่ผ่าน',
            'overed_credits' => 'จำนวนหน่วยกิตที่เกิน',
        ];
    }

    private function writeResult(int $importId, array $successRows, array $failedRows): string
    {
        $successHeader = array_map(
            fn (string $header) => $this->headerCell($header, '#70AD47'),
            self::HEADERS,
        );
        $failedHeader = [
            ...array_map(fn (string $header) => $this->headerCell($header, '#C00000'), self::HEADERS),
            $this->headerCell('สาเหตุที่ไม่สำเร็จ', '#C00000'),
        ];

        $workbook = new SimpleXLSXGen;
        $workbook
            ->addSheet([$successHeader, ...$this->excelRows($successRows)], 'Success')
            ->addSheet([$failedHeader, ...$this->excelRows($failedRows)], 'Fail')
            ->setDefaultFont('Tahoma')
            ->setDefaultFontSize(11)
            ->setColWidth('A:Y', 18)
            ->setColWidth('Z', 60);

        $path = "imports/students/{$importId}/result.xlsx";
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($path));

        if (! $workbook->saveAs($disk->path($path))) {
            throw new \RuntimeException('ไม่สามารถสร้างไฟล์ผลลัพธ์ import ได้');
        }

        return $path;
    }

    private function excelRows(array $rows): array
    {
        return array_map(
            fn (array $row) => array_map(
                fn (mixed $value) => SimpleXLSXGen::raw((string) $value),
                $row,
            ),
            $rows,
        );
    }

    private function headerCell(string $value, string $backgroundColor): string
    {
        return "<style bgcolor=\"{$backgroundColor}\" color=\"#FFFFFF\"><b><center>{$value}</center></b></style>";
    }

    private function importedBy(array $claims): string
    {
        foreach (['nontri_id', 'name', 'given_name'] as $claim) {
            if (isset($claims[$claim]) && is_scalar($claims[$claim]) && trim((string) $claims[$claim]) !== '') {
                return mb_substr(trim((string) $claims[$claim]), 0, 150);
            }
        }

        return 'unknown';
    }

    private function cellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function nullableValue(string $value): mixed
    {
        return $value === '' ? null : $value;
    }

    private function entryYear(string $value): mixed
    {
        if ($value === '' || ! preg_match('/^\d{4}$/', $value)) {
            return $value;
        }

        $year = (int) $value;

        return $year >= 2400 ? $year - 543 : $year;
    }

    private function phone(string $value): string
    {
        if ($value !== '' && ctype_digit($value) && strlen($value) < 10) {
            return str_pad($value, 10, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    private function normalizedKey(mixed $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);

        return mb_strtolower($value);
    }

    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn (string $value) => $value === '');
    }

    private function failureReason(int $rowNumber, array $errors): string
    {
        $errors = array_values(array_unique(array_filter($errors)));

        return "แถว {$rowNumber}: ".implode('; ', $errors);
    }
}
