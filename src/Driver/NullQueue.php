<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Driver;

use MonkeysLegion\Queue\Abstract\AbstractQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;

/**
 * Null Queue Driver
 * 
 * A no-op queue implementation that discards all jobs.
 * Useful for testing, development, or temporarily disabling queues.
 */
class NullQueue extends AbstractQueue
{
    public function push(array $jobData, string $queue = 'default'): void
    {
        // Do nothing - job is discarded
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        // Always return null - no jobs available
        return null;
    }

    public function ack(JobInterface $job): void
    {
        // Do nothing - no acknowledgment needed
    }

    public function release(JobInterface $job, int $delay = 0): void
    {
        // Do nothing - job is discarded
    }

    public function fail(JobInterface $job, ?\Throwable $error = null): void
    {
        // Do nothing - failed job is discarded
    }

    public function clear(string $queue = 'default'): void
    {
        // Do nothing - nothing to clear
    }

    public function listQueue(string $queue = 'default', int $limit = 100): array
    {
        // Always return empty array
        return [];
    }

    public function count(string $queue = 'default'): int
    {
        // Always return 0
        return 0;
    }

    public function getFailed(int $limit = 100): array
    {
        // Always return empty array
        return [];
    }

    public function clearFailed(): void
    {
        // Do nothing - nothing to clear
    }

    public function countFailed(): int
    {
        // Always return 0
        return 0;
    }

    public function purge(): void
    {
        // Do nothing - nothing to purge
    }

    public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void
    {
        // Do nothing - delayed job is discarded
    }

    public function bulk(array $jobs, string $queue = 'default'): void
    {
        // Do nothing - all jobs are discarded
    }

    public function retryFailed(int $limit = 100): void
    {
        // Do nothing - no failed jobs to retry
    }

    public function removeFailedJobs(string|array $jobIds): void
    {
        // Do nothing - no failed jobs to remove
    }

    public function peek(string $queue = 'default'): ?JobInterface
    {
        // Always return null - no jobs to peek
        return null;
    }

    public function moveJobToQueue(string $jobId, string $fromQueue, string $toQueue): void
    {
        // Do nothing - no jobs to move
    }

    public function processDelayedJobs(string $queue = 'default'): int
    {
        // Always return 0 - no delayed jobs processed
        return 0;
    }

    public function getStats(string $queue = 'default'): array
    {
        // Return zero stats
        return [
            'ready' => 0,
            'processing' => 0,
            'delayed' => 0,
            'failed' => 0,
        ];
    }

    public function findJob(string $jobId, string $queue = 'default'): ?JobInterface
    {
        // Always return null - job not found
        return null;
    }

    public function deleteJob(string $jobId, string $queue = 'default'): bool
    {
        // Always return false - no job to delete
        return false;
    }

    public function getQueues(): array
    {
        // Return default queue names
        return [$this->defaultQueue, $this->failedQueue];
    }
}
