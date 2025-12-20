<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Dispatcher;

use MonkeysLegion\Queue\Batch\BatchRepository;
use MonkeysLegion\Queue\Batch\PendingBatch;
use MonkeysLegion\Queue\Chain\PendingChain;
use MonkeysLegion\Queue\Contracts\QueueDispatcherInterface;
use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;

class QueueDispatcher implements QueueDispatcherInterface
{
    private ?BatchRepository $batchRepository = null;

    public function __construct(
        private QueueInterface $queueDriver
    ) {}

    public function dispatch(
        DispatchableJobInterface $job,
        string $queue = 'default',
        ?int $delay = null
    ): void {
        $jobData = $this->buildPayload($job);
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
        $jobData = $this->buildPayload($job);
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
            $this->batchRepository = new BatchRepository($this->queueDriver);
        }
        return (new PendingBatch($this->queueDriver, $this->batchRepository))->add($jobs);
    }

    /**
     * Get the batch repository instance.
     */
    public function getBatchRepository(): BatchRepository
    {
        if ($this->batchRepository === null) {
            $this->batchRepository = new BatchRepository($this->queueDriver);
        }
        return $this->batchRepository;
    }

    private function buildPayload(DispatchableJobInterface $job): array
    {
        $reflection = new \ReflectionClass($job);
        $constructor = $reflection->getConstructor();
        $payload = [];

        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                // get value from property if it exists
                if ($reflection->hasProperty($name)) {
                    $prop = $reflection->getProperty($name);
                    $payload[$name] = $prop->getValue($job);
                }
            }
        }

        // Build jobData
        return [
            'job' => get_class($job),
            'payload' => $payload,
        ];
    }
}

