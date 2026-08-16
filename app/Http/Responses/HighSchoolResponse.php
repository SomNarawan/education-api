<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class HighSchoolResponse extends JsonResource
{
    public function toArray($request): array
    {
        $subdistrict = $this->subdistrict;
        $district = $subdistrict?->district;
        $province = $district?->province;

        return [
            'id' => (int) $this->id,
            'school_name' => $this->school_name,
            'province_id' => $province === null ? null : (int) $province->id,
            'province_name' => $province?->province_name,
            'district_id' => $district === null ? null : (int) $district->id,
            'district_name' => $district?->district_name,
            'subdistrict_id' => (int) $this->subdistrict_id,
            'subdistrict_name' => $subdistrict?->subdistrict_name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'created_by' => $this->created_by,
            'updated_at' => $this->updated_at,
            'updated_by' => $this->updated_by,
        ];
    }
}
