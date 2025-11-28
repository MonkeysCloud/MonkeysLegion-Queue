<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Driver;

use InvalidArgumentException;
use Redis;
use RedisException;
use MonkeysLegion\Queue\Abstract\AbstractQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Queue\Job\Job;

class RedisQueue extends AbstractQueue
{
    public function __construct(
        private Redis $redis,
        array $config = []
    ) {
        parent::__construct($config);
    }

    public function push(array $jobData, string $queue = 'default'): void
    {
        $queue = $queue ?? $this->defaultQueue;
        $queueKey = $this->queuePrefix . $queue;

        // Prepare job data - preserve existing id and attempts if present
        $jobData = [
            'id' => $jobData['id'] ?? uniqid('job_', true),
            'job' => $jobData['job'] ?? null,
            'payload' => $jobData['payload'] ?? [],
            'attempts' => $jobData['attempts'] ?? 0,
            'created_at' => $jobData['created_at'] ?? microtime(true),
            'queue' => $queue, // Store the queue name so we can track where this job belongs
        ];

        try {
            $result = $this->redis->lPush($queueKey, $this->encodeJobData($jobData));

            if (!$result) {
                throw new \RuntimeException("Failed to push job to queue");
            }
        } catch (\RedisException $e) {
            throw new \RuntimeException(
                "Failed to push job to queue: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        $queue = $queue ?? $this->defaultQueue;
        $queueKey = $this->queuePrefix . $queue;
        $processingKey = $this->queuePrefix . $queue . ':processing';

        try {
            $jobJson = $this->redis->rPopLPush($queueKey, $processingKey);
            if (!$jobJson) {
                return null;
            }

            $jobData = json_decode($jobJson, true);
            if (!$jobData) {
                $this->redis->lRem($processingKey, $jobJson, 1);
                return null;
            }

            // Ensure queue name is tracked in job data
            $jobData['queue'] = $jobData['queue'] ?? $queue;

            return new Job($jobData, $this);
        } catch (\RedisException $e) {
            throw new \RuntimeException(
                "Failed to pop job from queue: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function ack(JobInterface $job): void
    {
        $jobData = $job instanceof Job ? $job->getData() : [
            'id' => $job->getId(),
            'job' => null,
            'payload' => [],
            'attempts' => $job->attempts(),
            'queue' => $this->defaultQueue,
        ];

        $queue = $jobData['queue'] ?? $this->defaultQueue;
        $processingKey = $this->queuePrefix . $queue . ':processing';

        try {
            $encoded = $this->encodeJobData($jobData);
            $this->redis->lRem($processingKey, $encoded, 1);
        } catch (\RedisException $e) {
            throw new \RuntimeException(
                "Failed to acknowledge job: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function release(JobInterface $job, int $delay = 0): void
    {
        $jobData = $job instanceof Job ? $job->getData() : [
            'id' => $job->getId(),
            'job' => null,
            'payload' => [],
            'attempts' => $job->attempts(),
            'queue' => $this->defaultQueue,
        ];

        // Update attempts count
        $jobData['attempts'] = ($jobData['attempts'] ?? 0) + 1;

        $queue = $jobData['queue'] ?? $this->defaultQueue;
        $processingKey = $this->queuePrefix . $queue . ':processing';

        try {
            // Remove from processing queue
            $originalEncoded = $job instanceof Job ? $this->encodeJobData($job->getData()) : null;
            if ($originalEncoded) {
                $this->redis->lRem($processingKey, $originalEncoded, 1);
            }

            // Re-queue with delay if specified
            if ($delay > 0) {
                $this->later($delay, $jobData, $queue);
            } else {
                $this->push($jobData, $queue);
            }
        } catch (\RedisException $e) {
            throw new \RuntimeException(
                "Failed to release job: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function clear(string $queue = 'default'): void
    {
        $queue = $queue ?? $this->defaultQueue;
        $queueKey = $this->queuePrefix . $queue;

        try {
            $this->redis->del($queueKey);
        } catch (\RedisException $e) {
            throw new \RuntimeException("Failed to clear queue: " . $e->getMessage(), 0, $e);
        }
    }

    public function __destruct()
    {
        try {
            $this->redis->close();
        } catch (\Exception $e) {
            // ignore, we're shutting down anyway
        }
    }

    public function fail(JobInterface $job, ?\Throwable $error = null): void
    {
        $failedKey = $this->queuePrefix . $this->failedQueue;

        // Extract job data from JobInterface
        $jobData = $job instanceof Job ? $job->getData() : [
            'id' => $job->getId(),
            'job' => null,
            'payload' => [],
            'attempts' => $job->attempts(),
            'queue' => $this->defaultQueue,
        ];

        $failedJobData = [
            'id' => $job->getId(),
            'job' => $jobData['job'] ?? null,
            'payload' => $jobData['payload'] ?? [],
            'attempts' => $job->attempts(),
            'queue' => $jobData['queue'] ?? $this->defaultQueue, // Preserve queue info
            'exception' => $error ? [
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString(),
            ] : null,
            'failed_at' => microtime(true),
        ];

        // Moving job to failed queue
        try {
            $this->redis->rPush($failedKey, $this->encodeJobData($failedJobData));
        } catch (\RedisException $e) {
            // Silently fail - job already removed from processing queue
        }
    }


    public function getFailed(int $limit = 100): array
    {
        $failedKey = $this->queuePrefix . $this->failedQueue;

        try {
            $jobs = $this->redis->lRange($failedKey, 0, $limit - 1);
            if (!is_array($jobs)) $jobs = [];

            $decodedJobs = array_map(
                fn($job) => is_string($job) ? json_decode($job, true) : null,
                $jobs
            );

            return array_values(array_filter($decodedJobs, fn($job) => $job !== null));
        } catch (RedisException $e) {
            throw new \RuntimeException("Failed to get failed jobs: " . $e->getMessage(), 0, $e);
        }
    }

    public function clearFailed(): void
    {
        $failedKey = $this->queuePrefix . $this->failedQueue;

        try {
            $this->redis->del($failedKey);
        } catch (RedisException $e) {
            throw new \RuntimeException("Failed to clear failed jobs: " . $e->getMessage(), 0, $e);
        }
    }

    public function purge(): void
    {
        try {
            $keys = $this->redis->keys($this->queuePrefix . '*');
            if (!empty($keys) && is_array($keys)) {
                $this->redis->del($keys);
            }
        } catch (RedisException $e) {
            throw new \RuntimeException("Failed to purge queues: " . $e->getMessage(), 0, $e);
        }
    }

    public function countFailed(): int
    {
        $failedKey = $this->queuePrefix . $this->failedQueue;

        try {
            return $this->redis->lLen($failedKey);
        } catch (RedisException $e) {
            return 0;
        }
    }

    public function count(string $queue = 'default'): int
    {
        $queueKey = $this->queuePrefix . $queue;

        try {
            return $this->redis->lLen($queueKey);
        } catch (RedisException $e) {
            return 0;
        }
    }

    public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void
    {
        $queue = $queue ?? $this->defaultQueue;
        $queueKey = $this->queuePrefix . "delayed:{$queue}";
        $runAt = microtime(true) + $delayInSeconds;

        if (empty($jobData['job'])) {
            throw new \InvalidArgumentException("later() requires 'job' to be defined");
        }

        // Build proper job structure (preserve payload and existing data)
        $payload = $jobData['payload'] ?? [];

        $job = [
            'id'         => $jobData['id'] ?? uniqid('job_', true),
            'job'        => $jobData['job'],
            'payload'    => $payload,
            'attempts'   => $jobData['attempts'] ?? 0,
            'created_at' => $jobData['created_at'] ?? microtime(true),
            'available_at' => $runAt,
            'queue'      => $queue, // Track queue name
        ];

        try {
            $encoded = $this->encodeJobData($job);
            $this->redis->zAdd($queueKey, $runAt, $encoded);
        } catch (\RedisException $e) {
            throw new \RuntimeException("Failed to queue delayed job: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Push multiple jobs onto the queue at once.
     *
     * @param array<array{job: string, payload?: array}> $jobs
     * @param string $queue
     */
    public function bulk(array $jobs, string $queue = 'default'): void
    {
        if (empty($jobs)) {
            return;
        }

        // FIX: Use configured prefix instead of hard-coded 'queue:'
        $queue = $queue ?? $this->defaultQueue;
        $queueKey = $this->queuePrefix . $queue;

        // Validate all jobs before starting transaction
        foreach ($jobs as $entry) {
            if (!isset($entry['job'])) {
                throw new InvalidArgumentException("Bulk job entry missing 'job' key.");
            }
        }

        $this->redis->multi();

        try {
            foreach ($jobs as $entry) {
                $jobData = [
                    'id'        => uniqid('job_', true),
                    'job'       => $entry['job'],
                    'payload'   => $entry['payload'] ?? [],
                    'attempts'  => 0,
                    'created_at' => microtime(true),
                    'queue'     => $queue, // Track queue name
                ];

                $this->redis->lPush($queueKey, $this->encodeJobData($jobData));
            }

            $this->redis->exec();
        } catch (\RedisException $e) {
            $this->redis->discard();
            throw new \RuntimeException("Failed to bulk enqueue jobs: " . $e->getMessage(), 0, $e);
        }
    }

    public function listQueue(string $queue = 'default', int $limit = 100): array
    {
        $queue = $queue ?? $this->defaultQueue;
        $queueKey = $this->queuePrefix . $queue;

        try {
            $jobs = $this->redis->lRange($queueKey, 0, $limit - 1);

            if (!is_array($jobs)) {
                return [];
            }

            $decodedJobs = array_map(
                fn($job) => is_string($job) ? json_decode($job, true) : null,
                $jobs
            );

            return array_values(array_filter($decodedJobs, fn($job) => $job !== null));
        } catch (RedisException $e) {
            throw new \RuntimeException("Failed to list queue: " . $e->getMessage(), 0, $e);
        }
    }

    public function retryFailed(int $limit = 100): void
    {
        $failedKey = $this->queuePrefix . $this->failedQueue;

        try {
            $jobs = $this->redis->lRange($failedKey, 0, $limit - 1);
            if (!is_array($jobs)) {
                return;
            }

            foreach ($jobs as $jobJson) {
                if (!is_string($jobJson)) continue;

                $failedJobData = json_decode($jobJson, true);
                if (!$failedJobData || !isset($failedJobData['job'])) continue;

                $this->redis->lRem($failedKey, $jobJson, 1);

                $restoreQueue = $failedJobData['queue'];

                $this->push([
                    'id' => $failedJobData['id'] ?? uniqid('job_', true),
                    'job' => $failedJobData['job'],
                    'payload' => $failedJobData['payload'] ?? [],
                    'attempts' => 0,
                ], $restoreQueue);
            }
        } catch (RedisException $e) {
            throw new \RuntimeException("Failed to retry failed jobs: " . $e->getMessage(), 0, $e);
        }
    }

    public function removeFailedJobs(string|array $jobIds): void
    {
        $ids = is_array($jobIds) ? $jobIds : [$jobIds];
        $failedKey = $this->queuePrefix . $this->failedQueue;

        try {
            $jobs = $this->redis->lRange($failedKey, 0, -1);
            if (!is_array($jobs)) {
                return;
            }

            foreach ($jobs as $jobJson) {
                if (!is_string($jobJson)) continue;

                $jobData = json_decode($jobJson, true);
                if (!$jobData || !isset($jobData['id'])) continue;

                if (in_array($jobData['id'], $ids, true)) {
                    $this->redis->lRem($failedKey, $jobJson, 1);
                }
            }
        } catch (RedisException $e) {
            throw new \RuntimeException("Failed to remove failed jobs: " . $e->getMessage(), 0, $e);
        }
    }

    public function peek(string $queue = 'default'): ?JobInterface
    {
        $queue = $queue ?? $this->defaultQueue;
        $queueKey = $this->queuePrefix . $queue;

        try {
            $jobs = $this->redis->lRange($queueKey, 0, 0);

            if (!is_array($jobs) || empty($jobs)) {
                return null;
            }

            $jobJson = $jobs[0];
            if (!is_string($jobJson)) {
                return null;
            }

            $jobData = json_decode($jobJson, true);
            if (!$jobData) {
                return null;
            }

            // Ensure queue name is tracked
            $jobData['queue'] = $jobData['queue'] ?? $queue;

            return new Job($jobData, $this);
        } catch (RedisException $e) {
            return null;
        }
    }

    public function moveJobToQueue(string $jobId, string $fromQueue, string $toQueue): void
    {
        $fromKey = $this->queuePrefix . $fromQueue;
        $toKey = $this->queuePrefix . $toQueue;

        try {
            $jobs = $this->redis->lRange($fromKey, 0, -1);
            if (!is_array($jobs)) {
                return;
            }

            foreach ($jobs as $jobJson) {
                if (!is_string($jobJson)) continue;

                $jobData = json_decode($jobJson, true);
                if (!$jobData || !isset($jobData['id'])) continue;

                if ($jobData['id'] === $jobId) {
                    // Update queue name in job data
                    $jobData['queue'] = $toQueue;
                    $updatedJobJson = $this->encodeJobData($jobData);

                    $this->redis->lRem($fromKey, $jobJson, 1);
                    $this->redis->lPush($toKey, $updatedJobJson);
                    return;
                }
            }
        } catch (RedisException $e) {
            throw new \RuntimeException("Failed to move job: " . $e->getMessage(), 0, $e);
        }
    }

    public function processDelayedJobs(string $queue = 'default'): int
    {
        $queue = $queue ?? $this->defaultQueue;
        $delayedKey = $this->queuePrefix . "delayed:{$queue}";
        $queueKey = $this->queuePrefix . $queue;
        $now = microtime(true);

        try {
            $jobs = $this->redis->zRangeByScore($delayedKey, '-inf', (string)$now);

            if (!is_array($jobs) || empty($jobs)) {
                return 0;
            }

            $movedCount = 0;

            foreach ($jobs as $jobJson) {
                if (!is_string($jobJson)) continue;

                $this->redis->lPush($queueKey, $jobJson);
                $this->redis->zRem($delayedKey, $jobJson);
                $movedCount++;
            }

            return $movedCount;
        } catch (RedisException $e) {
            return 0;
        }
    }

    public function getStats(string $queue = 'default'): array
    {
        $queue = $queue ?? $this->defaultQueue;
        $queueKey = $this->queuePrefix . $queue;
        $processingKey = $this->queuePrefix . $queue . ':processing';
        $delayedKey = $this->queuePrefix . "delayed:{$queue}";
        $failedKey = $this->queuePrefix . 'failed';

        try {
            return [
                'ready' => $this->redis->lLen($queueKey),
                'processing' => $this->redis->lLen($processingKey),
                'delayed' => $this->redis->zCard($delayedKey),
                'failed' => $this->redis->lLen($failedKey),
            ];
        } catch (RedisException $e) {
            return [
                'ready' => 0,
                'processing' => 0,
                'delayed' => 0,
                'failed' => 0,
            ];
        }
    }

    public function findJob(string $jobId, string $queue = 'default'): ?JobInterface
    {
        $queue = $queue ?? $this->defaultQueue;
        $queueKey = $this->queuePrefix . $queue;
        $processingKey = $this->queuePrefix . $queue . ':processing';
        $delayedKey = $this->queuePrefix . "delayed:{$queue}";

        try {
            // Search in ready queue
            $jobs = $this->redis->lRange($queueKey, 0, -1);
            if (is_array($jobs)) {
                foreach ($jobs as $jobJson) {
                    if (!is_string($jobJson)) continue;
                    $jobData = json_decode($jobJson, true);
                    if ($jobData && isset($jobData['id']) && $jobData['id'] === $jobId) {
                        $jobData['queue'] = $jobData['queue'] ?? $queue;
                        return new Job($jobData, $this);
                    }
                }
            }

            // Search in processing queue
            $jobs = $this->redis->lRange($processingKey, 0, -1);
            if (is_array($jobs)) {
                foreach ($jobs as $jobJson) {
                    if (!is_string($jobJson)) continue;
                    $jobData = json_decode($jobJson, true);
                    if ($jobData && isset($jobData['id']) && $jobData['id'] === $jobId) {
                        $jobData['queue'] = $jobData['queue'] ?? $queue;
                        return new Job($jobData, $this);
                    }
                }
            }

            // Search in delayed queue
            $jobs = $this->redis->zRange($delayedKey, 0, -1);
            if (is_array($jobs)) {
                foreach ($jobs as $jobJson) {
                    if (!is_string($jobJson)) continue;
                    $jobData = json_decode($jobJson, true);
                    if ($jobData && isset($jobData['id']) && $jobData['id'] === $jobId) {
                        $jobData['queue'] = $jobData['queue'] ?? $queue;
                        return new Job($jobData, $this);
                    }
                }
            }

            return null;
        } catch (RedisException $e) {
            return null;
        }
    }

    public function deleteJob(string $jobId, string $queue = 'default'): bool
    {
        $queue = $queue ?? $this->defaultQueue;
        $queueKey = $this->queuePrefix . $queue;
        $processingKey = $this->queuePrefix . $queue . ':processing';
        $delayedKey = $this->queuePrefix . "delayed:{$queue}";

        try {
            $deleted = false;

            // Try ready queue
            $jobs = $this->redis->lRange($queueKey, 0, -1);
            if (is_array($jobs)) {
                foreach ($jobs as $jobJson) {
                    if (!is_string($jobJson)) continue;
                    $jobData = json_decode($jobJson, true);
                    if ($jobData && isset($jobData['id']) && $jobData['id'] === $jobId) {
                        $this->redis->lRem($queueKey, $jobJson, 1);
                        $deleted = true;
                        break;
                    }
                }
            }

            // Try processing queue
            if (!$deleted) {
                $jobs = $this->redis->lRange($processingKey, 0, -1);
                if (is_array($jobs)) {
                    foreach ($jobs as $jobJson) {
                        if (!is_string($jobJson)) continue;
                        $jobData = json_decode($jobJson, true);
                        if ($jobData && isset($jobData['id']) && $jobData['id'] === $jobId) {
                            $this->redis->lRem($processingKey, $jobJson, 1);
                            $deleted = true;
                            break;
                        }
                    }
                }
            }

            // Try delayed queue
            if (!$deleted) {
                $jobs = $this->redis->zRange($delayedKey, 0, -1);
                if (is_array($jobs)) {
                    foreach ($jobs as $jobJson) {
                        if (!is_string($jobJson)) continue;
                        $jobData = json_decode($jobJson, true);
                        if ($jobData && isset($jobData['id']) && $jobData['id'] === $jobId) {
                            $this->redis->zRem($delayedKey, $jobJson);
                            $deleted = true;
                            break;
                        }
                    }
                }
            }

            return $deleted;
        } catch (RedisException $e) {
            return false;
        }
    }

    public function getQueues(): array
    {
        try {
            $pattern = $this->queuePrefix . '*';
            $keys = $this->redis->keys($pattern);

            if (!is_array($keys)) {
                return [$this->defaultQueue, $this->failedQueue];
            }

            $queues = [];
            foreach ($keys as $key) {
                if (!is_string($key)) continue;

                // Remove prefix and extract queue name
                $queueName = str_replace($this->queuePrefix, '', $key);

                // Skip processing, delayed, and failed suffixes
                if (
                    str_contains($queueName, ':processing') ||
                    str_contains($queueName, 'delayed:') ||
                    $queueName === 'failed'
                ) {
                    continue;
                }

                $queues[] = $queueName;
            }

            return array_unique($queues);
        } catch (RedisException $e) {
            return [$this->defaultQueue, $this->failedQueue];
        }
    }
}