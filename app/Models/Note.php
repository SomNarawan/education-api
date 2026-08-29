<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use SoftDeletes;

    protected $table = 'notes';

    const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'note_type_id',
        'remark',
        'created_by',
        'deleted_by',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'note_type_id' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function noteType(): BelongsTo
    {
        return $this->belongsTo(NoteType::class, 'note_type_id');
    }
}
