<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEntry extends Model
{
    protected $table = 'plan_entries';

    protected $fillable = [
        'plan_term_id',
        'item_type',
        'subject_id',
        'division_id',
        'display_name_th',
        'display_name_en',
        'planned_credits',
        'note',
        'sort_order',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(PlanTerm::class, 'plan_term_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(CurriculumDivision::class, 'division_id');
    }
}
