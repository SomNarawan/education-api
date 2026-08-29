<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subdistrict extends Model
{
    protected $table = 'subdistricts';

    public $timestamps = false;

    protected $fillable = [
        'subdistrict_name',
        'postal_code',
        'district_id',
    ];

    protected $casts = [
        'district_id' => 'integer',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }
}
