<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Integration\Driver;

use MonkeysLegion\Queue\Driver\RedisQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;
use PHPUnit\Framework\TestCase;
use Redis;

class RedisQueueExtendedTest extends TestCase
{
    private Redis $redis;
    private RedisQueue $queue;
    private string $testPrefix = 'test_ext_queue:';

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        $this->redis = new Redis();

        try {
            $this->redis->connect('127.0.0.1', 6379);
            $this->redis->select(15);
        } catch (\RedisException $e) {
            $this->markTestSkipped('Redis server not available: ' . $e->getMessage());
        }

        $this->cleanupRedis();

        $this->queue = new RedisQueue($this->redis, [
            'queue_prefix' => $this->testPrefix,
            'default_queue' => 'test_default',
            'failed_queue' => 'test_failed',
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanupRedis();
        if (isset($this->redis)) {
            $this->redis->close();
        }
    }

    private function cleanupRedis(): void
    {
        $keys = $this->redis->keys($this->testPrefix . '*');
        if (!empty($keys) && is_array($keys)) {
            $this->redis->del($keys);
        }

        $bulkKeys = $this->redis->keys('queue:*');
        if (!empty($bulkKeys) && is_array($bulkKeys)) {
            $this->redis->del($bulkKeys);
        }
    }

    public function testPushPreservesExistingJobData(): void
    {
        $jobData = [
            'id' => 'custom-id-123',
            'job' => 'TestJob',
            'payload' => ['key' => 'value'],
            'attempts' => 5,
            'created_at' => 1234567890.0,
        ];

        $this->queue->push($jobData, 'test_default');

        $jobs = $this->queue->listQueue('test_default');
        $this->assertEquals('custom-id-123', $jobs[0]['id']);
        $this->assertEquals(5, $jobs[0]['attempts']);
        $this->assertEquals(1234567890.0, $jobs[0]['created_at']);
    }

    public function testPopReturnsNullWhenQueueIsEmpty(): void
    {
        $job = $this->queue->pop('empty_queue');
        $this->assertNull($job);
    }

    public function testMultipleJobsProcessedInOrder(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'test_default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'test_default');
        $this->queue->push(['job' => 'Job3', 'payload' => []], 'test_default');

        $job1 = $this->queue->pop('test_default');
        $job2 = $this->queue->pop('test_default');
        $job3 = $this->queue->pop('test_default');

        $this->assertEquals('Job1', $job1->getData()['job']);
        $this->assertEquals('Job2', $job2->getData()['job']);
        $this->assertEquals('Job3', $job3->getData()['job']);
    }

    public function testAckingNonExistentJobDoesNotThrow(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $mockJob->method('getId')->willReturn('non-existent');
        $mockJob->method('attempts')->willReturn(0);
        $mockJob->method('getData')->willReturn([
            'id' => 'non-existent',
            'job' => 'TestJob',
            'payload' => [],
            'attempts' => 0,
        ]);

        // Should not throw
        $this->queue->ack($mockJob);
        $this->assertTrue(true);
    }

    public function testReleaseIncrementsAttemptsMultipleTimes(): void
    {
        $this->queue->push(['job' => 'TestJob', 'payload' => []], 'test_default');

        $job1 = $this->queue->pop('test_default');
        $this->assertEquals(0, $job1->attempts());

        $this->queue->release($job1, 0);

        $job2 = $this->queue->pop('test_default');
        $this->assertEquals(1, $job2->attempts());

        $this->queue->release($job2, 0);

        $job3 = $this->queue->pop('test_default');
        $this->assertEquals(2, $job3->attempts());
    }

    public function testProcessDelayedJobsReturnsZeroWhenNoJobsReady(): void
    {
        $this->queue->later(100, ['job' => 'TestJob', 'payload' => []], 'test_default');

        $moved = $this->queue->processDelayedJobs('test_default');
        $this->assertEquals(0, $moved);
    }

    public function testProcessDelayedJobsMovesMultipleJobs(): void
    {
        $this->queue->later(-1, ['job' => 'Job1', 'payload' => []], 'test_default');
        $this->queue->later(-1, ['job' => 'Job2', 'payload' => []], 'test_default');
        $this->queue->later(-1, ['job' => 'Job3', 'payload' => []], 'test_default');

        $moved = $this->queue->processDelayedJobs('test_default');
        $this->assertEquals(3, $moved);

        $this->assertEquals(3, $this->queue->count('test_default'));
    }

    public function testFailedJobPreservesExceptionDetails(): void
    {
        $this->queue->push(['job' => 'TestJob', 'payload' => []], 'test_default');
        $job = $this->queue->pop('test_default');

        $exception = new \RuntimeException('Custom error message', 42);
        $this->queue->fail($job, $exception);

        $failed = $this->queue->getFailed();
        $this->assertEquals('Custom error message', $failed[0]['exception']['message']);
        $this->assertStringContainsString(__FILE__, $failed[0]['exception']['file']);
    }

    public function testFailedJobWithoutException(): void
    {
        $this->queue->push(['job' => 'TestJob', 'payload' => []], 'test_default');
        $job = $this->queue->pop('test_default');

        $this->queue->fail($job, null);

        $failed = $this->queue->getFailed();
        $this->assertNull($failed[0]['exception']);
    }

    public function testGetFailedWithLimit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->queue->push(['job' => "Job{$i}", 'payload' => []], 'test_default');
            $job = $this->queue->pop('test_default');
            $this->queue->fail($job, new \Exception("Error {$i}"));
        }

        $failed = $this->queue->getFailed(5);
        $this->assertCount(5, $failed);
    }

    public function testRetryFailedWithSpecificQueue(): void
    {
        $this->queue->push(['job' => 'TestJob', 'payload' => []], 'test_default');
        $job = $this->queue->pop('test_default');
        $this->queue->fail($job, new \Exception('Failed'));

        $this->queue->retryFailed(10);

        $this->assertEquals(1, $this->queue->count('test_default'));
        $this->assertEquals(0, $this->queue->countFailed());
    }

    public function testRemoveMultipleFailedJobs(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'test_default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'test_default');
        $this->queue->push(['job' => 'Job3', 'payload' => []], 'test_default');

        $job1 = $this->queue->pop('test_default');
        $job2 = $this->queue->pop('test_default');
        $job3 = $this->queue->pop('test_default');

        $this->queue->fail($job1, new \Exception('E1'));
        $this->queue->fail($job2, new \Exception('E2'));
        $this->queue->fail($job3, new \Exception('E3'));

        $failed = $this->queue->getFailed();
        $ids = [$failed[0]['id'], $failed[2]['id']];

        $this->queue->removeFailedJobs($ids);

        $this->assertEquals(1, $this->queue->countFailed());
    }

    public function testPeekReturnsNullOnEmptyQueue(): void
    {
        $job = $this->queue->peek('empty_queue');
        $this->assertNull($job);
    }

    public function testFindJobInDelayedQueue(): void
    {
        $this->queue->later(100, ['job' => 'TestJob', 'payload' => ['data' => 'test']], 'test_default');

        $jobs = $this->redis->zRange($this->testPrefix . 'delayed:test_default', 0, -1);
        $jobData = json_decode($jobs[0], true);
        $jobId = $jobData['id'];

        $found = $this->queue->findJob($jobId, 'test_default');
        $this->assertInstanceOf(JobInterface::class, $found);
        $this->assertEquals($jobId, $found->getId());
    }

    public function testFindJobInProcessingQueue(): void
    {
        $this->queue->push(['job' => 'TestJob', 'payload' => []], 'test_default');
        $job = $this->queue->pop('test_default');

        $found = $this->queue->findJob($job->getId(), 'test_default');
        $this->assertInstanceOf(JobInterface::class, $found);
        $this->assertEquals($job->getId(), $found->getId());
    }

    public function testFindJobReturnsNullWhenNotFound(): void
    {
        $found = $this->queue->findJob('non-existent-id', 'test_default');
        $this->assertNull($found);
    }

    public function testDeleteJobFromProcessingQueue(): void
    {
        $this->queue->push(['job' => 'TestJob', 'payload' => []], 'test_default');
        $job = $this->queue->pop('test_default');

        $deleted = $this->queue->deleteJob($job->getId(), 'test_default');
        $this->assertTrue($deleted);

        $processingKey = $this->testPrefix . 'test_default:processing';
        $this->assertEquals(0, $this->redis->lLen($processingKey));
    }

    public function testDeleteJobFromDelayedQueue(): void
    {
        $this->queue->later(100, ['job' => 'TestJob', 'payload' => []], 'test_default');

        $jobs = $this->redis->zRange($this->testPrefix . 'delayed:test_default', 0, -1);
        $jobData = json_decode($jobs[0], true);
        $jobId = $jobData['id'];

        $deleted = $this->queue->deleteJob($jobId, 'test_default');
        $this->assertTrue($deleted);

        $delayedKey = $this->testPrefix . 'delayed:test_default';
        $this->assertEquals(0, $this->redis->zCard($delayedKey));
    }

    public function testDeleteJobReturnsFalseWhenNotFound(): void
    {
        $deleted = $this->queue->deleteJob('non-existent', 'test_default');
        $this->assertFalse($deleted);
    }

    public function testMoveJobBetweenMultipleQueues(): void
    {
        $this->queue->push(['job' => 'TestJob', 'payload' => []], 'queue_1');
        $jobs = $this->queue->listQueue('queue_1');
        $jobId = $jobs[0]['id'];

        $this->queue->moveJobToQueue($jobId, 'queue_1', 'queue_2');
        $this->assertEquals(0, $this->queue->count('queue_1'));
        $this->assertEquals(1, $this->queue->count('queue_2'));

        $jobs2 = $this->queue->listQueue('queue_2');
        $this->assertEquals($jobId, $jobs2[0]['id']);
    }

    public function testGetQueuesListsAllActiveQueues(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'alpha');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'beta');
        $this->queue->push(['job' => 'Job3', 'payload' => []], 'gamma');
        $this->queue->later(10, ['job' => 'Job4', 'payload' => []], 'delta');

        sleep(10); // Wait for delayed job to become ready
        $this->queue->processDelayedJobs('delta'); // this will make 'delta' active

        $queues = $this->queue->getQueues();
        $this->assertContains('alpha', $queues);
        $this->assertContains('beta', $queues);
        $this->assertContains('gamma', $queues);
        $this->assertContains('delta', $queues);
        $this->assertNotContains('failed', $queues);
    }

    public function testPurgeRemovesAllQueues(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'queue_1');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'queue_2');
        $this->queue->later(10, ['job' => 'Job3', 'payload' => []], 'queue_3');

        $job = $this->queue->pop('queue_1');
        $this->queue->fail($job, new \Exception('Failed'));

        $this->queue->purge();

        $this->assertEquals(0, $this->queue->count('queue_1'));
        $this->assertEquals(0, $this->queue->count('queue_2'));
        $this->assertEquals(0, $this->queue->count('queue_3'));
        $this->assertEquals(0, $this->queue->countFailed());
    }

    public function testCountReturnsZeroForEmptyQueue(): void
    {
        $count = $this->queue->count('empty_queue');
        $this->assertEquals(0, $count);
    }

    public function testCountFailedReturnsZeroForEmptyFailedQueue(): void
    {
        $count = $this->queue->countFailed();
        $this->assertEquals(0, $count);
    }

    public function testListQueueReturnsEmptyArrayForEmptyQueue(): void
    {
        $jobs = $this->queue->listQueue('empty_queue');
        $this->assertIsArray($jobs);
        $this->assertEmpty($jobs);
    }

    public function testGetFailedReturnsEmptyArrayWhenNoFailures(): void
    {
        $failed = $this->queue->getFailed();
        $this->assertIsArray($failed);
        $this->assertEmpty($failed);
    }
}
