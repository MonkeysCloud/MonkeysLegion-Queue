<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Batch;

use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Traits\JobSerializer;

/**
 * Fluent builder for job batches.
 */
class PendingBatch
{
    use JobSerializer;

    /** @var DispatchableJobInterface[] */
    private array $jobs = [];
    private string $queue = 'default';
    private ?string $thenCallback = null;
    private ?string $catchCallback = null;
    private ?string $finallyCallback = null;

    public function __construct(
        private QueueInterface $queueDriver,
        private BatchRepository $repository
    ) {
    }

    /**
     * Add jobs to the batch.
     *
     * @param DispatchableJobInterface[] $jobs
     */
    public function add(array $jobs): self
    {
        foreach ($jobs as $job) {
            $this->jobs[] = $job;
        }
        return $this;
    }

    /**
     * Set the queue for all jobs in the batch.
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Register a callback for when all jobs complete successfully.
     *
     * @param string $callback Class name or "ClassName::method"
     */
    public function then(string $callback): self
    {
        $this->thenCallback = $callback;
        return $this;
    }

    /**
     * Register a callback for when any job fails.
     *
     * @param string $callback Class name or "ClassName::method"
     */
    public function catch(string $callback): self
    {
        $this->catchCallback = $callback;
        return $this;
    }

    /**
     * Register a callback that always runs when batch finishes.
     *
     * @param string $callback Class name or "ClassName::method"
     */
    public function finally(string $callback): self
    {
        $this->finallyCallback = $callback;
        return $this;
    }

    /**
     * Dispatch the batch.
     */
    public function dispatch(): Batch
    {
        if (empty($this->jobs)) {
            throw new \RuntimeException('Cannot dispatch empty batch');
        }

        // Create batch
        $batchId = uniqid('batch_', true);
        $batch = new Batch(
            $batchId,
            count($this->jobs),
            microtime(true),
            $this->queue,
            $this->thenCallback,
            $this->catchCallback,
            $this->finallyCallback
        );

        $this->repository->store($batch);

        // Dispatch all jobs with batch metadata
        foreach ($this->jobs as $job) {
            $jobData = $this->serializeJob($job);
            $jobData['batch_id'] = $batchId;
            $this->queueDriver->push($jobData, $this->queue);
        }

        return $batch;
    }
}
