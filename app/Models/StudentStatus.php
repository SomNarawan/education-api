<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentStatus extends Model
{
    public const STATUS_STUDYING = 'กำลังศึกษา';

    protected $table = 'student_statuses';

    public $timestamps = false;
}
