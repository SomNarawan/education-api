<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Lets /api/health tell whether a queue worker is alive. The worker calls
 * beat() on every loop (Illuminate\Queue\Events\Looping); the timestamp goes
 * into the shared cache store so the web container can read it.
 */
class QueueWorkerHeartbeat
{
    private const CACHE_KEY = 'health:queue-worker-heartbeat';

    // Write at most this often; the worker loops every few seconds.
    private const WRITE_INTERVAL = 15;

    private static int $lastWrite = 0;

    public static function beat(): void
    {
        $now = time();

        if ($now - self::$lastWrite < self::WRITE_INTERVAL) {
            return;
        }

        try {
            Cache::forever(self::CACHE_KEY, $now);
            self::$lastWrite = $now;
        } catch (Throwable) {
            // The worker itself will report the broken store; never fail the loop.
        }
    }

    /**
     * Seconds since the last heartbeat, or null if none was ever recorded.
     */
    public static function secondsSinceLastBeat(): ?int
    {
        $last = Cache::get(self::CACHE_KEY);

        return $last === null ? null : max(0, time() - (int) $last);
    }
}
