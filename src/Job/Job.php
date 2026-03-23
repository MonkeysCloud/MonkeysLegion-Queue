<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Job;

use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Traits\JobSerializer;

class Job implements JobInterface
{
    use JobSerializer;

    private int $attempts;
    private string $id;

    public function __construct(
        private array $data,              // the decoded JSON
        private QueueInterface $queue     // the queue it came from
    ) {
        $this->id = $data['id'] ?? uniqid('job_', true);
        $this->attempts = $data['attempts'] ?? 0;
    }

    public function handle(): void
    {
        $jobClass = $this->data['job'] ?? null;
        $payload = $this->unserializeJob($this->data['payload'] ?? []);

        if (!$jobClass || !class_exists($jobClass)) {
            throw new \RuntimeException("Job class '{$jobClass}' not found");
        }

        // Instantiate the user's actual job class
        $jobInstance = new $jobClass(...$payload);

        // Call its handle method
        if (method_exists($jobInstance, 'handle')) {
            $jobInstance->handle();
        }

        // Dispatch next job in chain if present
        $this->dispatchNextInChain();
    }

    /**
     * Dispatch the next job in the chain, if any.
     */
    private function dispatchNextInChain(): void
    {
        $chain = $this->data['chain'] ?? [];
        $chainQueue = $this->data['chain_queue'] ?? 'default';

        if (empty($chain)) {
            return;
        }

        // Get next job and remaining chain
        $nextJobData = array_shift($chain);
        $nextJobData['chain'] = $chain;
        $nextJobData['chain_queue'] = $chainQueue;

        $this->queue->push($nextJobData, $chainQueue);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function fail(\Throwable $exception): void
    {
        $this->queue->fail($this, $exception);
    }

    /**
     * Get the raw job data array
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Check if this job is part of a chain.
     */
    public function isChained(): bool
    {
        return !empty($this->data['chain']);
    }
}
