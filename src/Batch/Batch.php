<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Batch;

/**
 * Represents a batch of jobs with tracking metadata.
 */
class Batch
{
    private int $totalJobs;
    private int $pendingJobs;
    private int $failedJobs = 0;
    private array $failedJobIds = [];
    private bool $cancelled = false;
    private ?float $finishedAt = null;

    public function __construct(
        public readonly string $id,
        int $totalJobs,
        public readonly float $createdAt,
        public readonly string $queue = 'default',
        public readonly ?string $thenCallback = null,
        public readonly ?string $catchCallback = null,
        public readonly ?string $finallyCallback = null
    ) {
        $this->totalJobs = $totalJobs;
        $this->pendingJobs = $totalJobs;
    }

    public function recordSuccess(): void
    {
        $this->pendingJobs = max(0, $this->pendingJobs - 1);
        $this->checkFinished();
    }

    public function recordFailure(string $jobId): void
    {
        $this->pendingJobs = max(0, $this->pendingJobs - 1);
        $this->failedJobs++;
        $this->failedJobIds[] = $jobId;
        $this->checkFinished();
    }

    private function checkFinished(): void
    {
        if ($this->pendingJobs === 0 && $this->finishedAt === null) {
            $this->finishedAt = microtime(true);
        }
    }

    public function cancel(): void
    {
        $this->cancelled = true;
        $this->finishedAt = microtime(true);
    }

    public function finished(): bool
    {
        return $this->finishedAt !== null;
    }

    public function successful(): bool
    {
        return $this->finished() && $this->failedJobs === 0 && !$this->cancelled;
    }

    public function failed(): bool
    {
        return $this->failedJobs > 0;
    }

    public function cancelled(): bool
    {
        return $this->cancelled;
    }

    public function progress(): float
    {
        if ($this->totalJobs === 0) {
            return 100.0;
        }
        return (($this->totalJobs - $this->pendingJobs) / $this->totalJobs) * 100;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'total_jobs' => $this->totalJobs,
            'pending_jobs' => $this->pendingJobs,
            'failed_jobs' => $this->failedJobs,
            'failed_job_ids' => $this->failedJobIds,
            'cancelled' => $this->cancelled,
            'created_at' => $this->createdAt,
            'finished_at' => $this->finishedAt,
            'queue' => $this->queue,
            'then_callback' => $this->thenCallback,
            'catch_callback' => $this->catchCallback,
            'finally_callback' => $this->finallyCallback,
        ];
    }

    public static function fromArray(array $data): self
    {
        $batch = new self(
            $data['id'],
            $data['total_jobs'],
            $data['created_at'],
            $data['queue'] ?? 'default',
            $data['then_callback'] ?? null,
            $data['catch_callback'] ?? null,
            $data['finally_callback'] ?? null
        );
        $batch->pendingJobs = $data['pending_jobs'];
        $batch->failedJobs = $data['failed_jobs'];
        $batch->failedJobIds = $data['failed_job_ids'] ?? [];
        $batch->cancelled = $data['cancelled'] ?? false;
        $batch->finishedAt = $data['finished_at'] ?? null;
        return $batch;
    }

    // Getters
    public function getTotalJobs(): int
    {
        return $this->totalJobs;
    }
    public function getPendingJobs(): int
    {
        return $this->pendingJobs;
    }
    public function getFailedJobs(): int
    {
        return $this->failedJobs;
    }
    public function getFailedJobIds(): array
    {
        return $this->failedJobIds;
    }
    public function getFinishedAt(): ?float
    {
        return $this->finishedAt;
    }
}
