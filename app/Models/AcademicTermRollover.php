<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicTermRollover extends Model
{
    protected $fillable = [
        'transition_summary',
        'affected_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transition_summary' => 'array',
            'affected_count' => 'integer',
        ];
    }
}
