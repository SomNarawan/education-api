<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;

class TitleWriteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title_abbr_th' => ['required', 'string', 'max:50'],
            'title_abbr_en' => ['required', 'string', 'max:50'],
            'title_name_th' => ['required', 'string', 'max:50'],
            'title_name_en' => ['required', 'string', 'max:50'],
        ];
    }
}
