<?php

namespace App\Services;

use App\Constants\Status;
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
use App\Models\SystemTeacher;
use App\Models\Title;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ListOfValueService
{
    public function get(ListOfValueType $type, array $filters = []): Collection
    {
        return match ($type) {
            ListOfValueType::Titles => $this->options(
                Title::query()->where('status', Status::ACTIVE),
                'title_name_th',
                'title_name_en'
            ),
            ListOfValueType::AdmissionChannels => $this->options(
                AdmissionChannel::query()->where('status', Status::ACTIVE),
                'channel_name'
            ),
            ListOfValueType::Relationships => $this->options(
                Relationship::query()->where('status', Status::ACTIVE),
                'relationship_name'
            ),
            ListOfValueType::StudentStatuses => $this->options(
                StudentStatus::query()->where('status', Status::ACTIVE),
                'status_name'
            ),
            ListOfValueType::NoteTypes => $this->options(
                NoteType::query()->where('status', Status::ACTIVE),
                'note'
            ),
            ListOfValueType::ImportTypes => $this->options(
                ImportType::query()->where('status', Status::ACTIVE),
                'type'
            ),
            ListOfValueType::HighSchools => $this->options(
                HighSchool::query()->where('status', Status::ACTIVE),
                'school_name'
            ),
            ListOfValueType::Provinces => $this->options(
                Province::query(),
                'province_name'
            ),
            ListOfValueType::Districts => $this->options(
                District::query()->where('province_id', $filters['province_id']),
                'district_name'
            ),
            ListOfValueType::Subdistricts => $this->options(
                Subdistrict::query()->where('district_id', $filters['district_id']),
                'subdistrict_name'
            ),
            ListOfValueType::SystemTeachers => $this->systemTeachers($filters),
            ListOfValueType::SystemDepartments => $this->options(
                SystemDepartment::query(),
                'th_name',
                'en_name'
            ),
            ListOfValueType::SystemFaculties => $this->options(
                SystemFaculty::query(),
                'th_name',
                'en_name'
            ),
        };
    }

    private function systemTeachers(array $filters): Collection
    {
        $query = SystemTeacher::query()
            ->when(
                isset($filters['department_id']),
                fn (Builder $query) => $query->where('department_id', $filters['department_id'])
            );

        return $this->options($query, 'full_name_th');
    }

    private function options(
        Builder $query,
        string $nameThField,
        ?string $nameEnField = null
    ): Collection {
        $columns = ['id', $nameThField];

        if ($nameEnField !== null) {
            $columns[] = $nameEnField;
        }

        return $query
            ->orderBy($nameThField)
            ->orderBy('id')
            ->get($columns)
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
