<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Events;

use MonkeysLegion\Queue\Batch\Batch;

/**
 * Event fired when a batch completes (successfully or with failures).
 */
class BatchCompleted
{
    public function __construct(
        public readonly Batch $batch,
        public readonly bool $successful
    ) {
    }
}
