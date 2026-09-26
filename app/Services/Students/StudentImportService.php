<?php

namespace App\Services\Students;

use App\Actions\Students\SaveStudent;
use App\Constants\Status;
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
    private const GROUP_HEADERS = [
        'รหัสนิสิต',
        'ข้อมูลส่วนตัว',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        'อาจารย์ที่ปรึกษา',
        'ช่องทางรับเข้า',
        'โรงเรียน ม.ปลาย',
        'ผู้ปกครอง',
        '',
        '',
        '',
        '',
        'สถานะปัจจุบัน',
    ];

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
    ];

    private const REQUIRED_HEADER_INDEXES = [
        0, 2, 3, 4, 5, 6, 7, 8, 9, 11, 18,
    ];

    private const HEADER_MERGES = [
        'B1:I1',
        'N1:R1',
    ];

    public function __construct(
        private readonly SaveStudent $saveStudent,
    ) {}

    public function import(
        UploadedFile $file,
        int $systemDepartmentId,
        int $curriculumId,
        string $curriculumCode,
        int $studyPlanId,
        string $studyPlanNameTh,
        string $teacherId,
        string $teacherFullName,
        array $claims,
    ): array {
        $importType = ImportType::query()
            ->where('type', 'student')
            ->where('status', Status::ACTIVE)
            ->first();

        if ($importType === null) {
            throw ValidationException::withMessages([
                'file' => 'ไม่พบ import type "student" ที่เปิดใช้งาน',
            ]);
        }

        $curriculumCode = mb_substr(trim($curriculumCode), 0, 255);
        $studyPlanNameTh = mb_substr(trim($studyPlanNameTh), 0, 255);
        $teacherId = mb_substr(trim($teacherId), 0, 50);
        $teacherFullName = mb_substr(trim($teacherFullName), 0, 255);

        $import = DataImport::query()->create([
            'import_type_id' => $importType->id,
            'system_department_id' => $systemDepartmentId,
            'curriculum_id' => $curriculumId,
            'curriculum_code' => $curriculumCode,
            'curriculum_plan_id' => $studyPlanId,
            'curriculum_plan_name_th' => $studyPlanNameTh,
            'file_name' => $file->getClientOriginalName(),
            'status' => Status::PROCESSING,
            'imported_by' => $this->importedBy($claims),
            'started_at' => now(),
        ]);

        try {
            $rows = $this->readRows($file);
            $this->validateHeaders($rows);

            $dataRows = array_filter(
                array_slice($rows, 2, null, true),
                fn (array $row) => ! $this->isEmptyRow($this->sourceRow($row)),
            );

            if ($dataRows === []) {
                throw ValidationException::withMessages([
                    'file' => 'ไม่มีข้อมูลสำหรับนำเข้า',
                ]);
            }

            $masterData = $this->masterData();
            $successRows = [];
            $failedRows = [];

            foreach ($dataRows as $index => $row) {
                $sourceRow = $this->sourceRow($row);
                $sourceRow[10] = $teacherFullName;
                $rowNumber = $index + 1;
                [$attributes, $masterErrors] = $this->attributes(
                    $sourceRow,
                    $masterData,
                    $systemDepartmentId,
                    $curriculumId,
                    $curriculumCode,
                    $studyPlanId,
                    $studyPlanNameTh,
                    $teacherId,
                    $teacherFullName,
                );
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
            $successCount = count($successRows);
            $failedCount = count($failedRows);
            $total = $successCount + $failedCount;

            $import->update([
                'file_result_path' => $resultPath,
                'total_count' => $total,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'status' => $this->resultStatus($successCount, $failedCount),
                'error_message' => null,
                'completed_at' => now(),
            ]);

            return [
                'import' => $import->refresh(),
                'absolute_path' => Storage::disk('local')->path($resultPath),
                'download_name' => "student_import_result_{$import->id}.xlsx",
            ];
        } catch (Throwable $exception) {
            $import->update([
                'status' => Status::FAILED,
                'error_message' => $this->errorMessage($exception),
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
        if (! isset($rows[0], $rows[1])) {
            throw ValidationException::withMessages([
                'file' => 'ไม่พบ header ที่แถว 1-2 ในชีต Students',
            ]);
        }

        $actualHeaders = array_map(
            fn (mixed $header) => rtrim(trim((string) $header), '*'),
            array_slice($rows[1], 0, count(self::HEADERS)),
        );

        if ($actualHeaders !== self::HEADERS) {
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

    private function attributes(
        array $row,
        array $masterData,
        int $systemDepartmentId,
        int $curriculumId,
        string $curriculumCode,
        int $studyPlanId,
        string $studyPlanNameTh,
        string $teacherId,
        string $teacherFullName,
    ): array {
        $masterErrors = [];
        $titleId = $this->masterId($row[2], $masterData['titles'], 'คำนำหน้า', true, $masterErrors);
        $admissionChannelId = $this->masterId($row[11], $masterData['admission_channels'], 'ช่องทางรับเข้า', true, $masterErrors);
        $highSchoolId = $this->masterId($row[12], $masterData['high_schools'], 'โรงเรียน ม.ปลาย', false, $masterErrors);
        $guardianTitleId = $this->masterId($row[13], $masterData['titles'], 'คำนำหน้าผู้ปกครอง', false, $masterErrors);
        $relationshipId = $this->masterId($row[16], $masterData['relationships'], 'ความสัมพันธ์', false, $masterErrors);
        $studentStatusId = $this->masterId($row[18], $masterData['student_statuses'], 'สถานะปัจจุบัน', true, $masterErrors);

        return [[
            'student_code' => $row[0] === '' ? null : $row[0],
            'student_id_card' => $row[1],
            'title_id' => $titleId,
            'first_name_th' => $row[3],
            'last_name_th' => $row[4],
            'first_name_en' => $row[5],
            'last_name_en' => $row[6],
            'phone' => $this->phone($row[7]),
            'email' => $row[8],
            'system_department_id' => $systemDepartmentId,
            'curriculum_id' => $curriculumId,
            'curriculum_code' => $curriculumCode,
            'study_plan_id' => $studyPlanId,
            'study_plan_name_th' => $studyPlanNameTh,
            'entry_year' => $this->entryYear($row[9]),
            'teacher_id' => $teacherId,
            'teacher_full_name' => $teacherFullName,
            'admission_channel_id' => $admissionChannelId,
            'high_school_id' => $highSchoolId,
            'guardian_title_id' => $guardianTitleId,
            'guardian_first_name_th' => $this->optionalCell($row[14]),
            'guardian_last_name_th' => $this->optionalCell($row[15]),
            'guardian_relationship_id' => $relationshipId,
            'guardian_phone' => $this->optionalCell($this->phone($row[17])),
            'student_status_id' => $studentStatusId,
        ], $masterErrors];
    }

    private function masterData(): array
    {
        return [
            'titles' => $this->lookup('titles', ['title_abbr_th', 'title_name_th']),
            'admission_channels' => $this->lookup('admission_channels', ['channel_name']),
            'high_schools' => $this->lookup('high_schools', ['school_name']),
            'relationships' => $this->lookup('relationships', ['relationship_name']),
            'student_statuses' => $this->lookup('student_statuses', ['status_name']),
        ];
    }

    private function lookup(string $table, array $columns): array
    {
        $query = DB::table($table)->select(['id', ...$columns]);

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
            $errors[] = "ไม่พบ {$label} \"{$value}\" ในฐานข้อมูล";

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
                'regex:/^\d+$/',
                Rule::unique('students', 'student_code'),
            ],
            'student_id_card' => ['nullable', 'string', 'max:13', Rule::unique('students', 'student_id_card')],
            'title_id' => ['required', 'integer', Rule::exists('titles', 'id')],
            'first_name_th' => ['required', 'string', 'max:50'],
            'last_name_th' => ['required', 'string', 'max:50'],
            'first_name_en' => ['required', 'string', 'max:50'],
            'last_name_en' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:10'],
            'email' => ['required', 'email', 'max:50'],
            'system_department_id' => [
                'required',
                'integer',
                Rule::exists('system_departments', 'id')->where('status', Status::ACTIVE),
            ],
            'curriculum_id' => ['required', 'integer', 'min:1'],
            'curriculum_code' => ['required', 'string', 'max:255'],
            'study_plan_id' => ['required', 'integer', 'min:1'],
            'study_plan_name_th' => ['required', 'string', 'max:255'],
            'entry_year' => ['required', 'integer', 'between:1901,2155'],
            'teacher_id' => ['required', 'string', 'max:50'],
            'teacher_full_name' => ['required', 'string', 'max:255'],
            'admission_channel_id' => ['required', 'integer', Rule::exists('admission_channels', 'id')],
            'high_school_id' => ['nullable', 'integer', Rule::exists('high_schools', 'id')],
            'guardian_title_id' => ['nullable', 'integer', Rule::exists('titles', 'id')],
            'guardian_first_name_th' => ['nullable', 'string', 'max:50'],
            'guardian_last_name_th' => ['nullable', 'string', 'max:50'],
            'guardian_relationship_id' => ['nullable', 'integer', Rule::exists('relationships', 'id')],
            'guardian_phone' => ['nullable', 'string', 'max:10'],
            'student_status_id' => ['required', 'integer', Rule::exists('student_statuses', 'id')],
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
            'system_department_id' => 'ภาควิชา',
            'entry_year' => 'ปีเข้าเรียน',
            'guardian_first_name_th' => 'ชื่อผู้ปกครอง',
            'guardian_last_name_th' => 'นามสกุลผู้ปกครอง',
            'guardian_phone' => 'เบอร์โทรผู้ปกครอง',
        ];
    }

    private function writeResult(int $importId, array $successRows, array $failedRows): string
    {
        $successHeaders = $this->resultHeaders('#B6D7A8');
        $failedHeaders = $this->resultHeaders('#F4CCCC', true);

        $workbook = new SimpleXLSXGen;
        $workbook
            ->setDefaultFont('Tahoma')
            ->setDefaultFontSize(11);

        $workbook
            ->addSheet([...$successHeaders, ...$this->excelRows($successRows)], 'Success')
            ->setColWidth('A:S', 18)
            ->freezePanes('A3');
        $this->mergeGroupHeaders($workbook);

        $workbook
            ->addSheet([...$failedHeaders, ...$this->excelRows($failedRows)], 'Fail')
            ->setColWidth('A:S', 18)
            ->setColWidth('T', 60)
            ->freezePanes('A3');
        $this->mergeGroupHeaders($workbook);

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

    private function resultHeaders(string $backgroundColor, bool $includeFailureReason = false): array
    {
        $groupHeaders = self::GROUP_HEADERS;
        $headers = $this->templateHeaders();

        if ($includeFailureReason) {
            $groupHeaders[] = 'ผลการนำเข้า';
            $headers[] = 'สาเหตุที่ไม่สำเร็จ';
        }

        return [
            array_map(fn (string $header) => $this->headerCell($header, $backgroundColor), $groupHeaders),
            array_map(fn (string $header) => $this->headerCell($header, $backgroundColor), $headers),
        ];
    }

    private function templateHeaders(): array
    {
        return array_map(
            fn (string $header, int $index) => in_array($index, self::REQUIRED_HEADER_INDEXES, true)
                ? "{$header}*"
                : $header,
            self::HEADERS,
            array_keys(self::HEADERS),
        );
    }

    private function mergeGroupHeaders(SimpleXLSXGen $workbook): void
    {
        foreach (self::HEADER_MERGES as $range) {
            $workbook->mergeCells($range);
        }
    }

    private function resultStatus(int $successCount, int $failedCount): string
    {
        if ($failedCount === 0) {
            return Status::COMPLETED;
        }

        if ($successCount === 0) {
            return Status::FAILED;
        }

        return Status::COMPLETED_WITH_ERRORS;
    }

    private function headerCell(string $value, string $backgroundColor): string
    {
        return "<style bgcolor=\"{$backgroundColor}\" color=\"#000000\" border=\"#000000\"><b><center>{$value}</center></b></style>";
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

    private function optionalCell(string $value): ?string
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

    private function errorMessage(Throwable $exception): string
    {
        $messages = $exception instanceof ValidationException
            ? $exception->validator->errors()->all()
            : [$exception->getMessage()];
        $message = implode('; ', array_values(array_unique(array_filter($messages))));

        return mb_substr($message !== '' ? $message : 'ไม่สามารถนำเข้าข้อมูลได้', 0, 10000);
    }
}
