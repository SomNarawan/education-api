<?php

namespace App\Http\Controllers\Api;

use App\Models\NoteType;

class NoteTypeController extends MasterDataController
{
    protected string $modelClass = NoteType::class;

    protected string $nameField = 'note';

    protected int $nameMaxLength = 255;

    protected string $singularLabel = 'note type';

    protected string $pluralLabel = 'note types';
}
