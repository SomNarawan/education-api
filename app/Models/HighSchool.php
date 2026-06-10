<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HighSchool extends Model
{
    protected $table = 'high_schools';

    public $timestamps = false;

     public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Subdistrict::class, 'subdistrict_id');
    }
}
