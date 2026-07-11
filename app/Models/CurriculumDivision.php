<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumDivision extends Model
{
    protected $table = 'curriculum_divisions';

    protected $fillable = [
        'curriculum_id',
        'parent_id',
        'plan_id',
        'division_type',
        'code',
        'name_th',
        'name_en',
        'min_credits',
        'required_credits',
        'min_hours',
        'min_weeks',
        'description_th',
        'description_en',
        'sort_order',
        'status',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(CurriculumDivisionSubject::class, 'division_id');
    }
}
