<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use App\Queue\Concerns\UsesOrderedQueue;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * @covers \App\Queue\Concerns\UsesOrderedQueue
 */
class UsesOrderedQueueTest extends UnitTestCase
{
    #[Test]
    public function connection_property_defaults_to_default_ordered(): void
    {
        $consumer = $this->makeConsumer();

        $this->assertSame('default-ordered', $consumer->connection);
    }

    #[Test]
    public function messageDeduplicationId_returns_a_non_empty_string(): void
    {
        $consumer = $this->makeConsumer();

        $id = $consumer->messageDeduplicationId();

        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }

    #[Test]
    public function messageDeduplicationId_returns_same_value_on_repeated_calls(): void
    {
        $consumer = $this->makeConsumer();

        $firstCall = $consumer->messageDeduplicationId();
        $secondCall = $consumer->messageDeduplicationId();

        $this->assertSame($firstCall, $secondCall);
    }

    #[Test]
    public function messageDeduplicationId_returns_different_values_for_different_instances(): void
    {
        $instanceA = $this->makeConsumer();
        $instanceB = $this->makeConsumer();

        $this->assertNotSame(
            $instanceA->messageDeduplicationId(),
            $instanceB->messageDeduplicationId(),
        );
    }

    private function makeConsumer(): object
    {
        return new class () {
            use UsesOrderedQueue;
        };
    }
}
