<?php

namespace MonkeysLegion\Queue\Contracts;

/**
 * Interface ShouldQueue
 *
 * Denotes that an object (such as a notification, an event, or a structured job)
 * is intended to be processed asynchronously by passing it through the Queue system,
 * rather than being executed immediately during the synchronous request lifecycle.
 */
interface ShouldQueue
{
    // This is primarily a marker interface.
    // However, additional queue-specific configuration methods could be added here in the future
    // if needed (e.g., getQueueConnection(), getQueueName(), getDelay(), etc.).
}
