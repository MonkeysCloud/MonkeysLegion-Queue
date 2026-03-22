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
     * Full backward compatibility with old JSON format.
     *
     * @param array $payload The raw payload from the queue storage
     * @return array The "re-hydrated" payload ready for the constructor
     */
    protected function unserializeJob(mixed $payload): array
    {
        // 1. TOLERATE OLD FORMAT: 
        // If the database gives us a JSON string, decode it first.
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                // If it's a string but NOT JSON, try a full unserialize (safety catch)
                if (preg_match('/^[aOs]:/', $payload)) {
                    $payload = unserialize($payload);
                }
            }
        }

        // 2. PROCESS NEW FORMAT:
        // At this point $payload is an array. We look for "frozen" objects.
        if (is_array($payload)) {
            // Check if we are dealing with the 'payload' sub-key or the top-level
            $dataToProcess = $payload['payload'] ?? $payload;

            foreach ($dataToProcess as $key => $value) {
                if (
                    is_array($value) && 
                    isset($value['__type']) && 
                    $value['__type'] === 'serialized_object'
                ) {
                    $dataToProcess[$key] = unserialize($value['data']);
                }
            }
            
            // Re-assign back if we were working on a sub-key
            if (isset($payload['payload'])) {
                $payload['payload'] = $dataToProcess;
            } else {
                $payload = $dataToProcess;
            }
        }

        return (array) $payload;
    }
}