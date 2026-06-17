<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteType extends Model
{
    protected $table = 'note_types';

    public $timestamps = false;

    protected $fillable = [
        'note'
    ];
}