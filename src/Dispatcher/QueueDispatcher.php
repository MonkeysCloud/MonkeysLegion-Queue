<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Dispatcher;

use MonkeysLegion\Queue\Batch\BatchRepository;
use MonkeysLegion\Queue\Batch\PendingBatch;
use MonkeysLegion\Queue\Chain\PendingChain;
use MonkeysLegion\Queue\Contracts\QueueDispatcherInterface;
use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Contracts\ShouldQueue;
use MonkeysLegion\Queue\Traits\JobSerializer;

class QueueDispatcher implements QueueDispatcherInterface
{
    use JobSerializer;

    public function __construct(
        private QueueInterface $queueDriver,
        private ?BatchRepository $batchRepository = null
    ) {
    }

    public function dispatch(
        DispatchableJobInterface $job,
        string $queue = 'default',
        ?int $delay = null
    ): void {
        if (!($job instanceof ShouldQueue)) {
            $job->handle();
            return;
        }

        $jobData = $this->serializeJob($job);
        if ($delay) {
            $this->queueDriver->later($delay, $jobData, $queue);
        } else {
            $this->queueDriver->push($jobData, $queue);
        }
    }

    public function dispatchAt(
        DispatchableJobInterface $job,
        int $timestamp,
        string $queue = 'default'
    ): void {
        if (!($job instanceof ShouldQueue)) {
            $job->handle();
            return;
        }

        $jobData = $this->serializeJob($job);
        $this->queueDriver->later($timestamp - time(), $jobData, $queue);
    }

    /**
     * Create a new job chain.
     *
     * @param DispatchableJobInterface[] $jobs Jobs to chain
     * @return PendingChain
     */
    public function chain(array $jobs): PendingChain
    {
        return (new PendingChain($this->queueDriver))->add($jobs);
    }

    /**
     * Create a new job batch.
     *
     * @param DispatchableJobInterface[] $jobs Jobs to batch
     * @return PendingBatch
     */
    public function batch(array $jobs): PendingBatch
    {
        if ($this->batchRepository === null) {
            throw new \RuntimeException('Batch repository is not configured.');
        }
        return (new PendingBatch($this->queueDriver, $this->batchRepository))->add($jobs);
    }

    /**
     * Get the batch repository instance.
     */
    public function getBatchRepository(): BatchRepository
    {
        if ($this->batchRepository === null) {
            throw new \RuntimeException('Batch repository is not configured.');
        }
        return $this->batchRepository;
    }
}
