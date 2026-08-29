<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class District extends Model
{
    protected $table = 'districts';

    public $timestamps = false;

    protected $fillable = [
        'district_name',
        'province_id',
    ];

    protected $casts = [
        'province_id' => 'integer',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }
}
