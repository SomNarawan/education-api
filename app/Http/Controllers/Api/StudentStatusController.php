<?php

namespace App\Http\Controllers\Api;

use App\Models\StudentStatus;

class StudentStatusController extends MasterDataController
{
    protected string $modelClass = StudentStatus::class;

    protected string $nameField = 'status_name';

    protected int $nameMaxLength = 50;

    protected string $singularLabel = 'student status';

    protected string $pluralLabel = 'student statuses';
}
