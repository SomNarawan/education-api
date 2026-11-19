<?php

namespace App\Http\Controllers\Api;

use App\Models\Relationship;

class RelationshipController extends MasterDataController
{
    protected string $modelClass = Relationship::class;

    protected string $nameField = 'relationship_name';

    protected int $nameMaxLength = 50;
}
