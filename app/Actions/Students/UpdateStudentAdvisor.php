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
            $studyPlanId = (int) $attributes['study_plan_id'];
            $teacherId = $attributes['teacher_id'];
            $assignIds = $attributes['assign_student_ids'];
            $removeIds = $attributes['remove_student_ids'];
            $requestedIds = array_values(array_unique([
                ...$assignIds,
                ...$removeIds,
            ]));
            $students = Student::query()
                ->whereIn('id', $requestedIds)
                ->lockForUpdate()
                ->get(['id', 'teacher_id', 'study_plan_id'])
                ->keyBy('id');

            $invalidStudyPlanIds = collect($requestedIds)
                ->filter(
                    fn (int $studentId): bool =>
                        (int) $students->get($studentId)?->study_plan_id !== $studyPlanId
                )
                ->values()
                ->all();

            if ($invalidStudyPlanIds !== []) {
                throw ValidationException::withMessages([
                    'student_ids' => 'Students do not belong to the selected study plan: '.implode(', ', $invalidStudyPlanIds),
                ]);
            }

            $conflictingIds = collect($assignIds)
                ->filter(function (int $studentId) use ($students, $teacherId): bool {
                    $advisorId = $students->get($studentId)?->teacher_id;

                    return $advisorId !== null && $advisorId !== $teacherId;
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
                    ->where('study_plan_id', $studyPlanId)
                    ->update([
                        'teacher_id' => $teacherId,
                        'teacher_full_name' => $attributes['teacher_full_name'],
                    ]);

            $removedCount = $removeIds === []
                ? 0
                : Student::query()
                    ->whereIn('id', $removeIds)
                    ->where('study_plan_id', $studyPlanId)
                    ->where('teacher_id', $teacherId)
                    ->update(['teacher_id' => null, 'teacher_full_name' => null]);

            return [
                'study_plan_id' => $studyPlanId,
                'teacher_id' => $teacherId,
                'assign_student_ids' => $assignIds,
                'remove_student_ids' => $removeIds,
                'assigned_count' => $assignedCount,
                'removed_count' => $removedCount,
            ];
        });
    }
}
