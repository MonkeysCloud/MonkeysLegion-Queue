<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Contracts;

/**
 * Contract for all queue drivers (Redis, Database, Memory, etc.).
 * 
 * Provides a unified API for pushing, retrieving, and managing queued jobs
 * across multiple queue channels, including failed jobs.
 */
interface QueueInterface
{
    /**
     * Push a job onto the normal queue.
     *
     * @param array{job: string, payload?: array} $jobData Job class and optional payload.
     * @param string $queue Queue name. Defaults to "default".
     */
    public function push(array $jobData, string $queue = 'default'): void;

    /**
     * Push a job onto the queue with a delay.
     *
     * @param int $delayInSeconds Delay in seconds before the job becomes available.
     * @param array{job: string, payload?: array} $jobData Job class and optional payload.
     * @param string $queue Queue name. Defaults to "default".
     */
    public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void;

    /**
     * Push multiple jobs onto the queue at once.
     *
     * @param array<array{job: string, payload?: array}> $jobs Array of job data arrays.
     * @param string $queue Queue name. Defaults to "default".
     */
    public function bulk(array $jobs, string $queue = 'default'): void;

    /**
     * Retrieve the next job from the queue for processing.
     *
     * @param string $queue Queue name. Defaults to "default".
     * @return JobInterface|null Returns a JobInterface instance or null if empty.
     */
    public function pop(string $queue = 'default'): ?JobInterface;

    /**
     * Acknowledge that a job has been successfully processed.
     *
     * @param JobInterface $job The job instance that was processed.
     */
    public function ack(JobInterface $job): void;

    /**
     * Release a job back onto the queue to retry later.
     *
     * @param JobInterface $job The job to release.
     * @param int $delay Delay in seconds before the job becomes available again.
     */
    public function release(JobInterface $job, int $delay = 0): void;

    /**
     * Mark a job as permanently failed and move it to a failed queue.
     *
     * @param JobInterface $job The job that failed.
     * @param \Throwable|null $error Optional exception or error information.
     */
    public function fail(JobInterface $job, ?\Throwable $error = null): void;

    /**
     * Remove all jobs from a normal queue.
     *
     * @param string $queue Queue name. Defaults to "default".
     */
    public function clear(string $queue = 'default'): void;

    /**
     * Retrieve a list of jobs currently in the normal queue.
     *
     * @param string $queue Queue name. Defaults to "default".
     * @param int $limit Maximum number of jobs to retrieve. Defaults to 100.
     * @return array<int, array{id: string, job: string, payload?: array, attempts: int, created_at: float}>
     */
    public function listQueue(string $queue = 'default', int $limit = 100): array;

    /**
     * Count the number of jobs in a normal queue.
     *
     * @param string $queue Queue name. Defaults to "default".
     * @return int
     */
    public function count(string $queue = 'default'): int;

    /**
     * Retrieve failed jobs from the failed queue.
     *
     * @param string $queue Failed queue name. Defaults to "failed".
     * @param int $limit Maximum number of failed jobs to retrieve. Defaults to 100.
     * @return array<int, array{id: string, job: string, payload?: array, attempts?: int, exception?: array, failed_at: float}>
     */
    public function getFailed(int $limit = 100): array;

    /**
     * Clear all jobs from a failed queue.
     *
     * @param string $failedQueue Failed queue name. Defaults to "failed".
     */
    public function clearFailed(): void;

    /**
     * Count the number of jobs in a failed queue.
     *
     * @param string $failedQueue Failed queue name. Defaults to "failed".
     * @return int
     */
    public function countFailed(): int;

    /**
     * Purge all queues, removing all jobs from all queues.
     */
    public function purge(): void;

    /**
     * Retry failed jobs by pushing them back to a normal queue.
     *
     * @param string $failedQueue Failed queue name. Defaults to "failed".
     * @param string $targetQueue Target normal queue. Defaults to "default".
     * @param int $limit Maximum number of failed jobs to retry. Defaults to 100.
     */
    public function retryFailed(int $limit = 100): void;

    /**
     * Remove one or more jobs from a failed queue by their IDs.
     *
     * @param string|string[] $jobIds One or more job IDs.
     * @param string $failedQueue Failed queue name. Defaults to "failed".
     */
    public function removeFailedJobs(string|array $jobIds): void;

    /**
     * Peek at the next job in a queue without removing it.
     *
     * @param string $queue Queue name. Defaults to "default".
     * @return JobInterface|null
     */
    public function peek(string $queue = 'default'): ?JobInterface;

    /**
     * Move a job from one queue to another.
     *
     * @param string $jobId The ID of the job to move.
     * @param string $fromQueue Source queue name.
     * @param string $toQueue Target queue name.
     */
    public function moveJobToQueue(string $jobId, string $fromQueue, string $toQueue): void;

    /**
     * Process delayed jobs that are now ready to run.
     * Moves jobs from delayed queue to ready queue when their time comes.
     *
     * @param string $queue Queue name. Defaults to "default".
     * @return int Number of jobs moved to ready queue.
     */
    public function processDelayedJobs(string $queue = 'default'): int;

    /**
     * Get queue statistics and metrics.
     *
     * @param string $queue Queue name. Defaults to "default".
     * @return array{
     *     ready: int,
     *     processing: int,
     *     delayed: int,
     *     failed: int,
     *     total_processed?: int,
     *     avg_processing_time?: float
     * }
     */
    public function getStats(string $queue = 'default'): array;

    /**
     * Get a specific job by ID without removing it from the queue.
     *
     * @param string $jobId The job ID to find.
     * @param string $queue Queue name. Defaults to "default".
     * @return JobInterface|null
     */
    public function findJob(string $jobId, string $queue = 'default'): ?JobInterface;

    /**
     * Delete a specific job by ID from any queue (ready, processing, delayed).
     *
     * @param string $jobId The job ID to delete.
     * @param string $queue Queue name. Defaults to "default".
     * @return bool True if job was found and deleted.
     */
    public function deleteJob(string $jobId, string $queue = 'default'): bool;

    /**
     * Get all available queue names.
     *
     * @return string[] List of queue names.
     */
    public function getQueues(): array;
}
