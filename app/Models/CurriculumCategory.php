<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumCategory extends Model
{
    protected $table = 'curriculum_categories';

    protected $fillable = [
        'curriculum_id',
        'parent_id',
        'category_type',
        'code',
        'name_th',
        'name_en',
        'course_source_type',
        'ku_subject_category_id',
        'description_th',
        'description_en',
        'status',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
