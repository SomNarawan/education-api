<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectPrerequisite extends Model
{
    protected $table = 'subject_prerequisites';

    protected $fillable = [
        'subject_id',
        'prerequisite_subject_id',
        'prerequisite_type',
        'note',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function prerequisiteSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'prerequisite_subject_id');
    }
}
