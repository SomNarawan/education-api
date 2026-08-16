<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Relationship extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'relationships';

    protected $fillable = [
        'relationship_name',
        'status',
        'created_by',
        'updated_by',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'guardian_relationship_id');
    }
}
