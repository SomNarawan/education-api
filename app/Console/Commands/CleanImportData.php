<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Removes imported students and grades so student/grade imports can be tested again.
 * Development only: refuses to run outside the local and testing environments.
 */
class CleanImportData extends Command
{
    protected $signature = 'dev:clean-import-data
        {--all : ลบนิสิตทั้งหมด ประวัติการ import และไฟล์เกรดทั้งหมด}
        {--plan=* : ลบเฉพาะนิสิตของ study_plan_id นี้ (ระบุซ้ำได้)}
        {--student=* : ลบเฉพาะรหัสนิสิตนี้ (ระบุซ้ำได้)}
        {--grades-only : ล้างเฉพาะเกรด เก็บข้อมูลนิสิตไว้}
        {--dry-run : แสดงสิ่งที่จะถูกลบ โดยไม่ลบจริง}
        {--force : ไม่ต้องถามยืนยัน}';

    protected $description = 'ล้างข้อมูลที่ import นิสิตและเกรด เพื่อทดสอบการ import ใหม่ (ใช้ได้เฉพาะ local/testing)';

    private const ALLOWED_ENVIRONMENTS = ['local', 'testing'];

    private const STUDENT_CHILD_TABLES = ['notes', 'student_guardian_phone_histories'];

    // Columns the grade import writes back onto students; a new student has them empty.
    private const GRADE_SUMMARY_COLUMNS = ['gpa', 'gpax', 'passed_credits', 'not_passed_credits', 'overed_credits'];

    // Per-student files written by the grade import, as {directory}/{student_code}.json.
    // Keep in sync with StudentGradeJsonGenerator::deleteGeneratedData().
    private const GRADE_DIRECTORIES = [
        'data/grade_attempts',
        'data/enrollments',
        'data/enrollments_not_pass',
        'data/enrollments_pass',
        'data/enrollments_over',
        'data/graph/by_credit',
        'data/graph/by_semester',
    ];

    // Holds {student_code}_{n}.json files.
    private const GRADE_GROUP_DIRECTORY = 'data/graph/by_group';

    private const STUDENT_CODE_PATTERN = '/^[A-Za-z0-9]+$/';

    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            $this->error('คำสั่งนี้ใช้ได้เฉพาะ APP_ENV='.implode('/', self::ALLOWED_ENVIRONMENTS)
                .' (ตอนนี้คือ '.app()->environment().')');

            return self::FAILURE;
        }

        $all = (bool) $this->option('all');
        $planIds = $this->planIds();
        $codes = $this->studentCodes();
        $gradesOnly = (bool) $this->option('grades-only');

        if ($planIds === null || $codes === null) {
            return self::INVALID;
        }

        if ((int) $all + (int) ($planIds !== []) + (int) ($codes !== []) !== 1) {
            $this->error('ระบุขอบเขตอย่างใดอย่างหนึ่ง: --all, --plan=<study_plan_id> หรือ --student=<รหัสนิสิต>');

            return self::INVALID;
        }

        $students = $this->students($all, $planIds, $codes);
        $importIds = $all ? $this->importIds($gradesOnly ? ['grade'] : ['student', 'grade']) : collect();
        $disk = Storage::disk('local');
        $fileCodes = $students->pluck('student_code')
            ->merge($codes)
            ->map(fn ($code) => trim((string) $code))
            ->filter(fn (string $code) => preg_match(self::STUDENT_CODE_PATTERN, $code) === 1)
            ->unique()
            ->values();
        $gradeFiles = $all ? $this->allGradeFiles($disk) : $this->gradeFilesFor($disk, $fileCodes);
        $importDirectories = $all ? ($gradesOnly ? ['imports/grades'] : ['imports/students', 'imports/grades']) : [];

        $this->summary($students, $importIds, $gradeFiles, $importDirectories, $gradesOnly);

        if ($students->isEmpty() && $importIds->isEmpty() && $gradeFiles === [] && ! $this->hasFiles($disk, $importDirectories)) {
            $this->info('ไม่มีข้อมูลให้ลบ');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('dry-run: ยังไม่ได้ลบอะไร');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('ยืนยันการลบข้อมูลด้านบน?')) {
            $this->info('ยกเลิกแล้ว');

            return self::SUCCESS;
        }

        $childTables = array_values(array_filter(self::STUDENT_CHILD_TABLES, fn (string $table) => Schema::hasTable($table)));

        DB::transaction(function () use ($students, $importIds, $gradesOnly, $childTables): void {
            foreach ($students->pluck('id')->chunk(self::CHUNK_SIZE) as $ids) {
                if ($gradesOnly) {
                    DB::table('students')
                        ->whereIn('id', $ids->all())
                        ->update(array_fill_keys(self::GRADE_SUMMARY_COLUMNS, null));

                    continue;
                }

                foreach ($childTables as $table) {
                    DB::table($table)->whereIn('student_id', $ids->all())->delete();
                }

                // Hard delete, soft-deleted rows included: the import looks students up
                // without trashed rows, so a leftover row would block re-importing its code.
                DB::table('students')->whereIn('id', $ids->all())->delete();
            }

            foreach ($importIds->chunk(self::CHUNK_SIZE) as $ids) {
                DB::table('imports')->whereIn('id', $ids->all())->delete();
            }
        });

        if ($all) {
            foreach ([...self::GRADE_DIRECTORIES, self::GRADE_GROUP_DIRECTORY, ...$importDirectories] as $directory) {
                $disk->deleteDirectory($directory);
            }
        } elseif ($gradeFiles !== []) {
            $disk->delete($gradeFiles);
        }

        $this->info('ล้างข้อมูลเรียบร้อย');

        return self::SUCCESS;
    }

    /**
     * @return list<int>|null null when an option value is not a positive integer
     */
    private function planIds(): ?array
    {
        $ids = [];

        foreach ((array) $this->option('plan') as $value) {
            $value = trim((string) $value);

            if (! ctype_digit($value) || (int) $value < 1) {
                $this->error("--plan ต้องเป็นตัวเลข: {$value}");

                return null;
            }

            $ids[] = (int) $value;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>|null null when a code contains characters other than letters and digits
     */
    private function studentCodes(): ?array
    {
        $codes = [];

        foreach ((array) $this->option('student') as $value) {
            $value = trim((string) $value);

            if (preg_match(self::STUDENT_CODE_PATTERN, $value) !== 1) {
                $this->error("--student ต้องเป็นตัวอักษรหรือตัวเลขเท่านั้น: {$value}");

                return null;
            }

            $codes[] = $value;
        }

        return array_values(array_unique($codes));
    }

    private function students(bool $all, array $planIds, array $codes): Collection
    {
        return DB::table('students')
            ->select(['id', 'student_code'])
            ->when(! $all && $planIds !== [], fn ($query) => $query->whereIn('study_plan_id', $planIds))
            ->when(! $all && $codes !== [], fn ($query) => $query->whereIn('student_code', $codes))
            ->orderBy('id')
            ->get();
    }

    private function importIds(array $types): Collection
    {
        return DB::table('imports')
            ->join('import_types', 'import_types.id', '=', 'imports.import_type_id')
            ->whereIn('import_types.type', $types)
            ->pluck('imports.id');
    }

    /**
     * @return list<string>
     */
    private function allGradeFiles(Filesystem $disk): array
    {
        $files = [];

        foreach ([...self::GRADE_DIRECTORIES, self::GRADE_GROUP_DIRECTORY] as $directory) {
            $files = [...$files, ...$disk->allFiles($directory)];
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function gradeFilesFor(Filesystem $disk, Collection $codes): array
    {
        if ($codes->isEmpty()) {
            return [];
        }

        $files = [];

        foreach ($codes as $code) {
            foreach (self::GRADE_DIRECTORIES as $directory) {
                $path = "{$directory}/{$code}.json";

                if ($disk->exists($path)) {
                    $files[] = $path;
                }
            }
        }

        $wanted = $codes->flip();

        foreach ($disk->files(self::GRADE_GROUP_DIRECTORY) as $path) {
            if (preg_match('/\/([A-Za-z0-9]+)_\d+\.json$/', $path, $match) === 1 && $wanted->has($match[1])) {
                $files[] = $path;
            }
        }

        return $files;
    }

    private function hasFiles(Filesystem $disk, array $directories): bool
    {
        foreach ($directories as $directory) {
            if ($disk->allFiles($directory) !== []) {
                return true;
            }
        }

        return false;
    }

    private function summary(
        Collection $students,
        Collection $importIds,
        array $gradeFiles,
        array $importDirectories,
        bool $gradesOnly,
    ): void {
        $rows = [
            [
                $gradesOnly ? 'นิสิตที่จะล้างเกรด (gpa, gpax, หน่วยกิต)' : 'นิสิตที่จะลบ (รวม notes, ประวัติเบอร์ผู้ปกครอง)',
                $students->count(),
            ],
            ['ไฟล์เกรด (data/...)', count($gradeFiles)],
        ];

        if ($importDirectories !== []) {
            $rows[] = ['ประวัติการ import (ตาราง imports)', $importIds->count()];
            $rows[] = ['โฟลเดอร์ไฟล์ import', implode(', ', $importDirectories)];
        }

        $this->table(['รายการ', 'จำนวน'], $rows);

        if ($students->isNotEmpty() && $students->count() <= 20) {
            $this->line('รหัสนิสิต: '.$students->pluck('student_code')->implode(', '));
        }
    }
}
