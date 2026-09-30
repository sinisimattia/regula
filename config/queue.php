<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Queue Backend
|--------------------------------------------------------------------------
|
| Code dispatches to "default", "high", "low" and "default-ordered" (PHP-23);
| QUEUE_DRIVER alone picks the backend behind all four.
|
| Drivers: "sync", "deferred", "background", "database", "redis", "sqs", "null"
|
*/

$driver = env('QUEUE_DRIVER', 'sync');

$connection = static fn (string $queue, string $sqsQueue, string $sqsDriver = 'sqs'): array => match ($driver) {
    'sqs' => [
        'driver' => $sqsDriver,
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'prefix' => env('SQS_PREFIX', 'https://sqs.eu-central-1.amazonaws.com/your-account-id'),
        'queue' => $sqsQueue,
        'suffix' => env('SQS_SUFFIX'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-central-1'),
        'after_commit' => false,
    ],
    'database' => [
        'driver' => 'database',
        'connection' => env('DB_QUEUE_CONNECTION'),
        'table' => env('DB_QUEUE_TABLE', 'jobs'),
        'queue' => $queue,
        'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
        'after_commit' => false,
    ],
    'redis' => [
        'driver' => 'redis',
        'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
        'queue' => $queue,
        'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
        'block_for' => null,
        'after_commit' => false,
    ],
    default => [
        'driver' => $driver,
    ],
};

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | The connection a job lands on when it does not pin one. Its backend is
    | QUEUE_DRIVER, like every connection below that code dispatches to.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | "deferred" and "background" run a job in-process after the response is
    | sent. "failover" pushes to the first connection in its list that accepts
    | the job.
    |
    */

    'connections' => [

        'default' => $connection('default', env('SQS_QUEUE', 'default')),

        'high' => $connection('high', env('SQS_QUEUE_HIGH', 'default')),

        'low' => $connection('low', env('SQS_QUEUE_LOW', 'default')),

        'default-ordered' => $connection(
            'default-ordered',
            env('SQS_QUEUE_DEFAULT_ORDERED', 'default-ordered.fifo'),
            'sqs-fifo',
        ),

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => explode(',', (string) env('QUEUE_FAILOVER_CONNECTIONS', 'default,deferred')),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],

];
