<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemDepartment extends Model
{
    use SoftDeletes;

    protected $table = 'system_departments';

    protected $fillable = [
        'th_name',
        'en_name',
        'th_short_name',
        'en_short_name',
        'system_faculty_id',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'sync_id',
    ];

    protected $casts = [
        'system_faculty_id' => 'integer',
        'sync_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function systemFaculty(): BelongsTo
    {
        return $this->belongsTo(SystemFaculty::class, 'system_faculty_id');
    }
}
