<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Model;

class SystemFaculty extends Model
{
    use HasActiveStatus;

    protected $table = 'system_faculties';

    protected $fillable = [
        'th_name',
        'en_name',
        'th_short_name',
        'en_short_name',
        'created_by',
        'updated_by',
        'status',
        'sync_id',
    ];

    protected $casts = [
        'sync_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
