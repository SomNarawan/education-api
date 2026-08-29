<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemDepartment extends Model
{
    use HasActiveStatus;

    protected $table = 'system_departments';

    protected $fillable = [
        'th_name',
        'en_name',
        'th_short_name',
        'en_short_name',
        'system_faculty_id',
        'created_by',
        'updated_by',
        'status',
        'sync_id',
    ];

    protected $casts = [
        'system_faculty_id' => 'integer',
        'sync_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function systemFaculty(): BelongsTo
    {
        return $this->belongsTo(SystemFaculty::class, 'system_faculty_id');
    }
}
