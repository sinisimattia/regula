<?php

declare(strict_types=1);

namespace App\Queue;

use App\Queue\Contracts\HasMessageGroup;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Queue\SqsQueue;
use LogicException;
use RuntimeException;

class SqsFifoQueue extends SqsQueue
{
    public function push($job, $data = '', $queue = null): mixed
    {
        $options = $this->extractFifoOptions($job);

        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->default, $data),
            $queue,
            null,
            fn ($payload, $queue) => $this->pushRaw($payload, $queue, $options),
        );
    }

    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        if (! isset($options['MessageGroupId'], $options['MessageDeduplicationId'])) {
            throw new RuntimeException(
                'FIFO queue requires MessageGroupId and MessageDeduplicationId. Job must implement '
                . HasMessageGroup::class . '.',
            );
        }

        return $this->sqs->sendMessage([
            'QueueUrl' => $this->getQueue($queue),
            'MessageBody' => $payload,
            'MessageGroupId' => $options['MessageGroupId'],
            'MessageDeduplicationId' => $options['MessageDeduplicationId'],
        ])->get('MessageId');
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        if ($this->secondsUntil($delay) > 0) {
            throw new LogicException(
                'SQS FIFO queues do not support per-message delays. Use the queue-level DelaySeconds setting instead.',
            );
        }

        return $this->push($job, $data, $queue);
    }

    /**
     * @param  array<int, mixed>  $jobs
     */
    public function bulk($jobs, $data = '', $queue = null): void
    {
        foreach ($jobs as $job) {
            $this->push($job, $data, $queue);
        }
    }

    /**
     * @return array{MessageGroupId: string, MessageDeduplicationId: string}
     */
    private function extractFifoOptions(mixed $job): array
    {
        $candidate = $job instanceof BroadcastEvent ? $job->event : $job;

        if (! $candidate instanceof HasMessageGroup) {
            $type = is_object($candidate) ? $candidate::class : gettype($candidate);

            throw new RuntimeException(sprintf(
                'Jobs pushed to a FIFO queue must implement %s. Got: %s',
                HasMessageGroup::class,
                $type,
            ));
        }

        return [
            'MessageGroupId' => $candidate->messageGroupId(),
            'MessageDeduplicationId' => $candidate->messageDeduplicationId(),
        ];
    }
}
