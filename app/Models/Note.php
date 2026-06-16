<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use SoftDeletes;

    protected $table = 'notes';

    const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'note',
        'deleted_at'
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}