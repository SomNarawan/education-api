<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGuardianPhoneHistory extends Model
{
    public $timestamps = false;

    protected $table = 'student_guardian_phone_histories';

    protected $fillable = [
        'student_id',
        'old_phone',
        'new_phone',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
