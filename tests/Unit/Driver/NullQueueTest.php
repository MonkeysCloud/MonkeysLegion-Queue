<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Driver;

use MonkeysLegion\Queue\Driver\NullQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;
use PHPUnit\Framework\TestCase;

class NullQueueTest extends TestCase
{
    private NullQueue $queue;

    protected function setUp(): void
    {
        $this->queue = new NullQueue([]);
    }

    public function testPushDoesNothing(): void
    {
        $this->queue->push(['job' => 'TestJob', 'payload' => []], 'test');
        $this->assertEquals(0, $this->queue->count('test'));
    }

    public function testPopAlwaysReturnsNull(): void
    {
        $result = $this->queue->pop('test');
        $this->assertNull($result);
    }

    public function testAckDoesNothing(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $this->queue->ack($mockJob);
        $this->assertTrue(true); // Just verify no exception
    }

    public function testReleaseDoesNothing(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $this->queue->release($mockJob, 10);
        $this->assertTrue(true);
    }

    public function testFailDoesNothing(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $this->queue->fail($mockJob, new \Exception('Test'));
        $this->assertEquals(0, $this->queue->countFailed());
    }

    public function testClearDoesNothing(): void
    {
        $this->queue->clear('test');
        $this->assertTrue(true);
    }

    public function testListQueueReturnsEmptyArray(): void
    {
        $result = $this->queue->listQueue('test');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCountReturnsZero(): void
    {
        $result = $this->queue->count('test');
        $this->assertEquals(0, $result);
    }

    public function testGetFailedReturnsEmptyArray(): void
    {
        $result = $this->queue->getFailed();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testClearFailedDoesNothing(): void
    {
        $this->queue->clearFailed();
        $this->assertTrue(true);
    }

    public function testCountFailedReturnsZero(): void
    {
        $result = $this->queue->countFailed();
        $this->assertEquals(0, $result);
    }

    public function testPurgeDoesNothing(): void
    {
        $this->queue->purge();
        $this->assertTrue(true);
    }

    public function testLaterDoesNothing(): void
    {
        $this->queue->later(10, ['job' => 'TestJob', 'payload' => []], 'test');
        $this->assertEquals(0, $this->queue->count('test'));
    }

    public function testBulkDoesNothing(): void
    {
        $jobs = [
            ['job' => 'Job1', 'payload' => []],
            ['job' => 'Job2', 'payload' => []],
        ];
        $this->queue->bulk($jobs, 'test');
        $this->assertEquals(0, $this->queue->count('test'));
    }

    public function testRetryFailedDoesNothing(): void
    {
        $this->queue->retryFailed();
        $this->assertTrue(true);
    }

    public function testRemoveFailedJobsDoesNothing(): void
    {
        $this->queue->removeFailedJobs('job-123');
        $this->queue->removeFailedJobs(['job-1', 'job-2']);
        $this->assertTrue(true);
    }

    public function testPeekReturnsNull(): void
    {
        $result = $this->queue->peek('test');
        $this->assertNull($result);
    }

    public function testMoveJobToQueueDoesNothing(): void
    {
        $this->queue->moveJobToQueue('job-123', 'from', 'to');
        $this->assertTrue(true);
    }

    public function testProcessDelayedJobsReturnsZero(): void
    {
        $result = $this->queue->processDelayedJobs('test');
        $this->assertEquals(0, $result);
    }

    public function testGetStatsReturnsZeroStats(): void
    {
        $stats = $this->queue->getStats('test');

        $this->assertIsArray($stats);
        $this->assertEquals(0, $stats['ready']);
        $this->assertEquals(0, $stats['processing']);
        $this->assertEquals(0, $stats['delayed']);
        $this->assertEquals(0, $stats['failed']);
    }

    public function testFindJobReturnsNull(): void
    {
        $result = $this->queue->findJob('job-123', 'test');
        $this->assertNull($result);
    }

    public function testDeleteJobReturnsFalse(): void
    {
        $result = $this->queue->deleteJob('job-123', 'test');
        $this->assertFalse($result);
    }

    public function testGetQueuesReturnsDefaultQueues(): void
    {
        $queues = $this->queue->getQueues();

        $this->assertIsArray($queues);
        $this->assertContains('default', $queues);
        $this->assertContains('failed', $queues);
    }

    public function testMultipleOperationsDoNothing(): void
    {
        // Push multiple jobs
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'test');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'test');
        $this->queue->later(10, ['job' => 'Job3', 'payload' => []], 'test');

        // Nothing should be in the queue
        $this->assertEquals(0, $this->queue->count('test'));
        $this->assertNull($this->queue->pop('test'));
        $this->assertEmpty($this->queue->listQueue('test'));

        // Stats should be zero
        $stats = $this->queue->getStats('test');
        $this->assertEquals(0, array_sum($stats));
    }

    public function testConstructorWithCustomConfig(): void
    {
        $queue = new NullQueue([
            'default_queue' => 'custom_default',
            'failed_queue' => 'custom_failed',
        ]);

        $queues = $queue->getQueues();
        $this->assertContains('custom_default', $queues);
        $this->assertContains('custom_failed', $queues);
    }
}
