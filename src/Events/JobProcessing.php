<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Events;

use MonkeysLegion\Queue\Contracts\JobInterface;

/**
 * Event fired before a job is processed.
 */
class JobProcessing
{
    public function __construct(
        public readonly JobInterface $job,
        public readonly string $queue
    ) {}
}
