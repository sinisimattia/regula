<?php

declare(strict_types=1);

namespace App\Queue\Connectors;

use App\Queue\SqsFifoQueue;
use Aws\Sqs\SqsClient;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Support\Arr;

class SqsFifoConnector extends SqsConnector
{
    public function connect(array $config): SqsFifoQueue
    {
        $config = $this->getDefaultConfiguration($config);

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return new SqsFifoQueue(
            sqs: new SqsClient(Arr::except($config, ['token'])),
            default: $config['queue'],
            prefix: $config['prefix'] ?? '',
            suffix: $config['suffix'] ?? '',
            dispatchAfterCommit: $config['after_commit'] ?? false,
        );
    }
}
