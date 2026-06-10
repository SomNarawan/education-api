<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subdistrict extends Model
{
    protected $table = 'subdistricts';

    public $timestamps = false;

    public function district()
    {
        return $this->belongsTo(District::class, 'district_id');
    }
}
