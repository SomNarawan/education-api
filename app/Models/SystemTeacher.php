<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemTeacher extends Model
{
    use SoftDeletes;

    protected $table = 'system_teachers';

    protected $fillable = [
        'nontri_id',
        'full_name_th',
        'department_id',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'sync_id',
    ];

    protected $casts = [
        'department_id' => 'integer',
        'sync_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function systemDepartment(): BelongsTo
    {
        return $this->belongsTo(SystemDepartment::class, 'department_id');
    }
}
