<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentStatus extends Model
{
    public const STATUS_STUDYING = 'กำลังศึกษา';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'student_statuses';

    protected $fillable = [
        'status_name',
        'status',
        'created_by',
        'updated_by',
    ];
}
