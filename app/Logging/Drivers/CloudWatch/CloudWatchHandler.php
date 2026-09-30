<?php

declare(strict_types=1);

namespace App\Logging\Drivers\CloudWatch;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use DateTime;
use InvalidArgumentException;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Monolog handler that ships records to CloudWatch Logs, adapted from maxbanton/cwh, which no longer
 * supports the current Monolog.
 */
class CloudWatchHandler extends AbstractProcessingHandler
{
    /**
     * Requests per second limit (https://docs.aws.amazon.com/AmazonCloudWatch/latest/logs/cloudwatch_limits_cwl.html)
     */
    private const RPS_LIMIT = 5;

    /**
     * Event size limit (https://docs.aws.amazon.com/AmazonCloudWatch/latest/logs/cloudwatch_limits_cwl.html)
     */
    private const EVENT_SIZE_LIMIT = 262118; // 262144 - reserved 26

    /**
     * The batch of log events in a single PutLogEvents request cannot span more than 24 hours.
     */
    private const TIMESPAN_LIMIT = 86400000;

    private bool $initialized = false;
    private ?string $sequenceToken = null;

    /** @var array<int, array<string, mixed>> */
    private array $buffer = [];

    /**
     * Data amount limit (http://docs.aws.amazon.com/AmazonCloudWatchLogs/latest/APIReference/API_PutLogEvents.html)
     */
    private int $dataAmountLimit = 1048576;
    private int $currentDataAmount = 0;
    private int $remainingRequests = self::RPS_LIMIT;
    private DateTime $savedTime;
    private ?float $earliestTimestamp = null;

    /**
     * @param  array<string, string>  $tags
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private CloudWatchLogsClient $client,
        private string $group,
        private string $stream,
        private ?int $retention = null,
        private int $batchSize = 10000,
        private array $tags = [],
        int|Level $level = Logger::DEBUG,
        bool $bubble = true,
        private bool $createGroup = true
    ) {
        if ($batchSize > 10000) {
            throw new InvalidArgumentException('Batch size cannot be greater than 10000');
        }

        parent::__construct($level, $bubble);

        $this->savedTime = new DateTime();
    }

    protected function write(LogRecord $record): void
    {
        $recordArray = [
            'formatted' => $record->formatted,
            'datetime' => $record->datetime
        ];

        $records = $this->formatRecords($recordArray);

        foreach ($records as $formattedRecord) {
            if ($this->willMessageSizeExceedLimit($formattedRecord) || $this->willMessageTimestampExceedLimit($formattedRecord)) {
                $this->flushBuffer();
            }

            $this->addToBuffer($formattedRecord);

            if (count($this->buffer) >= $this->batchSize) {
                $this->flushBuffer();
            }
        }
    }

    private function addToBuffer(array $record): void
    {
        $this->currentDataAmount += $this->getMessageSize($record);

        $timestamp = $record['timestamp'];

        if (!$this->earliestTimestamp || $timestamp < $this->earliestTimestamp) {
            $this->earliestTimestamp = $timestamp;
        }

        $this->buffer[] = $record;
    }

    private function flushBuffer(): void
    {
        if (!empty($this->buffer)) {
            if (false === $this->initialized) {
                $this->initialize();
            }

            // send items, retry once with a fresh sequence token
            try {
                $this->send($this->buffer);
            } catch (CloudWatchLogsException $e) {
                $this->refreshSequenceToken();
                $this->send($this->buffer);
            }

            // clear buffer
            $this->buffer = [];

            // clear the earliest timestamp
            $this->earliestTimestamp = null;

            // clear data amount
            $this->currentDataAmount = 0;
        }
    }

    private function checkThrottle(): void
    {
        $current = new DateTime();
        $diff = $current->diff($this->savedTime)->s;
        $sameSecond = $diff === 0;

        if ($sameSecond && $this->remainingRequests > 0) {
            $this->remainingRequests--;
        } elseif ($sameSecond && $this->remainingRequests === 0) {
            sleep(1);
            $this->remainingRequests = self::RPS_LIMIT;
        } elseif (!$sameSecond) {
            $this->remainingRequests = self::RPS_LIMIT;
        }

        $this->savedTime = new DateTime();
    }

    private function getMessageSize(array $record): int
    {
        return strlen($record['message']) + 26;
    }

    /**
     * Determine whether the specified record's message size in addition to the
     * size of the current queued messages will exceed AWS CloudWatch's limit.
     */
    protected function willMessageSizeExceedLimit(array $record): bool
    {
        return $this->currentDataAmount + $this->getMessageSize($record) >= $this->dataAmountLimit;
    }

    /**
     * Determine whether the specified record's timestamp exceeds the 24-hour timespan limit
     * for all batched messages written in a single call to PutLogEvents.
     */
    protected function willMessageTimestampExceedLimit(array $record): bool
    {
        return $this->earliestTimestamp && $record['timestamp'] - $this->earliestTimestamp > self::TIMESPAN_LIMIT;
    }

    private function formatRecords(array $entry): array
    {
        $entries = str_split($entry['formatted'], self::EVENT_SIZE_LIMIT);
        $timestamp = $entry['datetime']->format('U.u') * 1000;
        $records = [];

        foreach ($entries as $entry) {
            $records[] = [
                'message' => $entry,
                'timestamp' => $timestamp
            ];
        }

        return $records;
    }

    /**
     * @throws CloudWatchLogsException
     */
    private function send(array $entries): void
    {
        // AWS expects to receive entries in chronological order...
        usort($entries, static function (array $a, array $b) {
            if ($a['timestamp'] < $b['timestamp']) {
                return -1;
            } elseif ($a['timestamp'] > $b['timestamp']) {
                return 1;
            }

            return 0;
        });

        $data = [
            'logGroupName' => $this->group,
            'logStreamName' => $this->stream,
            'logEvents' => $entries
        ];

        if (!empty($this->sequenceToken)) {
            $data['sequenceToken'] = $this->sequenceToken;
        }

        $this->checkThrottle();

        $response = $this->client->putLogEvents($data);

        $this->sequenceToken = $response->get('nextSequenceToken');
    }

    private function initializeGroup(): void
    {
        // fetch existing groups
        $existingGroups = $this->client
            ->describeLogGroups(['logGroupNamePrefix' => $this->group])
            ->get('logGroups');

        // extract existing groups names
        $existingGroupsNames = array_map(
            function ($group) {
                return $group['logGroupName'];
            },
            $existingGroups
        );

        // create group and set retention policy if not created yet
        if (!in_array($this->group, $existingGroupsNames, true)) {
            $createLogGroupArguments = ['logGroupName' => $this->group];

            if (!empty($this->tags)) {
                $createLogGroupArguments['tags'] = $this->tags;
            }

            $this->client->createLogGroup($createLogGroupArguments);

            if ($this->retention !== null) {
                $this->client->putRetentionPolicy([
                    'logGroupName' => $this->group,
                    'retentionInDays' => $this->retention,
                ]);
            }
        }
    }

    private function initialize(): void
    {
        if ($this->createGroup) {
            $this->initializeGroup();
        }

        $this->refreshSequenceToken();
    }

    private function refreshSequenceToken(): void
    {
        // fetch existing streams
        $existingStreams = $this->client
            ->describeLogStreams([
                'logGroupName' => $this->group,
                'logStreamNamePrefix' => $this->stream,
            ])
            ->get('logStreams');

        // extract existing streams names
        $existingStreamsNames = array_map(
            function ($stream) {
                // set sequence token
                if ($stream['logStreamName'] === $this->stream && isset($stream['uploadSequenceToken'])) {
                    $this->sequenceToken = $stream['uploadSequenceToken'];
                }

                return $stream['logStreamName'];
            },
            $existingStreams
        );

        // create stream if not created
        if (!in_array($this->stream, $existingStreamsNames, true)) {
            $this->client->createLogStream([
                'logGroupName' => $this->group,
                'logStreamName' => $this->stream
            ]);
        }

        $this->initialized = true;
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter('%channel%: %level_name%: %message% %context% %extra%', null, false, true);
    }

    public function close(): void
    {
        $this->flushBuffer();
    }
}
