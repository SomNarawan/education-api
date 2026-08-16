<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionChannel extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'admission_channels';

    protected $fillable = [
        'channel_name',
        'status',
        'created_by',
        'updated_by',
    ];
}
