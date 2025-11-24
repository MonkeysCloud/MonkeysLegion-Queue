<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Contracts;

interface JobInterface
{
    /**
     * Execute the job.
     */
    public function handle(): void;

    /**
     * Get the job identifier.
     */
    public function getId(): string;

    /**
     * Get the raw job data array
     * 
     * @return array
     */
    public function getData(): array;

    /**
     * Get the number of attempts already made.
     */
    public function attempts(): int;

    /**
     * Mark the job as failed.
     */
    public function fail(\Throwable $exception): void;
}
