<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PDO;
use Throwable;

/**
 * Builds the GET /api/health report: one entry per dependency, each with an
 * "ok" flag. Details (counts, error messages) are only
 * included for callers that present config('health.token').
 *
 * Critical checks (this API's own dependencies) turn the status "down" (503).
 * Non-critical ones (services we only call out to) turn it "degraded" but keep
 * 200, so an outage elsewhere never makes this API look dead to a monitor.
 */
class HealthCheckService
{
    private const CRITICAL = ['database', 'migrations', 'queue', 'storage'];

    public function isAuthorized(?string $token): bool
    {
        $expected = (string) config('health.token');

        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    /**
     * @return array{status: string, message: string, checked_at: string, checks: array<string, array<string, mixed>>}
     */
    public function report(bool $detailed): array
    {
        $checks = [
            'database' => $this->run(fn () => $this->database()),
            'migrations' => $this->run(fn () => $this->migrations()),
            'queue' => $this->run(fn () => $this->queue()),
            'storage' => $this->run(fn () => $this->storage()),
            'frontend' => $this->run(fn () => $this->frontend()),
            'cmis' => $this->cached('cmis', fn () => $this->cmis()),
            'personnel_api' => $this->cached('personnel_api', fn () => $this->personnelApi()),
        ];

        foreach ($checks as $name => &$check) {
            $check['critical'] = in_array($name, self::CRITICAL, true);
        }
        unset($check);

        $failed = collect($checks)->reject(fn (array $check) => $check['ok']);
        $status = match (true) {
            $failed->contains('critical', true) => 'down',
            $failed->isNotEmpty() => 'degraded',
            default => 'ok',
        };

        $report = [
            'status' => $status,
            'message' => $this->message($status, $failed->keys()->all()),
            'checked_at' => now()->toIso8601String(),
            'checks' => $detailed
                ? $checks
                : array_map(fn (array $check) => array_intersect_key($check, array_flip(['ok', 'skipped', 'critical'])), $checks),
        ];

        if ($detailed) {
            $report['app'] = ['env' => config('app.env')];
        }

        return $report;
    }

    /**
     * One line telling a person reading the response what the status means.
     * Only names checks, which callers without a token already see.
     *
     * @param  list<string>  $failed
     */
    private function message(string $status, array $failed): string
    {
        $critical = array_values(array_intersect($failed, self::CRITICAL));
        $others = array_values(array_diff($failed, self::CRITICAL));

        return match ($status) {
            'ok' => 'All checks passed. The API is working normally.',
            'degraded' => 'The API is working, but non-critical services are failing: '.implode(', ', $others)
                .'. Features that depend on them may not work.',
            'down' => 'Critical checks failed: '.implode(', ', $critical)
                .'. The API cannot serve requests normally.'
                .($others === [] ? '' : ' Also failing: '.implode(', ', $others).'.'),
        };
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

    /**
     * Like run(), but reuses the result for config('health.external_cache_seconds')
     * so frequent probes of this public endpoint don't hammer other services.
     *
     * @param  Closure(): array<string, mixed>  $check
     * @return array<string, mixed>
     */
    private function cached(string $key, Closure $check): array
    {
        $ttl = (int) config('health.external_cache_seconds');

        if ($ttl <= 0) {
            return $this->run($check);
        }

        try {
            return Cache::remember("health:check:{$key}", $ttl, fn () => $this->run($check) + ['checked_at' => now()->toIso8601String()]);
        } catch (Throwable) {
            // A broken cache store must not hide the dependency's own state.
            return $this->run($check);
        }
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

        // The database is loaded from an SQL dump (docker/db), not built by
        // php artisan migrate, so there may be no migrations table to compare.
        if (! $migrator->repositoryExists()) {
            return ['ok' => true, 'skipped' => true, 'note' => 'no migrations table; schema comes from the SQL dump'];
        }

        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
        $pending = array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));

        return ['ok' => $pending === [], 'pending' => $pending];
    }

    private function queue(): array
    {
        $name = config('queue.default');
        $config = config("queue.connections.{$name}", []);

        // sync runs jobs inside the request; there is no worker to watch.
        if (($config['driver'] ?? null) === 'sync') {
            return ['ok' => true, 'skipped' => true, 'driver' => 'sync'];
        }

        $workerSilence = QueueWorkerHeartbeat::secondsSinceLastBeat();
        $maxSilence = (int) config('health.queue_worker_max_silence');

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

    private function cmis(): array
    {
        $baseUrl = config('cmis.url');

        if (! $baseUrl) {
            return ['ok' => true, 'skipped' => true];
        }

        return $this->probe(
            $this->joinUrl($baseUrl, config('cmis.base_path'), config('cmis.endpoints.curriculums')),
            (bool) config('cmis.verify_ssl', true)
        );
    }

    private function personnelApi(): array
    {
        $baseUrl = config('services.personnel_api.url');

        if (! $baseUrl) {
            return ['ok' => true, 'skipped' => true];
        }

        // PersonnelApiService does not verify SSL either.
        return $this->probe(
            $this->joinUrl($baseUrl, config('services.personnel_api.base_path'), config('services.personnel_api.endpoints.all_faculty')),
            false
        );
    }

    private function probe(string $url, bool $verifySsl): array
    {
        $response = Http::withOptions(['verify' => $verifySsl])
            ->acceptJson()
            ->timeout((int) config('health.external_timeout'))
            ->get($url);

        return [
            'ok' => $response->successful(),
            'url' => $url,
            'http_status' => $response->status(),
        ] + ($response->successful() ? [] : ['error' => "HTTP {$response->status()}"]);
    }

    private function joinUrl(string $baseUrl, ?string ...$parts): string
    {
        $parts = array_map(fn (?string $part) => trim((string) $part, '/'), $parts);

        return implode('/', array_filter([rtrim($baseUrl, '/'), ...$parts], fn (string $part) => $part !== ''));
    }
}
