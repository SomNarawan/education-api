<?php

namespace App\Http\Responses\PortalMain;

use Illuminate\Http\Resources\Json\JsonResource;

class PortalMainStudentResponse extends JsonResource
{
    public function __construct(mixed $resource, private readonly ?string $nontriId = null)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'nontriId' => $this->nontriId ?? $this->student_code,
            'name' => $this->first_name_th,
            'surname' => $this->last_name_th,
            'kuMail' => $this->email,
            'agency' => $this->systemDepartment?->th_name,
        ];
    }
}
