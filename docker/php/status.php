<?php

/*
 * Records what this container is doing in a JSON file on the shared storage
 * volume, one entry per CONTAINER_ROLE (migrate, app, queue). nginx serves the
 * file at /_status and /api/health includes it, so the state of every
 * container is visible over HTTP even when PHP cannot serve requests.
 *
 *   php status.php <state> <message> [detail]
 *
 * Standalone on purpose (no Laravel bootstrap): it has to work when Laravel
 * itself fails to boot. Never fails the caller.
 *
 * Env: CONTAINER_ROLE, HEALTH_STATUS_FILE (default storage/app/status/status.json)
 */

declare(strict_types=1);

const STATUS_OK_STATES = ['running', 'migrated'];

function record_status(string $state, string $message, string $detail = ''): void
{
    try {
        $file = getenv('HEALTH_STATUS_FILE') ?: '/var/www/html/storage/app/status/status.json';
        $role = getenv('CONTAINER_ROLE') ?: 'unknown';
        $dir = dirname($file);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("cannot create {$dir}");
        }

        $password = (string) getenv('DB_PASSWORD');
        if ($password !== '') {
            $detail = str_replace($password, '***', $detail);
        }

        // Several containers write to the same file; serialise on a lock file
        // and swap the file in with rename() so nginx never reads half of it.
        $lock = fopen($file.'.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException("cannot lock {$file}");
        }

        try {
            $all = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            $all = is_array($all) ? $all : [];

            $all[$role] = [
                'state' => $state,
                'ok' => in_array($state, STATUS_OK_STATES, true),
                'message' => $message,
                'detail' => $detail === '' ? null : mb_substr($detail, -4000),
                'container' => gethostname() ?: null,
                'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ];
            ksort($all);

            $tmp = $file.'.'.getmypid().'.tmp';
            file_put_contents($tmp, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL);
            chmod($tmp, 0644);
            rename($tmp, $file);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    } catch (Throwable $e) {
        fwrite(STDERR, '[status] could not record status: '.$e->getMessage().PHP_EOL);
    }
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    record_status($argv[1] ?? 'unknown', $argv[2] ?? '', $argv[3] ?? '');
}
