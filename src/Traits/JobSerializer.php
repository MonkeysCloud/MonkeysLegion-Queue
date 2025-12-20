<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Traits;

use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;

/**
 * Trait for serializing jobs into queue-storable format.
 *
 * Shared by QueueDispatcher, PendingChain, and PendingBatch.
 */
trait JobSerializer
{
    /**
     * Serialize a job into an array format for queue storage.
     *
     * @param DispatchableJobInterface $job The job to serialize
     * @return array{job: string, payload: array}
     */
    protected function serializeJob(DispatchableJobInterface $job): array
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
