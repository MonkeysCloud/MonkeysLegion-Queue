<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Fixtures;

use MonkeysLegion\Queue\Abstract\AbstractQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Queue\Job\Job;

class MemoryQueue extends AbstractQueue
{
    public array $jobs = [];
    public array $failed = [];
    public array $delayed = [];
    public array $lastRetried = [];
    public array $lastRemovedFailed = [];
    public string $lastClearedQueue = '';
    public string $lastDelayedProcessedQueue = '';
    public bool $wasPurged = false;

    public function push(array $jobData, string $queue = 'default'): void
    {
        $this->jobs[$queue][] = $jobData;
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        if (empty($this->jobs[$queue])) {
            return null;
        }

        $jobData = array_shift($this->jobs[$queue]);
        return new Job($jobData, $this);
    }

    public function ack(JobInterface $job): void
    {
        // Job is already removed in pop for this simple implementation
    }

    public function release(JobInterface $job, int $delay = 0): void
    {
        $data = $job->getData();
        $data['attempts'] = $job->attempts() + 1; // Increment attempts on release

        if ($delay > 0) {
            $this->later($delay, $data, $data['queue'] ?? 'default');
        } else {
            $this->push($data, $data['queue'] ?? 'default');
        }
    }

    public function fail(JobInterface $job, ?\Throwable $error = null): void
    {
        $this->failed[] = [
            'job' => $job,
            'error' => $error
        ];
    }

    public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void
    {
        // For testing, we might want to just push it
        // Or store in delayed array
        $this->delayed[$queue][] = [
            'run_at' => time() + $delayInSeconds,
            'data' => $jobData
        ];
    }

    // Helper for tests to process delayed
    public function moveDelayedToReady(string $queue = 'default'): void
    {
        if (empty($this->delayed[$queue])) {
            return;
        }

        foreach ($this->delayed[$queue] as $key => $item) {
            $this->push($item['data'], $queue);
            unset($this->delayed[$queue][$key]);
        }
    }

    // Required abstract methods
    public function clear(string $queue = 'default'): void
    {
        $this->lastClearedQueue = $queue;
        unset($this->jobs[$queue]);
    }
    public function listQueue(string $queue = 'default', int $limit = 100): array
    {
        return array_slice($this->jobs[$queue] ?? [], 0, $limit);
    }
    public function count(string $queue = 'default'): int
    {
        return count($this->jobs[$queue] ?? []);
    }
    public function getFailed(int $limit = 100): array
    {
        return array_slice($this->failed, 0, $limit);
    }
    public function clearFailed(): void
    {
        $this->failed = [];
    }
    public function countFailed(): int
    {
        return count($this->failed);
    }
    public function purge(): void
    {
        $this->jobs = [];
        $this->delayed = [];
        $this->failed = [];
        $this->wasPurged = true;
    }
    public function bulk(array $jobs, string $queue = 'default'): void
    {
        foreach ($jobs as $job) {
            $this->push($job, $queue);
        }
    }

    public function retryFailed(int $limit = 100): void
    {
        $this->lastRetried = array_slice($this->failed, 0, $limit);
    }

    public function removeFailedJobs(string|array $jobIds): void
    {
        $ids = is_array($jobIds) ? $jobIds : [$jobIds];
        $lookup = array_fill_keys(array_map('strval', $ids), true);
        $this->lastRemovedFailed = array_values(array_map('strval', $ids));
        $this->failed = array_values(array_filter(
            $this->failed,
            fn(array $job): bool => !isset($lookup[(string)($job['id'] ?? '')])
        ));
    }

    public function processDelayedJobs(string $queue = 'default'): int
    {
        $this->lastDelayedProcessedQueue = $queue;
        $count = count($this->delayed[$queue] ?? []);
        $this->moveDelayedToReady($queue);
        return $count;
    }
}
