<?php

declare(strict_types=1);

namespace App\Providers;

use App\Queue\Connectors\SqsFifoConnector;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\ServiceProvider;

class QueueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var QueueFactory $queue */
        $queue = $this->app['queue'];

        $queue->extend('sqs-fifo', fn () => new SqsFifoConnector());
    }
}
