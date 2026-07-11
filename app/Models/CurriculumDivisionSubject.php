<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumDivisionSubject extends Model
{
    protected $table = 'curriculum_division_subjects';

    protected $fillable = [
        'curriculum_id',
        'division_id',
        'subject_id',
        'plan_id',
        'link_type',
        'is_required',
        'min_grade',
        'note',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(CurriculumDivision::class, 'division_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CurriculumPlan::class, 'plan_id');
    }
}
