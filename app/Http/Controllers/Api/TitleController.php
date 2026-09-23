<?php

namespace App\Http\Controllers\Api;

use App\Http\Responses\TitleResponse;
use App\Models\Title;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class TitleController extends MasterDataController
{
    protected string $modelClass = Title::class;

    protected string $nameField = 'title_name_th';

    protected int $nameMaxLength = 50;

    protected string $singularLabel = 'title';

    protected string $pluralLabel = 'titles';

    protected function writeRules(?int $ignoreId = null): array
    {
        return [
            'title_abbr_th' => ['required', 'string', 'max:50', Rule::unique('titles', 'title_abbr_th')->ignore($ignoreId)],
            'title_abbr_en' => ['required', 'string', 'max:50', Rule::unique('titles', 'title_abbr_en')->ignore($ignoreId)],
            'title_name_th' => ['required', 'string', 'max:50', Rule::unique('titles', 'title_name_th')->ignore($ignoreId)],
            'title_name_en' => ['required', 'string', 'max:50', Rule::unique('titles', 'title_name_en')->ignore($ignoreId)],
        ];
    }

    protected function responseData(Model $item): array
    {
        return (new TitleResponse($item))->resolve();
    }
}
