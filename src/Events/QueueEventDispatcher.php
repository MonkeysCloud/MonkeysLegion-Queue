<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Events;

/**
 * Simple event dispatcher for queue events.
 */
class QueueEventDispatcher
{
    /** @var array<string, array<callable>> */
    private array $listeners = [];

    /**
     * Register a listener for an event.
     *
     * @param string $eventClass The event class name
     * @param callable $listener The listener callback
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param object $event The event instance
     */
    public function dispatch(object $event): void
    {
        $eventClass = get_class($event);

        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $listener) {
            $listener($event);
        }
    }

    /**
     * Check if any listeners are registered for an event.
     */
    public function hasListeners(string $eventClass): bool
    {
        return !empty($this->listeners[$eventClass]);
    }

    /**
     * Remove all listeners for an event.
     */
    public function forget(string $eventClass): void
    {
        unset($this->listeners[$eventClass]);
    }
}
