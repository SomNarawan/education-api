<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliation extends Model
{
    protected $table = 'affiliations';

    public $timestamps = false;

    protected $fillable = [
        'affiliation_name_th',
        'affiliation_name_en'
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'affiliation_id', 'id');
    }
}
