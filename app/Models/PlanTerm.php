<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanTerm extends Model
{
    protected $table = 'plan_terms';

    protected $fillable = [
        'plan_id',
        'year_no',
        'term_no',
        'term_label_th',
        'term_label_en',
        'total_credits',
        'note',
        'sort_order',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CurriculumPlan::class, 'plan_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PlanEntry::class, 'plan_term_id');
    }
}
