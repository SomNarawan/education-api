<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use SoftDeletes;

    protected $table = 'teachers';

    protected $fillable = [
        'nontri_id',
        'full_name_th',
        'department_id',
        'deleted_at',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
