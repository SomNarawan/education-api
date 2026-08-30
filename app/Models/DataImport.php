<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataImport extends Model
{
    protected $table = 'imports';

    protected $fillable = [
        'import_type_id',
        'curriculum_id',
        'curriculum_name_th',
        'curriculum_plan_id',
        'curriculum_plan_name_th',
        'file_name',
        'file_result_path',
        'total_count',
        'success_count',
        'failed_count',
        'status',
        'error_message',
        'imported_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'import_type_id' => 'integer',
            'curriculum_id' => 'integer',
            'curriculum_plan_id' => 'integer',
            'total_count' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function importType(): BelongsTo
    {
        return $this->belongsTo(ImportType::class);
    }
}
