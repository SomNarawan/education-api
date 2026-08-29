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
        'deleted_at',
    ];

    public function systemDepartment(): BelongsTo
    {
        return $this->belongsTo(SystemDepartment::class, 'department_id');
    }
}
