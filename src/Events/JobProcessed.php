<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Events;

use MonkeysLegion\Queue\Contracts\JobInterface;

/**
 * Event fired after a job is successfully processed.
 */
class JobProcessed
{
    public function __construct(
        public readonly JobInterface $job,
        public readonly string $queue,
        public readonly float $processingTimeMs
    ) {}
}
