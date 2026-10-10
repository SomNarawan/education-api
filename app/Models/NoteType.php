<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteType extends Model
{
    public const OTHER_NAME = 'อื่นๆ';

    protected $table = 'note_types';

    protected $fillable = [
        'note',
        'status',
        'created_by',
        'updated_by',
    ];

    public static function isOther(int $id): bool
    {
        return static::query()
            ->whereKey($id)
            ->where('note', self::OTHER_NAME)
            ->exists();
    }
}
