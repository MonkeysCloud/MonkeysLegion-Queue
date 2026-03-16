<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Contracts;

/**
 * Interface ShouldSync
 *
 * Denotes that a job should be executed synchronously in the current process
 * instead of being pushed to the queue, even when dispatched via the QueueDispatcher.
 */
interface ShouldSync
{
    // Marker interface
}
