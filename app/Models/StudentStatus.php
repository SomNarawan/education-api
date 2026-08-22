<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentStatus extends Model
{
    protected $table = 'student_statuses';

    protected $fillable = [
        'status_name',
        'status',
        'created_by',
        'updated_by',
    ];
}
