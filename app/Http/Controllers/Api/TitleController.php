<?php

namespace App\Http\Controllers\Api;

use App\Http\Responses\TitleResponse;
use App\Models\Title;
use Illuminate\Database\Eloquent\Model;

class TitleController extends MasterDataController
{
    protected string $modelClass = Title::class;

    protected string $nameField = 'title_name_th';

    protected int $nameMaxLength = 50;

    protected string $singularLabel = 'title';

    protected string $pluralLabel = 'titles';

    protected function writeRules(): array
    {
        return [
            'title_abbr_th' => ['required', 'string', 'max:50'],
            'title_abbr_en' => ['required', 'string', 'max:50'],
            'title_name_th' => ['required', 'string', 'max:50'],
            'title_name_en' => ['required', 'string', 'max:50'],
        ];
    }

    protected function responseData(Model $item): array
    {
        return (new TitleResponse($item))->resolve();
    }
}
