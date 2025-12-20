<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Batch;

use MonkeysLegion\Queue\Contracts\QueueInterface;

/**
 * Simple in-memory batch repository.
 * 
 * For production, extend this to use Redis or Database storage.
 */
class BatchRepository
{
    /** @var array<string, Batch> */
    private static array $batches = [];

    public function __construct(
        private ?QueueInterface $queue = null
    ) {}

    public function store(Batch $batch): void
    {
        self::$batches[$batch->id] = $batch;
    }

    public function find(string $batchId): ?Batch
    {
        return self::$batches[$batchId] ?? null;
    }

    public function update(Batch $batch): void
    {
        self::$batches[$batch->id] = $batch;
    }

    public function delete(string $batchId): void
    {
        unset(self::$batches[$batchId]);
    }

    /**
     * Record a job completion and execute callbacks if batch is done.
     */
    public function recordJobCompletion(string $batchId, bool $successful, ?string $jobId = null): void
    {
        $batch = $this->find($batchId);
        if (!$batch) {
            return;
        }

        if ($successful) {
            $batch->recordSuccess();
        } else {
            $batch->recordFailure($jobId ?? 'unknown');
        }

        $this->update($batch);

        // Execute callbacks if batch is finished
        if ($batch->finished()) {
            $this->executeCallbacks($batch);
        }
    }

    private function executeCallbacks(Batch $batch): void
    {
        // Execute catch callback if any jobs failed
        if ($batch->failed() && $batch->catchCallback) {
            $this->executeCallback($batch->catchCallback, $batch);
        }

        // Execute then callback if all jobs succeeded
        if ($batch->successful() && $batch->thenCallback) {
            $this->executeCallback($batch->thenCallback, $batch);
        }

        // Always execute finally callback
        if ($batch->finallyCallback) {
            $this->executeCallback($batch->finallyCallback, $batch);
        }
    }

    private function executeCallback(string $callback, Batch $batch): void
    {
        // Callback format: "ClassName::methodName" or just "ClassName" (calls __invoke)
        if (str_contains($callback, '::')) {
            [$class, $method] = explode('::', $callback, 2);
            if (class_exists($class)) {
                (new $class())->$method($batch);
            }
        } elseif (class_exists($callback)) {
            (new $callback())($batch);
        }
    }

    /**
     * Get all batches (for debugging/monitoring).
     */
    public function all(): array
    {
        return self::$batches;
    }

    /**
     * Clear all batches (for testing).
     */
    public function clear(): void
    {
        self::$batches = [];
    }
}
