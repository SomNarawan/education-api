<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    protected $table = 'departments';

    public $timestamps = false;

    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }
}
