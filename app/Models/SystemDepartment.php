<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemDepartment extends Model
{
    protected $table = 'system_departments';

    public $timestamps = false;

    public function systemFaculty(): BelongsTo
    {
        return $this->belongsTo(SystemFaculty::class, 'system_faculty_id');
    }
}
