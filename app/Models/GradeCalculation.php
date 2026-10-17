<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeCalculation extends Model
{
    protected $fillable = [
        'student_id',
        'study_year',
        'semester',
        'semester_order',
        'current_gpax',
        'current_credits',
        'term_gpa',
        'term_credits',
        'cumulative_gpa',
        'cumulative_credits',
        'courses',
    ];

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'study_year' => 'integer',
            'semester_order' => 'integer',
            'current_gpax' => 'decimal:4',
            'current_credits' => 'integer',
            'term_gpa' => 'decimal:4',
            'term_credits' => 'integer',
            'cumulative_gpa' => 'decimal:4',
            'cumulative_credits' => 'integer',
            'courses' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }
}
