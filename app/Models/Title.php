<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'titles';

    protected $fillable = [
        'title_abbr_th',
        'title_abbr_en',
        'title_name_th',
        'title_name_en',
        'status',
        'created_by',
        'updated_by',
    ];
}
