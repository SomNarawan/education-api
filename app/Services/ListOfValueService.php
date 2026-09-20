<?php

namespace App\Services;

use App\Constants\Status;
use App\Contracts\CmisApi;
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

class ListOfValueService
{
    public function __construct(
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
            ListOfValueType::CurriculumPersonnel => $this->curriculumPersonnelOptions(
                (int) $filters['curriculum_id'],
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

    private function curriculumPersonnelOptions(int $curriculumId): Collection
    {
        return $this->personnelOptions(
            $this->cmisApi->getCurriculumPersonnel($curriculumId),
        );
    }

    private function personnelOptions(array $personnel): Collection
    {
        return collect($personnel)
            ->filter(fn (mixed $person): bool => is_array($person))
            ->map(function (array $person): ?array {
                $id = $person['external_id'] ?? null;
                $nameTh = $person['full_name'] ?? $person['full_name_th'] ?? null;

                if (! is_scalar($id) || trim((string) $id) === ''
                    || ! is_scalar($nameTh) || trim((string) $nameTh) === '') {
                    return null;
                }

                return [
                    'id' => (string) $id,
                    'name_th' => (string) $nameTh,
                    'name_en' => isset($person['full_name_en']) && is_scalar($person['full_name_en'])
                        ? (string) $person['full_name_en']
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
