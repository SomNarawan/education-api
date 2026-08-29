<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Model;

class Sync extends Model
{
    public const TYPE_SYSTEM_FACULTY = 1;

    public const TYPE_SYSTEM_DEPARTMENT = 2;

    public const TYPE_SYSTEM_TEACHER = 3;

    protected $table = 'syncs';

    protected $fillable = [
        'sync_type',
        'synced_count',
        'deleted_count',
        'skipped_count',
        'status',
        'error_message',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sync_type' => 'integer',
        'synced_count' => 'integer',
        'deleted_count' => 'integer',
        'skipped_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function start(int $syncType, string $actor): self
    {
        return self::create([
            'sync_type' => $syncType,
            'synced_count' => 0,
            'deleted_count' => 0,
            'skipped_count' => 0,
            'status' => Status::RUNNING,
            'error_message' => null,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);
    }

    public function markAsSuccess(
        int $syncedCount,
        int $inactiveCount,
        int $skippedCount,
        string $actor
    ): self {
        $this->update([
            'synced_count' => $syncedCount,
            'deleted_count' => $inactiveCount,
            'skipped_count' => $skippedCount,
            'status' => Status::SUCCESS,
            'error_message' => null,
            'updated_by' => $actor,
        ]);

        return $this->refresh();
    }

    public function markAsFailed(string $errorMessage, string $actor): self
    {
        $this->update([
            'status' => Status::FAILED,
            'error_message' => $errorMessage,
            'updated_by' => $actor,
        ]);

        return $this->refresh();
    }
}
