<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\RateLimiter;

use MonkeysLegion\Queue\RateLimiter\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    public function testAttemptSucceedsWithinLimit(): void
    {
        $limiter = new RateLimiter(maxAttempts: 5, decaySeconds: 60);

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($limiter->attempt('test-key'));
        }
    }

    public function testAttemptFailsWhenLimitExceeded(): void
    {
        $limiter = new RateLimiter(maxAttempts: 3, decaySeconds: 60);

        // Use all attempts
        $this->assertTrue($limiter->attempt('test-key'));
        $this->assertTrue($limiter->attempt('test-key'));
        $this->assertTrue($limiter->attempt('test-key'));

        // Should be rate limited now
        $this->assertFalse($limiter->attempt('test-key'));
    }

    public function testRemainingReturnsCorrectCount(): void
    {
        $limiter = new RateLimiter(maxAttempts: 5, decaySeconds: 60);

        $this->assertEquals(5, $limiter->remaining('test-key'));

        $limiter->attempt('test-key');
        $this->assertEquals(4, $limiter->remaining('test-key'));

        $limiter->attempt('test-key');
        $this->assertEquals(3, $limiter->remaining('test-key'));
    }

    public function testRemainingNeverGoesNegative(): void
    {
        $limiter = new RateLimiter(maxAttempts: 2, decaySeconds: 60);

        $limiter->attempt('test-key');
        $limiter->attempt('test-key');
        $limiter->attempt('test-key'); // Exceeds limit

        $this->assertEquals(0, $limiter->remaining('test-key'));
    }

    public function testAvailableInReturnsZeroWhenNotLimited(): void
    {
        $limiter = new RateLimiter(maxAttempts: 5, decaySeconds: 60);

        $this->assertEquals(0, $limiter->availableIn('test-key'));

        $limiter->attempt('test-key');
        $this->assertEquals(0, $limiter->availableIn('test-key'));
    }

    public function testAvailableInReturnsTimeWhenLimited(): void
    {
        $limiter = new RateLimiter(maxAttempts: 1, decaySeconds: 60);

        $limiter->attempt('test-key');
        $limiter->attempt('test-key'); // Now limited

        $waitTime = $limiter->availableIn('test-key');
        $this->assertGreaterThan(0, $waitTime);
        $this->assertLessThanOrEqual(60, $waitTime);
    }

    public function testResetClearsRateLimitState(): void
    {
        $limiter = new RateLimiter(maxAttempts: 1, decaySeconds: 60);

        $limiter->attempt('test-key');
        $this->assertFalse($limiter->attempt('test-key'));

        $limiter->reset('test-key');

        $this->assertTrue($limiter->attempt('test-key'));
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $limiter = new RateLimiter(maxAttempts: 2, decaySeconds: 60);

        $limiter->attempt('key-1');
        $limiter->attempt('key-1');

        // key-1 is exhausted
        $this->assertFalse($limiter->attempt('key-1'));

        // key-2 should still have attempts
        $this->assertTrue($limiter->attempt('key-2'));
        $this->assertTrue($limiter->attempt('key-2'));
    }

    public function testGetConfigReturnsSettings(): void
    {
        $limiter = new RateLimiter(maxAttempts: 100, decaySeconds: 120);

        $config = $limiter->getConfig();

        $this->assertEquals(100, $config['max_attempts']);
        $this->assertEquals(120, $config['decay_seconds']);
    }
}
