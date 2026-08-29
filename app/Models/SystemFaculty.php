<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemFaculty extends Model
{
    use SoftDeletes;

    protected $table = 'system_faculties';

    protected $fillable = [
        'th_name',
        'en_name',
        'th_short_name',
        'en_short_name',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'sync_id',
    ];

    protected $casts = [
        'sync_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
