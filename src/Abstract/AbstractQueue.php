<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Abstract;

use MonkeysLegion\Cli\Console\Traits\Cli;
use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;

abstract class AbstractQueue implements QueueInterface
{
    use Cli;

    /**
     * @var string Default queue name (channel).
     */
    protected string $defaultQueue = 'default';

    /**
     * @var string Name of the failed jobs queue.
     */
    protected string $failedQueue = 'failed';

    /**
     * @var string Prefix for all queue keys in the storage system.
     */
    protected string $queuePrefix = 'ml_queue:';

    /**
     * @var int Time in seconds after which a job is retried if not acknowledged.
     */
    protected int $retryAfter = 90;

    /**
     * @var int Visibility timeout in seconds for jobs being processed.
     */
    protected int $visibilityTimeout = 300;

    /**
     * @var int Maximum number of attempts for a job before it is considered failed.
     */
    protected int $maxAttempts = 3;

    public function __construct(array $config)
    {
        $this->defaultQueue      = $config['default_queue']     ?? 'default';
        $this->failedQueue       = $config['failed_queue']      ?? 'failed';
        $this->queuePrefix       = rtrim($config['queue_prefix'] ?? 'ml_queue', ':') . ':'; // always ends with :

        $this->retryAfter        = $config['retry_after']       ?? 90;
        $this->visibilityTimeout = $config['visibility_timeout'] ?? 300;
        $this->maxAttempts       = $config['max_attempts']      ?? 3;
    }

    protected function encodeJobData(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * Default implementation for retryFailed
     */
    public function retryFailed(int $limit = 100): void
    {
        $this->cliLine()
            ->warning("retryFailed() not implemented for this driver")
            ->plain("FailedQueue={$this->failedQueue}, Limit={$limit}")
            ->print();
    }

    /**
     * Default implementation for removeFailedJobs
     */
    public function removeFailedJobs(string|array $jobIds): void
    {
        $ids = is_array($jobIds) ? $jobIds : [$jobIds];

        $this->cliLine()
            ->warning("removeFailedJobs() not implemented for this driver")
            ->plain("JobCount=" . count($ids) . ", FailedQueue={$this->failedQueue}")
            ->print();
    }

    /**
     * Default implementation for peek
     */
    public function peek(string $queue = 'default'): ?JobInterface
    {
        $this->cliLine()
            ->warning("peek() not implemented for this driver")
            ->plain("Queue={$queue}")
            ->print();

        return null;
    }

    /**
     * Default implementation for moveJobToQueue
     */
    public function moveJobToQueue(string $jobId, string $fromQueue, string $toQueue): void
    {
        $this->cliLine()
            ->warning("moveJobToQueue() not implemented for this driver")
            ->plain("JobID={$jobId}, From={$fromQueue}, To={$toQueue}")
            ->print();
    }

    /**
     * Default implementation for processDelayedJobs
     */
    public function processDelayedJobs(string $queue = 'default'): int
    {
        $this->cliLine()
            ->warning("processDelayedJobs() not implemented for this driver")
            ->plain("Queue={$queue}")
            ->print();

        return 0;
    }

    /**
     * Default implementation for getStats
     */
    public function getStats(string $queue = 'default'): array
    {
        return [
            'ready' => $this->count($queue),
            'processing' => 0,
            'delayed' => 0,
            'failed' => $this->countFailed(),
        ];
    }

    /**
     * Default implementation for findJob
     */
    public function findJob(string $jobId, string $queue = 'default'): ?JobInterface
    {
        $this->cliLine()
            ->warning("findJob() not implemented for this driver")
            ->plain("JobID={$jobId}, Queue={$queue}")
            ->print();

        return null;
    }

    /**
     * Default implementation for deleteJob
     */
    public function deleteJob(string $jobId, string $queue = 'default'): bool
    {
        $this->cliLine()
            ->warning("deleteJob() not implemented for this driver")
            ->plain("JobID={$jobId}, Queue={$queue}")
            ->print();

        return false;
    }

    /**
     * Default implementation for getQueues
     */
    public function getQueues(): array
    {
        return [$this->defaultQueue, $this->failedQueue];
    }
}
