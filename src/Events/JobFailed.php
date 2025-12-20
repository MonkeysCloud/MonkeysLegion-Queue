<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Events;

use MonkeysLegion\Queue\Contracts\JobInterface;

/**
 * Event fired when a job fails.
 */
class JobFailed
{
    public function __construct(
        public readonly JobInterface $job,
        public readonly string $queue,
        public readonly \Throwable $exception,
        public readonly bool $willRetry
    ) {}
}
