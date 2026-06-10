<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Relationship extends Model
{
    protected $table = 'relationships';
    
    public $timestamps = false;

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'guardian_relationship_id'
        );
    }
}