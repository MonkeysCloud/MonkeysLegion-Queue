<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Worker;

use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Contracts\WorkerInterface;
use MonkeysLegion\Queue\Helpers\CliPrinter;

/**
 * Queue worker for processing jobs
 * Handles job execution, retries, and failure management
 */
class Worker implements WorkerInterface
{
    private bool $shouldQuit = false;
    private int $processedJobs = 0;
    private int $lastDelayedCheck = 0;

    public function __construct(
        private QueueInterface $queue,
        private int $sleep = 3,
        private int $maxTries = 3,
        private int $memory = 128,
        private int $timeout = 60,
        private int $delayedCheckInterval = 30  // Check delayed jobs every 30 seconds
    ) {
        $this->registerSignalHandlers();
    }

    public function work(string $queue = 'default', int $sleep = 3): void
    {
        $this->sleep = $sleep;

        CliPrinter::printCliMessage("Worker started", [
            'queue' => $queue
        ], 'info');

        while (!$this->shouldQuit) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            if ($this->memoryExceeded()) {
                CliPrinter::printCliMessage("Memory limit exceeded", [], 'warning');
                break;
            }

            $this->checkDelayedJobs($queue);

            $job = $this->queue->pop($queue);

            if (!$job) {
                sleep($this->sleep);
                continue;
            }

            $this->process($job);
            $this->processedJobs++;
        }

        CliPrinter::printCliMessage("Worker stopped", [
            'total_processed' => $this->processedJobs
        ], 'info');
    }

    public function process(JobInterface $job): void
    {
        $start = microtime(true);

        try {
            set_time_limit($this->timeout);

            CliPrinter::printCliMessage("Processing", [
                'job_id' => substr($job->getId(), 4, 8), // Short ID
                'attempts' => $job->attempts() + 1
            ], 'processing');

            $job->handle();

            $this->queue->ack($job);

            CliPrinter::printCliMessage("Completed", [
                'job_id' => substr($job->getId(), 4, 8),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ], 'notice');
        } catch (\Throwable $e) {
            $attempts = $job->attempts() + 1;

            if ($attempts < $this->maxTries) {
                $this->retry($job, $e);
            } else {
                $this->queue->ack($job);
                $job->fail($e);

                CliPrinter::printCliMessage("Failed", [
                    'job_id' => substr($job->getId(), 4, 8),
                    'attempts' => $attempts
                ], 'error');
            }
        }
    }

    private function retry(JobInterface $job, \Throwable $e): void
    {
        $attempts = $job->attempts() + 1;
        $delay = min(60, (int)pow(2, $attempts - 1));

        $this->queue->release($job, $delay);

        CliPrinter::printCliMessage("Retrying", [
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
}
