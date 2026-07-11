<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumPlan extends Model
{
    protected $table = 'curriculum_plans';

    protected $fillable = [
        'curriculum_id',
        'code',
        'name_th',
        'name_en',
        'description_th',
        'description_en',
        'sort_order',
        'status',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }

    public function terms(): HasMany
    {
        return $this->hasMany(PlanTerm::class, 'plan_id');
    }
}
