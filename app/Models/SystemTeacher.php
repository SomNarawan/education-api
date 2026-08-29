<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemTeacher extends Model
{
    use HasActiveStatus;

    protected $table = 'system_teachers';

    protected $fillable = [
        'nontri_id',
        'full_name_th',
        'department_id',
        'created_by',
        'updated_by',
        'status',
        'sync_id',
    ];

    protected $casts = [
        'department_id' => 'integer',
        'sync_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function systemDepartment(): BelongsTo
    {
        return $this->belongsTo(SystemDepartment::class, 'department_id');
    }
}
