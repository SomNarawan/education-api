<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportType extends Model
{
    protected $table = 'import_types';

    protected $fillable = [
        'type',
        'status',
        'created_by',
        'updated_by',
    ];

    public function imports(): HasMany
    {
        return $this->hasMany(DataImport::class);
    }
}
