<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncType extends Model
{
    protected $table = 'sync_types';

    public $timestamps = false;

    protected $fillable = [
        'sync_type',
    ];
}
