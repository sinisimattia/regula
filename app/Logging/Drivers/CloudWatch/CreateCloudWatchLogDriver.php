<?php

declare(strict_types=1);

namespace App\Logging\Drivers\CloudWatch;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Exception;
use Monolog\Logger;
use Monolog\Processor\ClosureContextProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;

class CreateCloudWatchLogDriver
{
    /**
     * @param  array<string, mixed>  $config
     *
     * @throws Exception
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('cloudwatch');

        $logGroupName = $config['group'];
        $logStreamName = now()->format('Y-m-d');
        $logLevel = $config['level'] ?? 'debug';

        // Everything comes from the channel config: once the config is cached, env() returns null.
        $clientConfig = [
            'region' => $config['region'],
            'version' => 'latest',
        ];

        // Without explicit keys the SDK's default chain applies, which is how an IAM role is picked up.
        if (! empty($config['key']) && ! empty($config['secret'])) {
            $clientConfig['credentials'] = [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ];
        }

        $client = new CloudWatchLogsClient($clientConfig);

        $handler = new CloudWatchHandler(
            client: $client,
            group: $logGroupName,
            stream: $logStreamName,
            batchSize: $config['batch_size'],
            level: Logger::toMonologLevel($logLevel),
        );

        $logger->pushHandler($handler);

        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor(new ClosureContextProcessor());
        $logger->pushProcessor(new IntrospectionProcessor());

        return $logger;
    }
}
