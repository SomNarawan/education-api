<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteType extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'note_types';

    protected $fillable = [
        'note',
        'status',
        'created_by',
        'updated_by',
    ];
}
