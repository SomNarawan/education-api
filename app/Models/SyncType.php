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

    protected $casts = [
        'synced_count' => 'integer',
        'deleted_count' => 'integer',
        'skipped_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
