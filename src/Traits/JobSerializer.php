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
     * Serialize a job's constructor parameters into a storable format.
     *
     * @param DispatchableJobInterface $job The job instance to serialize
     * @return array The serialized job data ready for storage
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
                    $value = $prop->getValue($job);

                    // If it's an object, "freeze" it so JSON/Database storage doesn't break it
                    if (is_object($value)) {
                        $payload[$name] = [
                            '__type' => 'serialized_object',
                            'data'   => serialize($value)
                        ];
                    } else {
                        $payload[$name] = $value;
                    }
                }
            }
        }

        return [
            'job' => get_class($job),
            'payload' => $payload,
        ];
    }

    /**
     * Unserialize the job payload back into real PHP objects.
     *
     * @param array $payload The raw payload from the queue storage
     * @return array The "re-hydrated" payload ready for the constructor
     */
    protected function unserializeJob(array $payload): array
    {
        foreach ($payload as $key => $value) {
            // Check if this specific parameter was "frozen" as a serialized object
            if (
                is_array($value) && 
                isset($value['__type']) && 
                $value['__type'] === 'serialized_object'
            ) {
                // Turn the string back into a real Class instance (e.g., App\Entity\User)
                $payload[$key] = unserialize($value['data']);
            }
        }

        return $payload;
    }
}