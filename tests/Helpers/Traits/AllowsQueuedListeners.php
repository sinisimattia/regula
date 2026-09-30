<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Illuminate\Support\Facades\Queue;

/**
 * Lets a test that mocks the `Queue` facade survive a queued listener firing.
 *
 * Declared before the test's own expectations, it absorbs the dispatcher's `connection(null)` and
 * lets a named connection fall through to whatever the test expects.
 */
trait AllowsQueuedListeners
{
    protected function allowQueuedListeners(): void
    {
        Queue::shouldReceive('connection')->with(null)->andReturnSelf();
        Queue::shouldReceive('pushOn')->andReturn(null);
        Queue::shouldReceive('push')->andReturn(null);
    }
}
