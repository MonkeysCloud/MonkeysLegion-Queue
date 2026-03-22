<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Worker;

use MonkeysLegion\DI\Traits\ContainerAware;
use MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface;
use MonkeysLegion\Queue\Batch\BatchRepository;
use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Contracts\WorkerInterface;
use MonkeysLegion\Queue\Events\JobFailed;
use MonkeysLegion\Queue\Events\JobProcessed;
use MonkeysLegion\Queue\Events\JobProcessing;
use MonkeysLegion\Queue\Events\QueueEventDispatcher;
use MonkeysLegion\Queue\Helpers\CliPrinter;
use MonkeysLegion\Queue\RateLimiter\RateLimiterInterface;

/**
 * Queue worker for processing jobs
 * Handles job execution, retries, and failure management
 */
class Worker implements WorkerInterface
{
    use ContainerAware;

    private bool $shouldQuit = false;
    private int $processedJobs = 0;
    private int $lastDelayedCheck = 0;
    private string $currentQueue = 'default';

    private ?QueueEventDispatcher $eventDispatcher = null;
    private ?RateLimiterInterface $rateLimiter = null;
    private ?BatchRepository $batchRepository = null;
    private ?MonkeysLoggerInterface $logger = null;

    public function __construct(
        private QueueInterface $queue,
        private int $sleep = 3,
        private int $maxTries = 3,
        private int $memory = 128,
        private int $timeout = 60,
        private int $delayedCheckInterval = 30,
    ) {
        $this->registerSignalHandlers();

        $this->eventDispatcher = $this->has(QueueEventDispatcher::class) ? $this->resolve(QueueEventDispatcher::class) : null;
        $this->rateLimiter = $this->has(RateLimiterInterface::class) ? $this->resolve(RateLimiterInterface::class) : null;
        $this->batchRepository = $this->has(BatchRepository::class) ? $this->resolve(BatchRepository::class) : null;
        $this->logger = $this->has(MonkeysLoggerInterface::class) ? $this->resolve(MonkeysLoggerInterface::class) : null;
    }

    public function work(string|array $queue = 'default', int $sleep = 3): void
    {
        $this->sleep = $sleep;
        if (is_string($queue)) {
            $queue = [$queue];
        }

        CliPrinter::printCliMessage("Worker started", [
            'queue' => implode(',', $queue),
        ], 'info');

        while (!$this->shouldQuit) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            if ($this->memoryExceeded()) {
                CliPrinter::printCliMessage("Memory limit exceeded", [], 'warning');
                break;
            }

            foreach ($queue as $queue_name) {
                $this->checkDelayedJobs($queue_name);
            }

            foreach ($queue as $queue_name) {
                $job = $this->queue->pop($queue_name);
                if ($job) {
                    break;
                }
            }

            if (!$job) {
                // If queue is empty, trigger GC to free memory
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                sleep($this->sleep);
                continue;
            }

            // Check rate limiter before processing
            if ($this->rateLimiter !== null) {
                $queue_name = $job->getData()['queue'] ?? 'default';
                if (!$this->rateLimiter->attempt($queue_name)) {
                    // Rate limited - release job back and wait
                    $waitTime = $this->rateLimiter->availableIn($queue_name);
                    CliPrinter::printCliMessage("Rate limited", [
                        'queue' => $queue_name,
                        'wait_seconds' => $waitTime,
                    ], 'warning');
                    $this->queue->release($job, max(1, $waitTime));
                    sleep(min($waitTime, $this->sleep));
                    continue;
                }
            }

            $this->currentQueue = $job->getData()['queue'] ?? 'default';
            $this->process($job);
            $this->processedJobs++;

            // Proactively trigger GC every 100 jobs to manage memory
            if ($this->processedJobs % 100 === 0 && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        CliPrinter::printCliMessage("Worker stopped", [
            'total_processed' => $this->processedJobs
        ], 'info');
    }

    public function process(JobInterface $job): void
    {
        $start = microtime(true);
        $data = $job->getData();
        $queue = $data['queue'] ?? $this->currentQueue;

        // Dispatch JobProcessing event
        $this->dispatchEvent(new JobProcessing($job, $queue));

        try {
            set_time_limit($this->timeout);

            CliPrinter::printCliMessage("Processing", [
                'class' => $data['job'] ?? 'Unknown class',
                'job_id' => substr($job->getId(), 4, 8),
                'attempts' => $job->attempts() + 1,
                'queue' => $queue,
            ], 'processing');

            $job->handle();

            $this->queue->ack($job);

            $processingTimeMs = round((microtime(true) - $start) * 1000, 2);

            CliPrinter::printCliMessage("Completed", [
                'class' => $data['job'] ?? 'Unknown class',
                'job_id' => substr($job->getId(), 4, 8),
                'duration_ms' => $processingTimeMs,
                'queue' => $queue,
            ], 'notice');

            // Dispatch JobProcessed event
            $this->dispatchEvent(new JobProcessed($job, $queue, $processingTimeMs));

            // Track batch completion if job is part of a batch
            $this->trackBatchCompletion($job, true);
        } catch (\Throwable $e) {
            $attempts = $job->attempts() + 1;
            $willRetry = $attempts < $this->maxTries;

            CliPrinter::printCliMessage("Failed", [
                'class' => $data['job'] ?? 'Unknown class',
                'job_id' => substr($job->getId(), 4, 8),
                'attempts' => $attempts
            ], 'error');

            // Dispatch JobFailed event
            $this->dispatchEvent(new JobFailed($job, $queue, $e, $willRetry));

            if ($willRetry) {
                $this->retry($job, $e);
            } else {
                $this->queue->ack($job);
                $job->fail($e);
                // Track batch failure (job will not retry)
                $this->trackBatchCompletion($job, false);
            }
        }
    }

    private function retry(JobInterface $job, \Throwable $e): void
    {
        $attempts = $job->attempts() + 1;
        $delay = min(60, (int)pow(2, $attempts - 1));

        $this->queue->release($job, $delay);

        CliPrinter::printCliMessage("Pushed to retry later", [
            'class' => $job->getData()['job'] ?? 'Unknown class',
            'job_id' => substr($job->getId(), 4, 8),
            'attempts' => $attempts,
            'delay' => $delay
        ], 'warning');
    }

    private function checkDelayedJobs(string $queue): void
    {
        $now = time();

        if ($now - $this->lastDelayedCheck < $this->delayedCheckInterval) {
            return;
        }

        $this->lastDelayedCheck = $now;

        try {
            $this->queue->processDelayedJobs($queue);
        } catch (\Throwable $e) {
            // Silently continue
        }
    }

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        // Graceful shutdown on SIGTERM and SIGINT
        pcntl_signal(SIGTERM, function () {
            $this->stop();
        });

        pcntl_signal(SIGINT, function () {
            $this->stop();
        });
    }

    public function stop(): void
    {
        if (!$this->shouldQuit) {
            CliPrinter::printCliMessage("Shutdown signal received, finishing current job...", [], 'warning');
            $this->shouldQuit = true;
        }
    }

    private function memoryExceeded(): bool
    {
        $usage = memory_get_usage(true) / 1024 / 1024;
        return $usage >= $this->memory;
    }

    /**
     * Get worker statistics
     */
    public function getStats(): array
    {
        return [
            'processed_jobs' => $this->processedJobs,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'should_quit' => $this->shouldQuit,
        ];
    }

    /**
     * Dispatch an event if an event dispatcher is configured.
     */
    private function dispatchEvent(object $event): void
    {
        $this->eventDispatcher?->dispatch($event);
    }

    /**
     * Track batch completion for a job if it's part of a batch.
     */
    private function trackBatchCompletion(JobInterface $job, bool $successful): void
    {
        $data = $job->getData();
        $batchId = $data['batch_id'] ?? null;

        if ($batchId === null || $this->batchRepository === null) {
            return;
        }

        $this->batchRepository->recordJobCompletion($batchId, $successful, $job->getId());
    }
}
