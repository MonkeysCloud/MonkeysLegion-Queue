<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Dispatcher;

use MonkeysLegion\Queue\Contracts\QueueDispatcherInterface;
use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;

class QueueDispatcher implements QueueDispatcherInterface
{
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
