<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentIndexResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'title' => $this->title,
            'first_name_th' => $this->first_name_th,
            'last_name_th' => $this->last_name_th,
            'first_name_en' => $this->first_name_en,
            'last_name_en' => $this->last_name_en,
            'phone' => $this->phone,
            'email' => $this->email,
            'student_status_id' => $this->student_status_id,
        ];
    }
}
