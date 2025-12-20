<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\RateLimiter;

/**
 * Contract for rate limiters.
 */
interface RateLimiterInterface
{
    /**
     * Attempt to acquire a slot for processing.
     *
     * @param string $key The rate limit key (e.g., queue name, job type)
     * @return bool True if slot acquired, false if rate limited
     */
    public function attempt(string $key): bool;

    /**
     * Get the number of seconds until the next slot is available.
     *
     * @param string $key The rate limit key
     * @return int Seconds to wait (0 if not limited)
     */
    public function availableIn(string $key): int;

    /**
     * Get remaining attempts in current window.
     *
     * @param string $key The rate limit key
     * @return int Remaining attempts
     */
    public function remaining(string $key): int;

    /**
     * Reset the rate limit for a key.
     *
     * @param string $key The rate limit key
     */
    public function reset(string $key): void;
}
