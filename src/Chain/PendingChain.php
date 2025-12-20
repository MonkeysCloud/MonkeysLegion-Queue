<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Chain;

use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;

/**
 * Fluent builder for job chains.
 * 
 * Jobs in a chain are executed sequentially - each job only runs
 * after the previous one completes successfully.
 */
class PendingChain
{
    /** @var DispatchableJobInterface[] */
    private array $jobs = [];
    private string $queue = 'default';
    private ?string $connection = null;

    public function __construct(
        private QueueInterface $queueDriver
    ) {}

    /**
     * Add jobs to the chain.
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
     * Set the queue for all jobs in the chain.
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Dispatch the chain by pushing the first job with chain metadata.
     */
    public function dispatch(): void
    {
        if (empty($this->jobs)) {
            return;
        }

        // Build chain data - all jobs except the first
        $remainingJobs = array_slice($this->jobs, 1);
        $chainData = [];

        foreach ($remainingJobs as $job) {
            $chainData[] = $this->serializeJob($job);
        }

        // Push first job with chain metadata
        $firstJob = $this->jobs[0];
        $jobData = $this->serializeJob($firstJob);
        $jobData['chain'] = $chainData;
        $jobData['chain_queue'] = $this->queue;

        $this->queueDriver->push($jobData, $this->queue);
    }

    /**
     * Serialize a job into an array format.
     */
    private function serializeJob(DispatchableJobInterface $job): array
    {
        $reflection = new \ReflectionClass($job);
        $constructor = $reflection->getConstructor();
        $payload = [];

        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if ($reflection->hasProperty($name)) {
                    $prop = $reflection->getProperty($name);
                    $payload[$name] = $prop->getValue($job);
                }
            }
        }

        return [
            'job' => get_class($job),
            'payload' => $payload,
        ];
    }
}
