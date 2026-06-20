<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentMap extends Model
{
    protected $table = 'department_maps';

    public $timestamps = false;

    protected $fillable = [
        'id_in',
        'id_out',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'id_in');
    }
}
