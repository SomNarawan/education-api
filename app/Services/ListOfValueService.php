<?php

namespace App\Services;

use App\Constants\Status;
use App\Contracts\CmisApi;
use App\Contracts\TeacherApi;
use App\Enums\ListOfValueType;
use App\Models\AdmissionChannel;
use App\Models\District;
use App\Models\HighSchool;
use App\Models\ImportType;
use App\Models\NoteType;
use App\Models\Province;
use App\Models\Relationship;
use App\Models\StudentStatus;
use App\Models\Subdistrict;
use App\Models\SystemDepartment;
use App\Models\SystemFaculty;
use App\Models\Title;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ListOfValueService
{
    public function __construct(
        private readonly TeacherApi $teacherApi,
        private readonly CmisApi $cmisApi,
    ) {}

    public function get(ListOfValueType $type, array $filters = []): Collection
    {
        $includeIds = array_map('intval', $filters['include_ids'] ?? []);

        return match ($type) {
            ListOfValueType::Titles => $this->options(
                Title::query()->where('status', Status::ACTIVE),
                'title_name_th',
                'title_name_en',
                $includeIds,
            ),
            ListOfValueType::AdmissionChannels => $this->options(
                AdmissionChannel::query()->where('status', Status::ACTIVE),
                'channel_name',
                null,
                $includeIds,
            ),
            ListOfValueType::Relationships => $this->options(
                Relationship::query()->where('status', Status::ACTIVE),
                'relationship_name',
                null,
                $includeIds,
            ),
            ListOfValueType::StudentStatuses => $this->options(
                StudentStatus::query()->where('status', Status::ACTIVE),
                'status_name',
                null,
                $includeIds,
            ),
            ListOfValueType::NoteTypes => $this->options(
                NoteType::query()->where('status', Status::ACTIVE),
                'note',
                null,
                $includeIds,
            ),
            ListOfValueType::ImportTypes => $this->options(
                ImportType::query()->where('status', Status::ACTIVE),
                'type',
                null,
                $includeIds,
            ),
            ListOfValueType::HighSchools => $this->options(
                HighSchool::query()->where('status', Status::ACTIVE),
                'school_name',
                null,
                $includeIds,
            ),
            ListOfValueType::Provinces => $this->options(
                Province::query(),
                'province_name',
                null,
                $includeIds,
            ),
            ListOfValueType::Districts => $this->options(
                District::query()->where('province_id', $filters['province_id']),
                'district_name',
                null,
                $includeIds,
            ),
            ListOfValueType::Subdistricts => $this->options(
                Subdistrict::query()->where('district_id', $filters['district_id']),
                'subdistrict_name',
                null,
                $includeIds,
            ),
            ListOfValueType::SystemTeachers => $this->systemTeachers($filters),
            ListOfValueType::SystemDepartments => $this->options(
                SystemDepartment::query(),
                'th_name',
                'en_name',
                $includeIds,
            ),
            ListOfValueType::SystemFaculties => $this->options(
                SystemFaculty::query(),
                'th_name',
                'en_name',
                $includeIds,
            ),
            ListOfValueType::Curriculums => $this->curriculumOptions($filters),
            ListOfValueType::StudyPlans => $this->studyPlanOptions(
                (int) $filters['curriculum_id'],
                $includeIds,
            ),
        };
    }

    private function studyPlanOptions(int $curriculumId, array $includeIds = []): Collection
    {
        return collect($this->cmisApi->getCurriculumPlans($curriculumId))
            ->filter(fn (mixed $studyPlan): bool => is_array($studyPlan))
            ->filter(fn (array $studyPlan): bool => ($studyPlan['status'] ?? null) === 'activate'
                || in_array((int) ($studyPlan['id'] ?? 0), $includeIds, true))
            ->map(fn (array $studyPlan): array => [
                'id' => (int) ($studyPlan['id'] ?? 0),
                'name_th' => $studyPlan['name_th'] ?? null,
                'name_en' => $studyPlan['name_en'] ?? null,
            ])
            ->filter(fn (array $studyPlan): bool => $studyPlan['id'] > 0)
            ->values();
    }

    private function curriculumOptions(array $filters): Collection
    {
        $includeIds = array_map('intval', $filters['include_ids'] ?? []);

        return collect($this->cmisApi->getCurriculums())
            ->filter(fn (mixed $curriculum): bool => is_array($curriculum)
                && (($curriculum['status'] ?? null) === 'published'
                    || in_array((int) ($curriculum['id'] ?? 0), $includeIds, true)))
            ->map(fn (array $curriculum): array => [
                'id' => (int) ($curriculum['id'] ?? 0),
                'name_th' => $curriculum['name_th'] ?? null,
                'name_en' => $curriculum['name_en'] ?? null,
            ])
            ->values();
    }

    private function systemTeachers(array $filters): Collection
    {
        if (isset($filters['study_plan_id'])) {
            return $this->curriculumPersonnelOptions((int) $filters['study_plan_id']);
        }

        return $this->teacherOptions($this->teacherApi->getTeachers());
    }

    private function curriculumPersonnelOptions(int $studyPlanId): Collection
    {
        $studyPlan = $this->cmisApi->findStudyPlan($studyPlanId);
        $curriculumId = $studyPlan['curriculum_id'] ?? null;

        if (! is_numeric($curriculumId)) {
            throw ValidationException::withMessages([
                'study_plan_id' => 'The selected study plan does not have a curriculum.',
            ]);
        }

        try {
            $payload = $this->cmisApi->getCurriculumPersonnel((int) $curriculumId);
            $personnel = $payload['data'] ?? null;

            if (! is_array($personnel)) {
                throw new RuntimeException('CMIS curriculum personnel response does not contain a valid data array.');
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Unable to load curriculum personnel from CMIS.',
                previous: $exception,
            );
        }

        return $this->teacherOptions($personnel);
    }

    private function teacherOptions(array $teachers): Collection
    {
        return collect($teachers)
            ->filter(fn (mixed $teacher): bool => is_array($teacher))
            ->map(function (array $teacher): ?array {
                $id = $teacher['personnel_id'] ?? $teacher['id'] ?? null;
                $nameTh = $teacher['full_name'] ?? $teacher['full_name_th'] ?? null;

                if (! is_numeric($id) || ! is_scalar($nameTh) || trim((string) $nameTh) === '') {
                    return null;
                }

                return [
                    'id' => (int) $id,
                    'name_th' => (string) $nameTh,
                    'name_en' => isset($teacher['full_name_en']) && is_scalar($teacher['full_name_en'])
                        ? (string) $teacher['full_name_en']
                        : null,
                ];
            })
            ->filter()
            ->values();
    }

    private function options(
        Builder $query,
        string $nameThField,
        ?string $nameEnField = null,
        array $includeIds = [],
    ): Collection {
        $columns = ['id', $nameThField];

        if ($nameEnField !== null) {
            $columns[] = $nameEnField;
        }

        $items = $query
            ->orderBy($nameThField)
            ->orderBy('id')
            ->get($columns);

        if ($includeIds !== []) {
            $items = $items->merge(
                $query->getModel()::query()
                    ->whereKey($includeIds)
                    ->get($columns)
            )->unique(fn (Model $item): mixed => $item->getKey());
        }

        return $items
            ->sortBy([
                [$nameThField, 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn (Model $item): array => [
                'id' => (int) $item->getKey(),
                'name_th' => $item->getAttribute($nameThField),
                'name_en' => $nameEnField === null
                    ? null
                    : $item->getAttribute($nameEnField),
            ])
            ->values();
    }
}
