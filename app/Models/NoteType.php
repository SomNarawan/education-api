<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteType extends Model
{
    protected $table = 'note_types';

    protected $fillable = [
        'note',
        'status',
        'created_by',
        'updated_by',
    ];
}
