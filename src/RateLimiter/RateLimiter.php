<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\RateLimiter;

/**
 * Token bucket rate limiter implementation.
 * 
 * Uses in-memory storage for simplicity. For distributed systems,
 * extend this class or implement RateLimiterInterface with Redis.
 */
class RateLimiter implements RateLimiterInterface
{
    /** @var array<string, array{tokens: int, last_refill: float}> */
    private array $buckets = [];

    /**
     * @param int $maxAttempts Maximum attempts per time window
     * @param int $decaySeconds Time window in seconds
     */
    public function __construct(
        private int $maxAttempts = 60,
        private int $decaySeconds = 60
    ) {}

    public function attempt(string $key): bool
    {
        $this->refillBucket($key);

        if ($this->buckets[$key]['tokens'] <= 0) {
            return false;
        }

        $this->buckets[$key]['tokens']--;
        return true;
    }

    public function availableIn(string $key): int
    {
        if (!isset($this->buckets[$key])) {
            return 0;
        }

        if ($this->buckets[$key]['tokens'] > 0) {
            return 0;
        }

        $elapsed = microtime(true) - $this->buckets[$key]['last_refill'];
        $secondsUntilRefill = max(0, $this->decaySeconds - $elapsed);

        return (int) ceil($secondsUntilRefill);
    }

    public function remaining(string $key): int
    {
        $this->refillBucket($key);
        return max(0, $this->buckets[$key]['tokens']);
    }

    public function reset(string $key): void
    {
        unset($this->buckets[$key]);
    }

    private function refillBucket(string $key): void
    {
        if (!isset($this->buckets[$key])) {
            $this->buckets[$key] = [
                'tokens' => $this->maxAttempts,
                'last_refill' => microtime(true),
            ];
            return;
        }

        $elapsed = microtime(true) - $this->buckets[$key]['last_refill'];

        if ($elapsed >= $this->decaySeconds) {
            $this->buckets[$key] = [
                'tokens' => $this->maxAttempts,
                'last_refill' => microtime(true),
            ];
        }
    }

    /**
     * Get rate limiter configuration.
     */
    public function getConfig(): array
    {
        return [
            'max_attempts' => $this->maxAttempts,
            'decay_seconds' => $this->decaySeconds,
        ];
    }
}
