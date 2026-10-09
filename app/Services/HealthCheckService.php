<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PDO;
use Throwable;

/**
 * Builds the GET /api/health report: one entry per dependency, each with an
 * "ok" flag. Details (counts, error messages, container states) are only
 * included for callers that present config('health.token').
 */
class HealthCheckService
{
    public function isAuthorized(?string $token): bool
    {
        $expected = (string) config('health.token');

        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    /**
     * @return array{status: string, checked_at: string, checks: array<string, array<string, mixed>>}
     */
    public function report(bool $detailed): array
    {
        $checks = [
            'database' => $this->run(fn () => $this->database()),
            'migrations' => $this->run(fn () => $this->migrations()),
            'queue' => $this->run(fn () => $this->queue()),
            'storage' => $this->run(fn () => $this->storage()),
            'frontend' => $this->run(fn () => $this->frontend()),
        ];

        $healthy = collect($checks)->every(fn (array $check) => $check['ok']);

        $report = [
            'status' => $healthy ? 'ok' : 'down',
            'checked_at' => now()->toIso8601String(),
            'checks' => $detailed
                ? $checks
                : array_map(fn (array $check) => array_intersect_key($check, array_flip(['ok', 'skipped'])), $checks),
        ];

        if ($detailed) {
            $report['app'] = [
                'env' => config('app.env'),
                'build' => $this->readJson(base_path('build-info.json')),
            ];
            $report['containers'] = $this->readJson((string) config('health.status_file'));
        }

        return $report;
    }

    /**
     * @param  Closure(): array<string, mixed>  $check
     * @return array<string, mixed>
     */
    private function run(Closure $check): array
    {
        $started = microtime(true);

        try {
            $result = $check();
        } catch (Throwable $e) {
            $result = ['ok' => false, 'error' => $e->getMessage()];
        }

        return $result + ['ms' => (int) round((microtime(true) - $started) * 1000)];
    }

    private function database(): array
    {
        $connection = DB::connection();
        $connection->select('SELECT 1');

        return [
            'ok' => true,
            'driver' => $connection->getDriverName(),
            'database' => $connection->getDatabaseName(),
            'server_version' => $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION),
        ];
    }

    private function migrations(): array
    {
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            return ['ok' => false, 'error' => 'migrations table does not exist; php artisan migrate has never run'];
        }

        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
        $pending = array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));

        return ['ok' => $pending === [], 'pending' => $pending];
    }

    private function queue(): array
    {
        $name = config('queue.default');
        $config = config("queue.connections.{$name}", []);
        $workerSilence = QueueWorkerHeartbeat::secondsSinceLastBeat();
        $maxSilence = (int) config('health.queue_worker_max_silence');

        // sync runs jobs inside the request; there is no worker to watch.
        if (($config['driver'] ?? null) === 'sync') {
            return ['ok' => true, 'skipped' => true, 'driver' => 'sync'];
        }

        $result = [
            'ok' => $workerSilence !== null && $workerSilence <= $maxSilence,
            'driver' => $config['driver'] ?? null,
            'worker_last_seen_seconds_ago' => $workerSilence,
        ];

        if ($workerSilence === null) {
            $result['error'] = 'no queue worker has ever reported in';
        } elseif ($workerSilence > $maxSilence) {
            $result['error'] = "queue worker silent for {$workerSilence}s (limit {$maxSilence}s)";
        }

        if (($config['driver'] ?? null) === 'database') {
            $jobs = DB::connection($config['connection'] ?? null)->table($config['table'] ?? 'jobs');
            $oldestPending = (clone $jobs)->whereNull('reserved_at')->min('available_at');

            $result['pending'] = (clone $jobs)->whereNull('reserved_at')->count();
            $result['running'] = (clone $jobs)->whereNotNull('reserved_at')->count();
            $result['oldest_pending_seconds'] = $oldestPending === null ? null : max(0, time() - (int) $oldestPending);
        }

        $failed = config('queue.failed');
        if (in_array($failed['driver'] ?? null, ['database', 'database-uuids'], true)) {
            $result['failed'] = DB::connection($failed['database'] ?? null)->table($failed['table'] ?? 'failed_jobs')->count();
        }

        return $result;
    }

    private function storage(): array
    {
        $paths = [storage_path('app'), storage_path('framework/cache'), storage_path('framework/views')];
        $notWritable = array_values(array_filter($paths, fn (string $path) => ! is_writable($path)));

        return $notWritable === []
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'not writable: '.implode(', ', $notWritable)];
    }

    private function frontend(): array
    {
        $url = config('health.frontend_url');

        if (! $url) {
            return ['ok' => true, 'skipped' => true];
        }

        $response = Http::timeout(3)->get($url);

        return [
            'ok' => $response->successful(),
            'url' => $url,
            'http_status' => $response->status(),
        ];
    }

    private function readJson(string $path): mixed
    {
        if (! is_file($path)) {
            return null;
        }

        return json_decode((string) file_get_contents($path), true);
    }
}
