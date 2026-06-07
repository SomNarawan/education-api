<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Title extends Model
{
    protected $table = 'titles';

    public $timestamps = false;

    protected $fillable = [
        'title_abbr_th',
        'title_abbr_en',
        'title_name_th',
        'title_name_en',
    ];
}
