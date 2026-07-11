<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $table = 'subjects';

    protected $fillable = [
        'code',
        'name_th',
        'name_en',
        'credits',
        'lecture_hours',
        'lab_hours',
        'self_study_hours',
        'description_th',
        'description_en',
        'ku_subject_category_id',
        'campus_id',
        'status',
        'year_level_no',
        'book_category_no',
        'code_sequence_no',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

    public function prerequisites(): HasMany
    {
        return $this->hasMany(SubjectPrerequisite::class, 'subject_id');
    }
}
