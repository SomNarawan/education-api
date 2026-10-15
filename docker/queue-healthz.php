<?php

/**
 * Status endpoint of the queue container (php -S 0.0.0.0:3012, docker/entrypoint.sh).
 *
 * GET /healthz: 200 while the worker's heartbeat is fresh, 503 when it has
 * gone silent - the same rule /api/health uses for its "queue" check. The
 * worker does not beat while a job runs, so a job longer than
 * HEALTH_QUEUE_WORKER_MAX_SILENCE also shows as 503 until it finishes.
 */

use App\Services\QueueWorkerHeartbeat;
use Illuminate\Contracts\Console\Kernel;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/healthz') {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Not found. Use /healthz.']), "\n";

    return;
}

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$silence = QueueWorkerHeartbeat::secondsSinceLastBeat();
$maxSilence = (int) config('health.queue_worker_max_silence');
$ok = $silence !== null && $silence <= $maxSilence;

http_response_code($ok ? 200 : 503);
echo json_encode([
    'status' => $ok ? 'ok' : 'error',
    'service' => 'queue',
    'message' => match (true) {
        $ok => 'queue worker ทำงานปกติ',
        $silence === null => 'queue worker ยังไม่เคยรายงานตัว - อาจยังเริ่มไม่เสร็จ หรือเริ่มไม่สำเร็จ',
        default => "queue worker เงียบไป {$silence} วินาที (เกิน {$maxSilence}) - ค้าง หรือกำลังทำงานที่ใช้เวลานาน",
    },
    'worker_last_seen_seconds_ago' => $silence,
    'max_silence_seconds' => $maxSilence,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
