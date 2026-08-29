<?php

namespace App\Actions\Students;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateStudentAdvisor
{
    public function execute(array $attributes): array
    {
        return DB::transaction(function () use ($attributes): array {
            $systemTeacherId = (int) $attributes['teacher_id'];
            $assignIds = $attributes['assign_student_ids'];
            $removeIds = $attributes['remove_student_ids'];
            $students = Student::query()
                ->whereIn('id', array_merge($assignIds, $removeIds))
                ->lockForUpdate()
                ->get(['id', 'teacher_id'])
                ->keyBy('id');

            $conflictingIds = collect($assignIds)
                ->filter(function (int $studentId) use ($students, $systemTeacherId): bool {
                    $advisorId = $students->get($studentId)?->teacher_id;

                    return $advisorId !== null && (int) $advisorId !== $systemTeacherId;
                })
                ->values()
                ->all();

            if ($conflictingIds !== []) {
                throw ValidationException::withMessages([
                    'assign_student_ids' => 'Students already assigned to another advisor: '.implode(', ', $conflictingIds),
                ]);
            }

            $assignedCount = $assignIds === []
                ? 0
                : Student::query()
                    ->whereIn('id', $assignIds)
                    ->update(['teacher_id' => $systemTeacherId]);

            $removedCount = $removeIds === []
                ? 0
                : Student::query()
                    ->whereIn('id', $removeIds)
                    ->where('teacher_id', $systemTeacherId)
                    ->update(['teacher_id' => null]);

            return [
                'teacher_id' => $systemTeacherId,
                'assign_student_ids' => $assignIds,
                'remove_student_ids' => $removeIds,
                'assigned_count' => $assignedCount,
                'removed_count' => $removedCount,
            ];
        });
    }
}
