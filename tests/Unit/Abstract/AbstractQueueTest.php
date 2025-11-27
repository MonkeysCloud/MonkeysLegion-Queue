<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Abstract;

use MonkeysLegion\Queue\Abstract\AbstractQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;
use PHPUnit\Framework\TestCase;

class AbstractQueueTest extends TestCase
{
    private AbstractQueue $queue;

    protected function setUp(): void
    {
        // Create a concrete implementation for testing
        $this->queue = new class([]) extends AbstractQueue {
            public function push(array $jobData, string $queue = 'default'): void {}
            public function pop(string $queue = 'default'): ?JobInterface
            {
                return null;
            }
            public function ack(JobInterface $job): void {}
            public function release(JobInterface $job, int $delay = 0): void {}
            public function fail(JobInterface $job, ?\Throwable $error = null): void {}
            public function clear(string $queue = 'default'): void {}
            public function listQueue(string $queue = 'default', int $limit = 100): array
            {
                return [];
            }
            public function count(string $queue = 'default'): int
            {
                return 0;
            }
            public function getFailed(string $queue = 'failed', int $limit = 100): array
            {
                return [];
            }
            public function clearFailed(string $failedQueue = 'failed'): void {}
            public function countFailed(string $failedQueue = 'failed'): int
            {
                return 0;
            }
            public function purge(): void {}
            public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void {}
            public function bulk(array $jobs, string $queue = 'default'): void {}

            // Expose protected method for testing
            public function testEncodeJobData(array $data): string
            {
                return $this->encodeJobData($data);
            }
        };
    }

    public function testConstructorWithDefaultConfig(): void
    {
        $queue = new class([]) extends AbstractQueue {
            public function push(array $jobData, string $queue = 'default'): void {}
            public function pop(string $queue = 'default'): ?JobInterface
            {
                return null;
            }
            public function ack(JobInterface $job): void {}
            public function release(JobInterface $job, int $delay = 0): void {}
            public function fail(JobInterface $job, ?\Throwable $error = null): void {}
            public function clear(string $queue = 'default'): void {}
            public function listQueue(string $queue = 'default', int $limit = 100): array
            {
                return [];
            }
            public function count(string $queue = 'default'): int
            {
                return 0;
            }
            public function getFailed(string $queue = 'failed', int $limit = 100): array
            {
                return [];
            }
            public function clearFailed(string $failedQueue = 'failed'): void {}
            public function countFailed(string $failedQueue = 'failed'): int
            {
                return 0;
            }
            public function purge(): void {}
            public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void {}
            public function bulk(array $jobs, string $queue = 'default'): void {}

            public function getDefaultQueue(): string
            {
                return $this->defaultQueue;
            }
            public function getFailedQueue(): string
            {
                return $this->failedQueue;
            }
            public function getQueuePrefix(): string
            {
                return $this->queuePrefix;
            }
            public function getRetryAfter(): int
            {
                return $this->retryAfter;
            }
            public function getVisibilityTimeout(): int
            {
                return $this->visibilityTimeout;
            }
            public function getMaxAttempts(): int
            {
                return $this->maxAttempts;
            }
        };

        $this->assertEquals('default', $queue->getDefaultQueue());
        $this->assertEquals('failed', $queue->getFailedQueue());
        $this->assertEquals('ml_queue:', $queue->getQueuePrefix());
        $this->assertEquals(90, $queue->getRetryAfter());
        $this->assertEquals(300, $queue->getVisibilityTimeout());
        $this->assertEquals(3, $queue->getMaxAttempts());
    }

    public function testConstructorWithCustomConfig(): void
    {
        $config = [
            'default_queue' => 'custom_default',
            'failed_queue' => 'custom_failed',
            'queue_prefix' => 'custom_prefix',
            'retry_after' => 120,
            'visibility_timeout' => 600,
            'max_attempts' => 5,
        ];

        $queue = new class($config) extends AbstractQueue {
            public function push(array $jobData, string $queue = 'default'): void {}
            public function pop(string $queue = 'default'): ?JobInterface
            {
                return null;
            }
            public function ack(JobInterface $job): void {}
            public function release(JobInterface $job, int $delay = 0): void {}
            public function fail(JobInterface $job, ?\Throwable $error = null): void {}
            public function clear(string $queue = 'default'): void {}
            public function listQueue(string $queue = 'default', int $limit = 100): array
            {
                return [];
            }
            public function count(string $queue = 'default'): int
            {
                return 0;
            }
            public function getFailed(string $queue = 'failed', int $limit = 100): array
            {
                return [];
            }
            public function clearFailed(string $failedQueue = 'failed'): void {}
            public function countFailed(string $failedQueue = 'failed'): int
            {
                return 0;
            }
            public function purge(): void {}
            public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void {}
            public function bulk(array $jobs, string $queue = 'default'): void {}

            public function getDefaultQueue(): string
            {
                return $this->defaultQueue;
            }
            public function getFailedQueue(): string
            {
                return $this->failedQueue;
            }
            public function getQueuePrefix(): string
            {
                return $this->queuePrefix;
            }
            public function getRetryAfter(): int
            {
                return $this->retryAfter;
            }
            public function getVisibilityTimeout(): int
            {
                return $this->visibilityTimeout;
            }
            public function getMaxAttempts(): int
            {
                return $this->maxAttempts;
            }
        };

        $this->assertEquals('custom_default', $queue->getDefaultQueue());
        $this->assertEquals('custom_failed', $queue->getFailedQueue());
        $this->assertEquals('custom_prefix:', $queue->getQueuePrefix());
        $this->assertEquals(120, $queue->getRetryAfter());
        $this->assertEquals(600, $queue->getVisibilityTimeout());
        $this->assertEquals(5, $queue->getMaxAttempts());
    }

    public function testQueuePrefixAlwaysEndsWithColon(): void
    {
        $config = ['queue_prefix' => 'test_prefix'];

        $queue = new class($config) extends AbstractQueue {
            public function push(array $jobData, string $queue = 'default'): void {}
            public function pop(string $queue = 'default'): ?JobInterface
            {
                return null;
            }
            public function ack(JobInterface $job): void {}
            public function release(JobInterface $job, int $delay = 0): void {}
            public function fail(JobInterface $job, ?\Throwable $error = null): void {}
            public function clear(string $queue = 'default'): void {}
            public function listQueue(string $queue = 'default', int $limit = 100): array
            {
                return [];
            }
            public function count(string $queue = 'default'): int
            {
                return 0;
            }
            public function getFailed(string $queue = 'failed', int $limit = 100): array
            {
                return [];
            }
            public function clearFailed(string $failedQueue = 'failed'): void {}
            public function countFailed(string $failedQueue = 'failed'): int
            {
                return 0;
            }
            public function purge(): void {}
            public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void {}
            public function bulk(array $jobs, string $queue = 'default'): void {}

            public function getQueuePrefix(): string
            {
                return $this->queuePrefix;
            }
        };

        $this->assertEquals('test_prefix:', $queue->getQueuePrefix());
    }

    public function testQueuePrefixWithTrailingColon(): void
    {
        $config = ['queue_prefix' => 'test_prefix:'];

        $queue = new class($config) extends AbstractQueue {
            public function push(array $jobData, string $queue = 'default'): void {}
            public function pop(string $queue = 'default'): ?JobInterface
            {
                return null;
            }
            public function ack(JobInterface $job): void {}
            public function release(JobInterface $job, int $delay = 0): void {}
            public function fail(JobInterface $job, ?\Throwable $error = null): void {}
            public function clear(string $queue = 'default'): void {}
            public function listQueue(string $queue = 'default', int $limit = 100): array
            {
                return [];
            }
            public function count(string $queue = 'default'): int
            {
                return 0;
            }
            public function getFailed(string $queue = 'failed', int $limit = 100): array
            {
                return [];
            }
            public function clearFailed(string $failedQueue = 'failed'): void {}
            public function countFailed(string $failedQueue = 'failed'): int
            {
                return 0;
            }
            public function purge(): void {}
            public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void {}
            public function bulk(array $jobs, string $queue = 'default'): void {}

            public function getQueuePrefix(): string
            {
                return $this->queuePrefix;
            }
        };

        $this->assertEquals('test_prefix:', $queue->getQueuePrefix());
    }

    public function testEncodeJobDataReturnsJsonString(): void
    {
        $data = [
            'id' => 'job-123',
            'job' => 'TestJob',
            'payload' => ['key' => 'value'],
            'attempts' => 0,
        ];

        $encoded = $this->queue->testEncodeJobData($data);

        $this->assertIsString($encoded);
        $decoded = json_decode($encoded, true);
        $this->assertEquals($data, $decoded);
    }

    public function testEncodeJobDataHandlesUnicode(): void
    {
        $data = [
            'id' => 'job-123',
            'message' => 'Hello 世界 🌍',
        ];

        $encoded = $this->queue->testEncodeJobData($data);
        $decoded = json_decode($encoded, true);

        $this->assertEquals('Hello 世界 🌍', $decoded['message']);
    }

    public function testEncodeJobDataReturnsEmptyStringOnFailure(): void
    {
        // Create invalid data that can't be JSON encoded (e.g., recursive reference)
        $data = [];
        $data['self'] = &$data;

        $encoded = $this->queue->testEncodeJobData($data);

        $this->assertEquals('', $encoded);
    }

    public function testRetryFailedDefaultImplementation(): void
    {
        $this->queue->retryFailed('failed', 'default', 10);
        $this->assertTrue(true);
    }

    public function testRemoveFailedJobsDefaultImplementationWithSingleId(): void
    {
        $this->queue->removeFailedJobs('job-123', 'failed');
        $this->assertTrue(true);
    }

    public function testRemoveFailedJobsDefaultImplementationWithMultipleIds(): void
    {
        $this->queue->removeFailedJobs(['job-123', 'job-456', 'job-789'], 'failed');
        $this->assertTrue(true);
    }

    public function testPeekDefaultImplementationReturnsNull(): void
    {
        $result = $this->queue->peek('test_queue');
        $this->assertNull($result);
    }

    public function testMoveJobToQueueDefaultImplementation(): void
    {
        $this->queue->moveJobToQueue('job-123', 'queue_a', 'queue_b');
        $this->assertTrue(true);
    }

    public function testProcessDelayedJobsDefaultImplementationReturnsZero(): void
    {
        $result = $this->queue->processDelayedJobs('test_queue');
        $this->assertEquals(0, $result);
    }

    public function testGetStatsDefaultImplementation(): void
    {
        $stats = $this->queue->getStats('test_queue');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('ready', $stats);
        $this->assertArrayHasKey('processing', $stats);
        $this->assertArrayHasKey('delayed', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertEquals(0, $stats['ready']);
        $this->assertEquals(0, $stats['processing']);
        $this->assertEquals(0, $stats['delayed']);
        $this->assertEquals(0, $stats['failed']);
    }

    public function testFindJobDefaultImplementationReturnsNull(): void
    {
        $result = $this->queue->findJob('job-123', 'test_queue');
        $this->assertNull($result);
    }

    public function testDeleteJobDefaultImplementationReturnsFalse(): void
    {
        $result = $this->queue->deleteJob('job-123', 'test_queue');
        $this->assertFalse($result);
    }

    public function testGetQueuesDefaultImplementationReturnsDefaultAndFailed(): void
    {
        $queues = $this->queue->getQueues();

        $this->assertIsArray($queues);
        $this->assertCount(2, $queues);
        $this->assertContains('default', $queues);
        $this->assertContains('failed', $queues);
    }

    public function testGetQueuesWithCustomQueueNames(): void
    {
        $config = [
            'default_queue' => 'my_queue',
            'failed_queue' => 'my_failed',
        ];

        $queue = new class($config) extends AbstractQueue {
            public function push(array $jobData, string $queue = 'default'): void {}
            public function pop(string $queue = 'default'): ?JobInterface
            {
                return null;
            }
            public function ack(JobInterface $job): void {}
            public function release(JobInterface $job, int $delay = 0): void {}
            public function fail(JobInterface $job, ?\Throwable $error = null): void {}
            public function clear(string $queue = 'default'): void {}
            public function listQueue(string $queue = 'default', int $limit = 100): array
            {
                return [];
            }
            public function count(string $queue = 'default'): int
            {
                return 0;
            }
            public function getFailed(string $queue = 'failed', int $limit = 100): array
            {
                return [];
            }
            public function clearFailed(string $failedQueue = 'failed'): void {}
            public function countFailed(string $failedQueue = 'failed'): int
            {
                return 0;
            }
            public function purge(): void {}
            public function later(int $delayInSeconds, array $jobData, string $queue = 'default'): void {}
            public function bulk(array $jobs, string $queue = 'default'): void {}
        };

        $queues = $queue->getQueues();

        $this->assertContains('my_queue', $queues);
        $this->assertContains('my_failed', $queues);
    }

    public function testAllDefaultMethodsCanBeCalledWithoutError(): void
    {
        $this->queue->retryFailed();
        $this->queue->removeFailedJobs('test-id');
        $this->assertNull($this->queue->peek());
        $this->queue->moveJobToQueue('id', 'from', 'to');
        $this->assertEquals(0, $this->queue->processDelayedJobs());
        $this->assertIsArray($this->queue->getStats());
        $this->assertNull($this->queue->findJob('test-id'));
        $this->assertFalse($this->queue->deleteJob('test-id'));
        $this->assertIsArray($this->queue->getQueues());

        $this->assertTrue(true);
    }
}
