<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    protected $table = 'teachers';

    protected $fillable = [
        'teacher_code',
        'title_id',
        'first_name_th',
        'last_name_th',
        'first_name_en',
        'last_name_en',
        'phone',
        'email',
        'department_id',
        'deleted_at',
        'is_deleted',
    ];

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'teacher_id', 'id');
    }
}
