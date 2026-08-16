<?php

namespace App\Http\Controllers\Api;

use App\Models\ImportType;

class ImportTypeController extends MasterDataController
{
    protected string $modelClass = ImportType::class;

    protected string $nameField = 'type';

    protected int $nameMaxLength = 50;

    protected string $singularLabel = 'import type';

    protected string $pluralLabel = 'import types';
}
