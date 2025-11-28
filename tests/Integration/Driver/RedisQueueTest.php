<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Integration\Driver;

use MonkeysLegion\Queue\Driver\RedisQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;
use PHPUnit\Framework\TestCase;
use Redis;

class RedisQueueTest extends TestCase
{
    private Redis $redis;
    private RedisQueue $queue;
    private string $testPrefix = 'test_queue:';

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        $this->redis = new Redis();

        try {
            $this->redis->connect('127.0.0.1', 6379);
            $this->redis->select(15); // Use database 15 for testing
        } catch (\RedisException $e) {
            $this->markTestSkipped('Redis server not available: ' . $e->getMessage());
        }

        // Clean up before creating queue
        $this->cleanupRedis();

        $this->queue = new RedisQueue($this->redis, [
            'queue_prefix' => $this->testPrefix,
            'default_queue' => 'test_default',
            'failed_queue' => 'test_failed',
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->cleanupRedis();

        if (isset($this->redis)) {
            $this->redis->close();
        }
    }

    private function cleanupRedis(): void
    {
        // Get all keys matching our test prefix
        $keys = $this->redis->keys($this->testPrefix . '*');

        if (!empty($keys) && is_array($keys)) {
            // Delete all test keys
            $this->redis->del($keys);
        }

        // Also clean up bulk test keys (uses different pattern)
        $bulkKeys = $this->redis->keys('queue:*');
        if (!empty($bulkKeys) && is_array($bulkKeys)) {
            $this->redis->del($bulkKeys);
        }
    }

    public function testPushAddsJobToQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => ['key' => 'value'],
        ];

        $this->queue->push($jobData, 'test_default');

        $count = $this->queue->count('test_default');
        $this->assertEquals(1, $count);
    }

    public function testPopRetrievesJobFromQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => ['key' => 'value'],
        ];

        $this->queue->push($jobData, 'test_default');
        $job = $this->queue->pop('test_default');

        $this->assertInstanceOf(JobInterface::class, $job);
        $this->assertEquals('TestJob', $job->getData()['job']);
        $this->assertEquals(['key' => 'value'], $job->getData()['payload']);
    }

    public function testPopMovesJobToProcessingQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'test_default');
        $this->queue->pop('test_default');

        // Check ready queue is empty
        $readyCount = $this->queue->count('test_default');
        $this->assertEquals(0, $readyCount);

        // Check processing queue has job
        $processingKey = $this->testPrefix . 'test_default:processing';
        $processingCount = $this->redis->lLen($processingKey);
        $this->assertEquals(1, $processingCount);
    }

    public function testAckRemovesJobFromProcessingQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'test_default');
        $job = $this->queue->pop('test_default');
        $this->queue->ack($job);

        // Check processing queue is empty
        $processingKey = $this->testPrefix . 'test_default:processing';
        $processingCount = $this->redis->lLen($processingKey);
        $this->assertEquals(0, $processingCount);
    }

    public function testReleaseMovesJobBackToQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'test_default');
        $job = $this->queue->pop('test_default');
        $this->queue->release($job, 0);

        // Check ready queue has job again
        $readyCount = $this->queue->count('test_default');
        $this->assertEquals(1, $readyCount);

        // Check attempts incremented
        $retryJob = $this->queue->pop('test_default');
        $this->assertEquals(1, $retryJob->attempts());
    }

    public function testReleaseWithDelayAddsToDelayedQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'test_default');
        $job = $this->queue->pop('test_default');
        $this->queue->release($job, 10);

        // Check delayed queue has job
        $delayedKey = $this->testPrefix . 'delayed:test_default';
        $delayedCount = $this->redis->zCard($delayedKey);
        $this->assertEquals(1, $delayedCount);

        // Check ready queue is empty
        $readyCount = $this->queue->count('test_default');
        $this->assertEquals(0, $readyCount);
    }

    public function testFailMovesJobToFailedQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'test_default');
        $job = $this->queue->pop('test_default');

        $exception = new \Exception('Job failed');
        $this->queue->fail($job, $exception);

        $failedCount = $this->queue->countFailed();
        $this->assertEquals(1, $failedCount);

        $failedJobs = $this->queue->getFailed();
        $this->assertCount(1, $failedJobs);
        $this->assertEquals('Job failed', $failedJobs[0]['exception']['message']);
    }

    public function testLaterAddsJobToDelayedQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => ['delayed' => true],
        ];

        $this->queue->later(5, $jobData, 'test_default');

        $delayedKey = $this->testPrefix . 'delayed:test_default';
        $delayedCount = $this->redis->zCard($delayedKey);
        $this->assertEquals(1, $delayedCount);
    }

    public function testProcessDelayedJobsMovesReadyJobs(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        // Add job with negative delay (already ready)
        $this->queue->later(-1, $jobData, 'test_default');

        $moved = $this->queue->processDelayedJobs('test_default');
        $this->assertEquals(1, $moved);

        // Check job moved to ready queue
        $readyCount = $this->queue->count('test_default');
        $this->assertEquals(1, $readyCount);
    }

    public function testBulkPushesMultipleJobs(): void
    {
        $jobs = [
            ['job' => 'Job1', 'payload' => ['id' => 1]],
            ['job' => 'Job2', 'payload' => ['id' => 2]],
            ['job' => 'Job3', 'payload' => ['id' => 3]],
        ];

        $this->queue->bulk($jobs, 'test_default');

        $count = $this->queue->count('test_default');
        $this->assertEquals(3, $count);
    }

    public function testClearRemovesAllJobsFromQueue(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'test_default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'test_default');

        $this->queue->clear('test_default');

        $count = $this->queue->count('test_default');
        $this->assertEquals(0, $count);
    }

    public function testListQueueReturnsJobsWithoutRemoving(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'test_default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'test_default');

        $jobs = $this->queue->listQueue('test_default', 10);

        $this->assertCount(2, $jobs);
        $this->assertEquals(2, $this->queue->count('test_default'));
    }

    public function testPeekReturnsNextJobWithoutRemoving(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'test_default');

        $job = $this->queue->peek('test_default');

        $this->assertInstanceOf(JobInterface::class, $job);
        $this->assertEquals(1, $this->queue->count('test_default'));
    }

    public function testRetryFailedMovesJobsBackToQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'test_default');
        $job = $this->queue->pop('test_default');
        $this->queue->fail($job, new \Exception('Failed'));

        $this->queue->retryFailed(10);

        $this->assertEquals(0, $this->queue->countFailed());
        $this->assertEquals(1, $this->queue->count('test_default'));
    }

    public function testFindJobLocatesJobById(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => ['find' => 'me'],
        ];

        $this->queue->push($jobData, 'test_default');
        $jobs = $this->queue->listQueue('test_default', 1);
        $jobId = $jobs[0]['id'];

        $foundJob = $this->queue->findJob($jobId, 'test_default');

        $this->assertInstanceOf(JobInterface::class, $foundJob);
        $this->assertEquals($jobId, $foundJob->getId());
    }

    public function testDeleteJobRemovesJobById(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'test_default');
        $jobs = $this->queue->listQueue('test_default', 1);
        $jobId = $jobs[0]['id'];

        $deleted = $this->queue->deleteJob($jobId, 'test_default');

        $this->assertTrue($deleted);
        $this->assertEquals(0, $this->queue->count('test_default'));
    }

    public function testMoveJobToQueueTransfersJob(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'queue_a');
        $jobs = $this->queue->listQueue('queue_a', 1);
        $jobId = $jobs[0]['id'];

        $this->queue->moveJobToQueue($jobId, 'queue_a', 'queue_b');

        $this->assertEquals(0, $this->queue->count('queue_a'));
        $this->assertEquals(1, $this->queue->count('queue_b'));
    }

    public function testGetStatsReturnsQueueMetrics(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'test_default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'test_default');
        $this->queue->pop('test_default'); // Move one to processing
        $this->queue->later(10, ['job' => 'Job3', 'payload' => []], 'test_default');

        $stats = $this->queue->getStats('test_default');

        $this->assertEquals(1, $stats['ready']);
        $this->assertEquals(1, $stats['processing']);
        $this->assertEquals(1, $stats['delayed']);
        $this->assertEquals(0, $stats['failed']);
    }

    public function testGetQueuesReturnsAllQueueNames(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'queue_a');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'queue_b');
        $this->queue->push(['job' => 'Job3', 'payload' => []], 'queue_c');

        $queues = $this->queue->getQueues();

        $this->assertContains('queue_a', $queues);
        $this->assertContains('queue_b', $queues);
        $this->assertContains('queue_c', $queues);
    }

    public function testRemoveFailedJobsDeletesSpecificJobs(): void
    {
        // Add multiple failed jobs
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'test_default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'test_default');

        $job1 = $this->queue->pop('test_default');
        $job2 = $this->queue->pop('test_default');

        $this->queue->fail($job1, new \Exception('Failed 1'));
        $this->queue->fail($job2, new \Exception('Failed 2'));

        $failedJobs = $this->queue->getFailed();
        $jobId1 = $failedJobs[0]['id'];

        $this->queue->removeFailedJobs($jobId1);

        $this->assertEquals(1, $this->queue->countFailed());
    }

    public function testClearFailedRemovesAllFailedJobs(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'test_default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'test_default');

        $job1 = $this->queue->pop('test_default');
        $job2 = $this->queue->pop('test_default');

        $this->queue->fail($job1, new \Exception('Failed 1'));
        $this->queue->fail($job2, new \Exception('Failed 2'));

        $this->queue->clearFailed();

        $this->assertEquals(0, $this->queue->countFailed());
    }
}
