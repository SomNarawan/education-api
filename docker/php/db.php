<?php

/*
 * Container helper for database startup tasks.
 *
 *   php db.php wait     Retry until the default connection answers, or time out.
 *   php db.php migrate  wait, take a database-level lock, run
 *                       `php artisan migrate --force`, release the lock.
 *
 * The lock (MySQL/MariaDB GET_LOCK, PostgreSQL advisory lock) belongs to this
 * process's connection, so if several containers start at once only one runs
 * migrations; the others wait and then find nothing left to migrate. If this
 * process dies, the database drops the connection and the lock with it.
 *
 * Progress and failures are also recorded with status.php, so they can be read
 * over HTTP at /_status.
 *
 * Env: DB_WAIT_TIMEOUT (seconds, default 60), MIGRATE_LOCK_TIMEOUT (seconds, default 300)
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

require __DIR__.'/status.php';

$basePath = getenv('APP_BASE_PATH') ?: '/var/www/html';

try {
    require $basePath.'/vendor/autoload.php';
    $app = require $basePath.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
} catch (Throwable $e) {
    record_status('boot_failed', 'Laravel failed to boot', (string) $e);
    throw $e;
}

function out(string $message): void
{
    fwrite(STDERR, '[db] '.$message.PHP_EOL);
}

/**
 * MySQL errors that retrying cannot fix: the server answered, so the problem
 * is configuration. Returns what to do about it, or null to keep waiting.
 */
function permanentDatabaseError(Throwable $e): ?string
{
    if (! preg_match('/SQLSTATE\[\w+\] \[(\d+)\]/', $e->getMessage(), $m)) {
        return null;
    }

    $config = config('database.connections.'.config('database.default'));
    $where = ($config['host'] ?? '?').':'.($config['port'] ?? '?');

    return match ((int) $m[1]) {
        1049 => "database '".($config['database'] ?? '?')."' does not exist on {$where}: create it and import the data (README > Database Setup), or fix DB_DATABASE",
        1044, 1045 => "MySQL on {$where} rejected user '".($config['username'] ?? '?')."': check DB_USERNAME/DB_PASSWORD and that the user may connect from the Docker network ('user'@'172.%')",
        default => null,
    };
}

function waitForDatabase(int $timeout): Connection
{
    $deadline = time() + $timeout;
    $attempt = 0;

    while (true) {
        $attempt++;

        try {
            $connection = DB::connection();
            $connection->select('SELECT 1');
            out("database is ready (attempt {$attempt})");

            return $connection;
        } catch (Throwable $e) {
            DB::purge();

            if (($problem = permanentDatabaseError($e)) !== null) {
                out("database misconfigured, not retrying: {$problem}");
                out($e->getMessage());
                record_status('db_misconfigured', $problem, $e->getMessage());
                exit(1);
            }

            if (time() >= $deadline) {
                out("database not reachable after {$timeout}s: ".$e->getMessage());
                record_status('db_unreachable', "database not reachable after {$timeout}s", $e->getMessage());
                exit(1);
            }

            out("waiting for database (attempt {$attempt}): ".$e->getMessage());
            record_status('waiting_db', "waiting for database (attempt {$attempt})", $e->getMessage());
            // Back off 1s, 2s, 4s, then every 5s.
            sleep(min(2 ** ($attempt - 1), 5));
        }
    }
}

/**
 * @return callable(): void  releases the lock
 */
function acquireMigrationLock(Connection $connection, int $timeout): callable
{
    $name = substr('laravel-migrate:'.$connection->getDatabaseName(), 0, 64);

    switch ($connection->getDriverName()) {
        case 'mysql':
        case 'mariadb':
            out("acquiring lock '{$name}' (timeout {$timeout}s)");
            $row = $connection->selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$name, $timeout]);

            if ((int) ($row->acquired ?? 0) !== 1) {
                out('could not acquire migration lock; another migration is still running');
                record_status('migrate_failed', 'could not acquire migration lock; another migration is still running');
                exit(1);
            }

            return fn () => $connection->selectOne('SELECT RELEASE_LOCK(?)', [$name]);

        case 'pgsql':
            $key = crc32($name);
            out("acquiring advisory lock {$key}");
            $connection->statement("SET lock_timeout = '".($timeout * 1000)."ms'");
            $connection->select('SELECT pg_advisory_lock(?)', [$key]);

            return fn () => $connection->select('SELECT pg_advisory_unlock(?)', [$key]);

        default:
            out('no locking support for driver '.$connection->getDriverName().'; running without lock');

            return fn () => null;
    }
}

$command = $argv[1] ?? '';
$waitTimeout = (int) (getenv('DB_WAIT_TIMEOUT') ?: 60);

switch ($command) {
    case 'wait':
        waitForDatabase($waitTimeout);
        exit(0);

    case 'migrate':
        $connection = waitForDatabase($waitTimeout);
        $release = acquireMigrationLock($connection, (int) (getenv('MIGRATE_LOCK_TIMEOUT') ?: 300));

        out('running php artisan migrate --force');
        record_status('migrating', 'running php artisan migrate --force');
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($basePath.'/artisan').' migrate --force --no-interaction 2>&1', $lines, $exitCode);
        $output = implode(PHP_EOL, $lines);
        fwrite(STDOUT, $output.PHP_EOL);

        $release();
        if ($exitCode === 0) {
            out('migrations finished');
            record_status('migrated', 'migrations finished', $output);
        } else {
            out("migrations FAILED (exit {$exitCode})");
            record_status('migrate_failed', "php artisan migrate failed (exit {$exitCode}); the app and the queue worker were not started", $output);
        }
        exit($exitCode);

    default:
        out('usage: php db.php wait|migrate');
        exit(2);
}
