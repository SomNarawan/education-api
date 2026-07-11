<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculum extends Model
{
    protected $table = 'curriculums';

    protected $fillable = [
        'parent_id',
        'department_id',
        'code',
        'name_th',
        'name_en',
        'degree_full_th',
        'degree_short_th',
        'degree_full_en',
        'degree_short_en',
        'level',
        'duration_years',
        'note',
        'revision_year',
        'start_year',
        'end_year',
        'total_credits_min',
        'status',
    ];

    protected $casts = [
        'revision_year' => 'integer',
        'start_year' => 'integer',
        'end_year' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(CurriculumPlan::class, 'curriculum_id');
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(CurriculumDivision::class, 'curriculum_id');
    }
}
