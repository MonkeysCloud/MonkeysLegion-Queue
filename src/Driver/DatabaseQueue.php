<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Driver;

use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Queue\Abstract\AbstractQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Queue\Job\Job;

class DatabaseQueue extends AbstractQueue
{
    private QueryBuilder $queryBuilder;
    private string $table;
    private string $failedTable;

    public function __construct(
        private ConnectionInterface $connection,
        array $config = []
    ) {
        $this->queryBuilder = new QueryBuilder($this->connection);
        $this->table = $config['table'] ?? 'jobs';
        $this->failedTable = $config['failed_table'] ?? 'failed_jobs';
        parent::__construct($config);
    }

    public function push(array $jobData, string $queue = 'default'): void
    {
        $queue = $queue ?? $this->defaultQueue;

        $jobData = [
            'id' => $jobData['id'] ?? uniqid('job_', true),
            'job' => $jobData['job'] ?? null,
            'payload' => $jobData['payload'] ?? [],
            'attempts' => $jobData['attempts'] ?? 0,
            'created_at' => $jobData['created_at'] ?? microtime(true),
            'queue' => $queue,
            'available_at' => $jobData['available_at'] ?? null,
        ];

        try {
            // Insert job into the database table
            $this->queryBuilder->insert($this->table, [
                'id' => $jobData['id'],
                'queue' => $queue,
                'job' => $jobData['job'],
                'payload' => json_encode($jobData['payload'], JSON_UNESCAPED_UNICODE),
                'attempts' => $jobData['attempts'],
                'created_at' => $jobData['created_at'],
                'available_at' => $jobData['available_at'],
                'reserved_at' => null,
                'failed_at' => null,
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to push job to database queue: ' . $e->getMessage());
        }
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        $queue = $queue ?? $this->defaultQueue;
        $now = microtime(true);

        $jobRow = $this->queryBuilder
            ->duplicate()
            ->from($this->table)
            ->where('queue', '=', $queue)
            ->whereNull('reserved_at')
            ->whereNull('failed_at')
            ->whereGroup(function ($q) use ($now) {
                $q->whereNull('available_at')
                    ->orWhere('available_at', '<=', $now);
            })
            ->orderBy('created_at', 'asc')
            ->limit(1)
            ->fetchAssoc();
        $this->queryBuilder->reset(); // Clear previous query state

        if (!$jobRow) {
            return null;
        }

        // Mark as reserved (simulate atomic claim)
        $reservedAt = microtime(true);
        $updated = $this->queryBuilder
            ->where('id', '=', $jobRow['id'])
            ->whereNull('reserved_at')
            ->update($this->table, ['reserved_at' => $reservedAt])
            ->execute();

        if (!$updated) {
            // Another worker claimed it
            return null;
        }

        $jobData = [
            'id' => $jobRow['id'],
            'job' => $jobRow['job'],
            'payload' => is_string($jobRow['payload']) ? json_decode($jobRow['payload'], true) : [],
            'attempts' => $jobRow['attempts'],
            'created_at' => $jobRow['created_at'],
            'queue' => $jobRow['queue'],
            'available_at' => $jobRow['available_at'],
        ];

        return new Job($jobData, $this);
    }

    public function ack(JobInterface $job): void
    {
        // Remove the job from the jobs table (only after successful processing)
        $jobId = $job->getId();
        try {
            $this->queryBuilder
                ->where('id', '=', $jobId)
                ->delete($this->table)
                ->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to acknowledge job: ' . $e->getMessage());
        }
    }

    public function fail(JobInterface $job, ?\Throwable $error = null): void
    {
        // Move job to failed_jobs table and remove from jobs table
        $jobData = $job->getData();
        $failedJobData = [
            'id' => $job->getId(),
            'job' => $jobData['job'] ?? null,
            'payload' => $this->encodeJobData($jobData['payload'] ?? []),
            'attempts' => $job->attempts(),
            'exception' => $error ? $this->encodeJobData([
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString(),
            ]) : null,
            'failed_at' => microtime(true),
        ];

        try {
            $this->queryBuilder->insert($this->failedTable, $failedJobData);
            $this->queryBuilder
                ->delete($this->table)->where('id', '=', $job->getId())
                ->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to move job to failed_jobs: ' . $e->getMessage());
        }
    }

    public function release(JobInterface $job, int $delay = 0): void
    {
        // Release a job back onto the queue to retry later.
        $jobId = $job->getId();
        $attempts = $job->attempts() + 1;
        $availableAt = $delay > 0 ? microtime(true) + $delay : null;

        try {
            $this->queryBuilder
                ->where('id', '=', $jobId)
                ->update($this->table, [
                    'attempts' => $attempts,
                    'reserved_at' => null,
                    'available_at' => $availableAt,
                ])->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to release job back to queue: ' . $e->getMessage());
        }
    }

    public function clear(string $queue = 'default'): void
    {
        // Remove all jobs from a normal queue.
        $queue = $queue ?? $this->defaultQueue;
        try {
            $this->queryBuilder
                ->where('queue', '=', $queue)
                ->delete($this->table)
                ->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to clear queue: ' . $e->getMessage());
        }
    }

    public function getFailed(string $queue = 'failed', int $limit = 100): array
    {
        try {
            $rows = $this->queryBuilder
                ->duplicate()
                ->from($this->failedTable)
                ->orderBy('failed_at', 'desc')
                ->limit($limit)
                ->fetchAll();
            // Clear previous query state so it doesn't affect other queries we reuse same Instance of DatabaseQueue
            $this->queryBuilder->reset();

            $failedJobs = [];
            foreach ($rows as $row) {
                $failedJobs[] = [
                    'id' => $row['id'],
                    'job' => $row['job'],
                    'payload' => is_string($row['payload']) ? json_decode($row['payload'], true) : [],
                    'attempts' => $row['attempts'] ?? 0,
                    'exception' => isset($row['exception']) && is_string($row['exception']) ? json_decode($row['exception'], true) : null,
                    'failed_at' => $row['failed_at'],
                ];
            }
            return $failedJobs;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to get failed jobs: ' . $e->getMessage());
        }
    }

    public function clearFailed(string $failedQueue = 'failed'): void
    {
        try {
            $this->queryBuilder
                ->delete($this->failedTable)
                ->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to clear failed jobs: ' . $e->getMessage());
        }
    }

    public function count(string $queue = 'default'): int
    {
        $queue = $queue ?? $this->defaultQueue;
        try {
            return (int) $this->queryBuilder
                ->duplicate()
                ->from($this->table)
                ->where('queue', '=', $queue)
                ->count();
            $this->queryBuilder->reset();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to count jobs in queue: ' . $e->getMessage());
        }
    }

    public function countFailed(string $failedQueue = 'failed'): int
    {
        try {
            return $this->queryBuilder
                ->duplicate()
                ->from($this->failedTable)
                ->count();
            $this->queryBuilder->reset();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to count failed jobs: ' . $e->getMessage());
        }
    }

    public function purge(): void
    {
        try {
            $this->queryBuilder
                ->delete($this->table)
                ->execute();

            $this->queryBuilder
                ->delete($this->failedTable)
                ->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to purge all queues: ' . $e->getMessage());
        }
    }

    public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void
    {
        $queue = $queue ?? $this->defaultQueue;
        $availableAt = microtime(true) + $delayInSeconds;

        $jobData['available_at'] = $availableAt;

        $this->push($jobData, $queue);
    }

    public function bulk(array $jobs, string $queue = 'default'): void
    {
        $queue = $queue ?? $this->defaultQueue;
        foreach ($jobs as $jobData) {
            $this->push($jobData, $queue);
        }
    }

    public function listQueue(string $queue = 'default', int $limit = 100): array
    {
        $queue = $queue ?? $this->defaultQueue;
        try {
            $rows = $this->queryBuilder
                ->duplicate()
                ->from($this->table)
                ->where('queue', '=', $queue)
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->fetchAll();
            $this->queryBuilder->reset();

            $jobs = [];
            foreach ($rows as $row) {
                $jobs[] = [
                    'id' => $row['id'],
                    'job' => $row['job'],
                    'payload' => is_string($row['payload']) ? json_decode($row['payload'], true) : [],
                    'attempts' => $row['attempts'],
                    'created_at' => $row['created_at'],
                ];
            }
            return $jobs;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to list queue: ' . $e->getMessage());
        }
    }

    public function retryFailed(string $failedQueue = 'failed', string $targetQueue = 'default', int $limit = 100): void
    {
        try {
            $rows = $this->queryBuilder
                ->duplicate()
                ->from($this->failedTable)
                ->orderBy('failed_at', 'asc')
                ->limit($limit)
                ->fetchAll();
            $this->queryBuilder->reset();

            foreach ($rows as $row) {
                $jobData = [
                    'id' => $row['id'],
                    'job' => $row['job'],
                    'payload' => is_string($row['payload']) ? json_decode($row['payload'], true) : [],
                    'attempts' => 0,
                    'created_at' => $row['created_at'] ?? microtime(true),
                ];
                $this->push($jobData, $targetQueue);

                // Remove from failed table
                $this->queryBuilder
                    ->where('id', '=', $row['id'])
                    ->delete($this->failedTable)
                    ->execute();
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to retry failed jobs: ' . $e->getMessage());
        }
    }

    public function removeFailedJobs(string|array $jobIds, string $failedQueue = 'failed'): void
    {
        $ids = is_array($jobIds) ? $jobIds : [$jobIds];
        try {
            foreach ($ids as $id) {
                $this->queryBuilder
                    ->where('id', '=', $id)
                    ->delete($this->failedTable)
                    ->execute();
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to remove failed jobs: ' . $e->getMessage());
        }
    }

    public function peek(string $queue = 'default'): ?JobInterface
    {
        $queue = $queue ?? $this->defaultQueue;
        try {
            $row = $this->queryBuilder
                ->duplicate()
                ->from($this->table)
                ->where('queue', '=', $queue)
                ->whereNull('reserved_at')
                ->whereNull('failed_at')
                ->orderBy('created_at', 'asc')
                ->limit(1)
                ->fetchAssoc();
            $this->queryBuilder->reset();

            if (!$row) {
                return null;
            }

            $jobData = [
                'id' => $row['id'],
                'job' => $row['job'],
                'payload' => is_string($row['payload']) ? json_decode($row['payload'], true) : [],
                'attempts' => $row['attempts'],
                'created_at' => $row['created_at'],
                'queue' => $row['queue'],
                'available_at' => $row['available_at'],
            ];

            return new Job($jobData, $this);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function moveJobToQueue(string $jobId, string $fromQueue, string $toQueue): void
    {
        try {
            // Find the job in the source queue
            $row = $this->queryBuilder
                ->duplicate()
                ->from($this->table)
                ->where('id', '=', $jobId)
                ->where('queue', '=', $fromQueue)
                ->fetchAssoc();
            $this->queryBuilder->reset();

            if (!$row) {
                return;
            }

            // Update the queue field to move the job
            $this->queryBuilder
                ->where('id', '=', $jobId)
                ->update($this->table, ['queue' => $toQueue])
                ->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to move job to another queue: ' . $e->getMessage());
        }
    }

    public function processDelayedJobs(string $queue = 'default'): int
    {
        $queue = $queue ?? $this->defaultQueue;
        $now = microtime(true);

        try {
            // Find all jobs in this queue that are delayed and now available
            $rows = $this->queryBuilder
                ->duplicate()
                ->from($this->table)
                ->where('queue', '=', $queue)
                ->whereNull('reserved_at')
                ->whereNotNull('available_at')
                ->andWhere('available_at', '<=', $now)
                ->fetchAll();
            $this->queryBuilder->reset();

            $count = 0;
            foreach ($rows as $row) {
                // Set available_at to null to make it ready
                $this->queryBuilder
                    ->where('id', '=', $row['id'])
                    ->update($this->table, ['available_at' => null])
                    ->execute();
                $count++;
            }
            return $count;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to process delayed jobs: ' . $e->getMessage());
        }
    }

    public function getStats(string $queue = 'default'): array
    {
        $queue = $queue ?? $this->defaultQueue;

        try {
            // READY
            $ready = $this->queryBuilder
                ->duplicate()
                ->from($this->table)
                ->where('queue', '=', $queue)
                ->whereNull('reserved_at')
                ->whereNull('failed_at')
                ->whereGroup(function (QueryBuilder $q) {
                    $q->where('available_at', '<=', microtime(true))
                        ->orWhereNull('available_at');
                })
                ->count();

            // PROCESSING
            $processing = $this->queryBuilder
                ->duplicate()
                ->from($this->table)
                ->where('queue', '=', $queue)
                ->whereNotNull('reserved_at')
                ->whereNull('failed_at')
                ->count();

            // DELAYED
            $delayed = $this->queryBuilder
                ->duplicate()
                ->from($this->table)
                ->where('queue', '=', $queue)
                ->whereNotNull('available_at')
                ->andWhere('available_at', '>', microtime(true))
                ->whereNull('failed_at')
                ->count();

            // FAILED
            $failed = $this->queryBuilder
                ->duplicate()
                ->from($this->failedTable)
                ->count();
            $this->queryBuilder->reset();

            return [
                'ready'      => (int) $ready,
                'processing' => (int) $processing,
                'delayed'    => (int) $delayed,
                'failed'     => (int) $failed,
            ];
        } catch (\Throwable $e) {

            return [
                'ready'      => 0,
                'processing' => 0,
                'delayed'    => 0,
                'failed'     => 0,
            ];
        }
    }

    public function findJob(string $jobId, string $queue = 'default'): ?JobInterface
    {
        $queue = $queue ?? $this->defaultQueue;
        try {
            // Search in ready, processing, and delayed jobs
            $row = $this->queryBuilder
                ->duplicate()
                ->from($this->table)
                ->where('id', '=', $jobId)
                ->where('queue', '=', $queue)
                ->fetchAssoc();
            $this->queryBuilder->reset();

            if ($row) {
                $jobData = [
                    'id' => $row['id'],
                    'job' => $row['job'],
                    'payload' => is_string($row['payload']) ? json_decode($row['payload'], true) : [],
                    'attempts' => $row['attempts'],
                    'created_at' => $row['created_at'],
                    'queue' => $row['queue'],
                    'available_at' => $row['available_at'],
                ];
                return new Job($jobData, $this);
            }

            // Optionally, search in failed jobs
            $row = $this->queryBuilder
                ->duplicate()
                ->from($this->failedTable)
                ->where('id', '=', $jobId)
                ->fetchAssoc();
            $this->queryBuilder->reset();

            if ($row) {
                $jobData = [
                    'id' => $row['id'],
                    'job' => $row['job'],
                    'payload' => is_string($row['payload']) ? json_decode($row['payload'], true) : [],
                    'attempts' => $row['attempts'] ?? 0,
                    'created_at' => $row['created_at'] ?? null,
                    'queue' => $queue,
                    'available_at' => null,
                ];
                return new Job($jobData, $this);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function deleteJob(string $jobId, string $queue = 'default'): bool
    {
        $queue = $queue ?? $this->defaultQueue;
        try {
            // Try to delete from normal jobs table
            $deleted = $this->queryBuilder
                ->where('id', '=', $jobId)
                ->andWhere('queue', '=', $queue)
                ->delete($this->table)
                ->execute();

            if ($deleted) {
                return true;
            }

            // Try to delete from failed jobs table
            $deleted = $this->queryBuilder
                ->where('id', '=', $jobId)
                ->delete($this->failedTable)
                ->execute();

            return (bool)$deleted;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getQueues(): array
    {
        try {
            // Get all unique queue names from jobs table
            $rows = $this->queryBuilder
                ->duplicate()
                ->from($this->table)
                ->select('queue')
                ->groupBy('queue')
                ->fetchAllAssoc();
            $this->queryBuilder->reset();

            $queues = [];
            foreach ($rows as $row) {
                if (isset($row['queue'])) {
                    $queues[] = $row['queue'];
                }
            }

            // Optionally, add failed queue if not present
            if (!in_array($this->failedQueue, $queues, true)) {
                $queues[] = $this->failedQueue;
            }

            // if default queue not present, add it
            if (!in_array($this->defaultQueue, $queues, true)) {
                $queues[] = $this->defaultQueue;
            }

            return array_unique($queues);
        } catch (\Exception $e) {
            return [$this->defaultQueue, $this->failedQueue];
        }
    }
}
