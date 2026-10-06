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
 * Env: DB_WAIT_TIMEOUT (seconds, default 60), MIGRATE_LOCK_TIMEOUT (seconds, default 300)
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

$basePath = getenv('APP_BASE_PATH') ?: '/var/www/html';

require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function out(string $message): void
{
    fwrite(STDERR, '[db] '.$message.PHP_EOL);
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

            if (time() >= $deadline) {
                out("database not reachable after {$timeout}s: ".$e->getMessage());
                exit(1);
            }

            out("waiting for database (attempt {$attempt}): ".$e->getMessage());
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
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($basePath.'/artisan').' migrate --force --no-interaction', $exitCode);

        $release();
        out($exitCode === 0 ? 'migrations finished' : "migrations FAILED (exit {$exitCode})");
        exit($exitCode);

    default:
        out('usage: php db.php wait|migrate');
        exit(2);
}
