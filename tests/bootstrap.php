<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/*
 * A second suite waits here instead of dropping the schema the first is still using (TEST-10).
 */
$connection = (string) config('database.default');
$database = (string) config("database.connections.$connection.database");
$lockPath = sys_get_temp_dir().'/phpunit-schema-'.sha1($connection.'/'.$database).'.lock';

$schemaLock = fopen($lockPath, 'c');

if ($schemaLock === false) {
    throw new RuntimeException("Could not open the test schema lock at $lockPath.");
}

if (! flock($schemaLock, LOCK_EX)) {
    throw new RuntimeException("Could not acquire the test schema lock at $lockPath.");
}

/*
 * Held, not merely taken: releasing it after the migration would let a second run start
 * rebuilding the schema while this one is still using it, which is the same failure a
 * step later.
 */
$GLOBALS['__testSchemaLock'] = $schemaLock;

$app->make(Kernel::class)->call('migrate:fresh', ['--force' => true]);

$app->flush();
unset($app);

/*
 * Booting the kernel installed global error handlers that flushing does not remove, and PHPUnit
 * marks every test risky while they linger. Hand the stack back the way it was found.
 */
restore_exception_handler();
restore_error_handler();
