<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAcademicPeriodSync extends Model
{
    protected $fillable = [
        'reference_date',
        'is_international',
        'academic_year',
        'term_code',
        'term_name_th',
        'total_students',
        'updated_count',
        'skipped_count',
        'failed_count',
        'status',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'reference_date' => 'date',
        'is_international' => 'boolean',
        'academic_year' => 'integer',
        'total_students' => 'integer',
        'updated_count' => 'integer',
        'skipped_count' => 'integer',
        'failed_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
