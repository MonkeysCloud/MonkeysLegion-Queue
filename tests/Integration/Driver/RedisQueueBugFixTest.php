<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Integration\Driver;

use MonkeysLegion\Queue\Driver\RedisQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Tests for bug fixes:
 * 1. Processing queue leak for non-default queues
 * 2. Bulk enqueue ignoring configured prefix
 */
class RedisQueueBugFixTest extends TestCase
{
    private Redis $redis;
    private RedisQueue $queue;
    private string $testPrefix = 'test_bugfix_queue:';

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
            'default_queue' => 'default',
            'failed_queue' => 'failed',
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

        // Clean up unprefixed keys that might be created by buggy bulk()
        $bulkKeys = $this->redis->keys('queue:*');
        if (!empty($bulkKeys) && is_array($bulkKeys)) {
            $this->redis->del($bulkKeys);
        }
    }

    // ===================================================================
    // BUG FIX #1: Processing Queue Leak Tests
    // ===================================================================

    /**
     * Test that ack() removes job from correct non-default processing queue
     * 
     * Bug: ack() was using $this->defaultQueue instead of job's actual queue,
     * causing jobs from custom queues to remain stuck in processing lists
     */
    public function testAckRemovesJobFromNonDefaultProcessingQueue(): void
    {
        $customQueue = 'emails';

        // Push job to custom queue
        $this->queue->push([
            'job' => 'SendEmailJob',
            'payload' => ['to' => 'user@example.com'],
        ], $customQueue);

        // Pop from custom queue
        $job = $this->queue->pop($customQueue);
        $this->assertNotNull($job);

        // Verify job is in custom queue's processing list
        $processingKey = $this->testPrefix . $customQueue . ':processing';
        $processingCount = $this->redis->lLen($processingKey);
        $this->assertEquals(1, $processingCount, 'Job should be in custom queue processing list');

        // Acknowledge the job
        $this->queue->ack($job);

        // Verify job removed from custom queue's processing list
        $processingCount = $this->redis->lLen($processingKey);
        $this->assertEquals(0, $processingCount, 'Job should be removed from custom queue processing list after ack');

        // Verify default processing queue is still empty
        $defaultProcessingKey = $this->testPrefix . 'default:processing';
        $defaultProcessingCount = $this->redis->lLen($defaultProcessingKey);
        $this->assertEquals(0, $defaultProcessingCount, 'Default queue processing list should remain empty');
    }

    /**
     * Test that release() removes job from correct non-default processing queue
     */
    public function testReleaseRemovesJobFromNonDefaultProcessingQueue(): void
    {
        $customQueue = 'notifications';

        $this->queue->push([
            'job' => 'SendNotificationJob',
            'payload' => ['user_id' => 123],
        ], $customQueue);

        $job = $this->queue->pop($customQueue);

        // Verify in processing
        $processingKey = $this->testPrefix . $customQueue . ':processing';
        $this->assertEquals(1, $this->redis->lLen($processingKey));

        // Release without delay
        $this->queue->release($job, 0);

        // Verify removed from processing
        $this->assertEquals(
            0,
            $this->redis->lLen($processingKey),
            'Job should be removed from custom queue processing list after release'
        );

        // Verify back in ready queue
        $this->assertEquals(
            1,
            $this->queue->count($customQueue),
            'Job should be back in custom ready queue'
        );

        // Verify attempts incremented
        $releasedJob = $this->queue->pop($customQueue);
        $this->assertEquals(1, $releasedJob->attempts());
    }

    /**
     * Test release with delay removes from correct processing queue
     */
    public function testReleaseWithDelayRemovesFromNonDefaultProcessingQueue(): void
    {
        $customQueue = 'background';

        $this->queue->push([
            'job' => 'ProcessImageJob',
            'payload' => ['image_id' => 999],
        ], $customQueue);

        $job = $this->queue->pop($customQueue);

        // Release with delay
        $this->queue->release($job, 30);

        // Verify removed from processing
        $processingKey = $this->testPrefix . $customQueue . ':processing';
        $this->assertEquals(0, $this->redis->lLen($processingKey));

        // Verify in delayed queue for custom queue
        $delayedKey = $this->testPrefix . 'delayed:' . $customQueue;
        $this->assertEquals(1, $this->redis->zCard($delayedKey));

        // Verify NOT in default queue's delayed list
        $defaultDelayedKey = $this->testPrefix . 'delayed:default';
        $this->assertEquals(0, $this->redis->zCard($defaultDelayedKey));
    }

    /**
     * Test multiple jobs in different queues are acked correctly
     */
    public function testMultipleQueuesAckCorrectly(): void
    {
        $queues = ['high', 'medium', 'low'];
        $jobs = [];

        // Push and pop jobs from different queues
        foreach ($queues as $queue) {
            $this->queue->push([
                'job' => 'TestJob',
                'payload' => ['priority' => $queue],
            ], $queue);

            $jobs[$queue] = $this->queue->pop($queue);
        }

        // Verify all in their respective processing queues
        foreach ($queues as $queue) {
            $processingKey = $this->testPrefix . $queue . ':processing';
            $this->assertEquals(1, $this->redis->lLen($processingKey));
        }

        // Acknowledge all jobs
        foreach ($jobs as $queue => $job) {
            $this->queue->ack($job);
        }

        // Verify all processing queues are empty
        foreach ($queues as $queue) {
            $processingKey = $this->testPrefix . $queue . ':processing';
            $this->assertEquals(
                0,
                $this->redis->lLen($processingKey),
                "Processing queue for {$queue} should be empty after ack"
            );
        }
    }

    /**
     * Test job queue tracking persists through operations
     */
    public function testJobQueueTrackingPersistsThroughOperations(): void
    {
        $customQueue = 'reports';

        $this->queue->push([
            'job' => 'GenerateReportJob',
            'payload' => ['report_id' => 456],
        ], $customQueue);

        // Pop and verify queue tracking
        $job = $this->queue->pop($customQueue);
        $jobData = $job->getData();
        $this->assertEquals(
            $customQueue,
            $jobData['queue'],
            'Job should track its original queue'
        );

        // Release and re-pop
        $this->queue->release($job, 0);
        $releasedJob = $this->queue->pop($customQueue);
        $releasedData = $releasedJob->getData();

        $this->assertEquals(
            $customQueue,
            $releasedData['queue'],
            'Queue tracking should persist after release'
        );

        // Final ack should work on correct queue
        $this->queue->ack($releasedJob);

        $processingKey = $this->testPrefix . $customQueue . ':processing';
        $this->assertEquals(0, $this->redis->lLen($processingKey));
    }

    /**
     * Test failed jobs from non-default queues can be retried correctly
     */
    public function testFailedJobFromNonDefaultQueueRetainsQueueInfo(): void
    {
        $customQueue = 'critical';

        $this->queue->push([
            'job' => 'CriticalJob',
            'payload' => ['data' => 'important'],
        ], $customQueue);

        $job = $this->queue->pop($customQueue);

        // Fail the job
        $this->queue->fail($job, new \Exception('Job failed'));

        // Verify failed job has queue info
        $failedJobs = $this->queue->getFailed();
        $this->assertCount(1, $failedJobs);
        $this->assertEquals(
            $customQueue,
            $failedJobs[0]['queue'],
            'Failed job should retain original queue name'
        );

        // Retry should restore to original queue
        $this->queue->retryFailed();

        // Job should be in original queue, not target queue
        $this->assertEquals(
            1,
            $this->queue->count($customQueue),
            'Retried job should go back to original queue'
        );
        $this->assertEquals(
            0,
            $this->queue->count('default'),
            'Retried job should not be in default queue when it has queue tracking'
        );
    }

    // ===================================================================
    // BUG FIX #2: Bulk Enqueue Prefix Tests
    // ===================================================================

    /**
     * Test bulk() respects configured prefix
     * 
     * Bug: bulk() was using hard-coded "queue:{queue}" instead of
     * configured prefix, making bulked jobs invisible to other operations
     */
    public function testBulkRespectsConfiguredPrefix(): void
    {
        $jobs = [
            ['job' => 'Job1', 'payload' => ['id' => 1]],
            ['job' => 'Job2', 'payload' => ['id' => 2]],
            ['job' => 'Job3', 'payload' => ['id' => 3]],
        ];

        $this->queue->bulk($jobs, 'default');

        // Verify jobs are in PREFIXED queue
        $correctKey = $this->testPrefix . 'default';
        $correctCount = $this->redis->lLen($correctKey);
        $this->assertEquals(
            3,
            $correctCount,
            'Bulk jobs should be in prefixed queue'
        );

        // Verify jobs are NOT in unprefixed queue
        $wrongKey = 'queue:default';
        $wrongCount = $this->redis->lLen($wrongKey);
        $this->assertEquals(
            0,
            $wrongCount,
            'Bulk jobs should NOT be in unprefixed "queue:" location'
        );
    }

    /**
     * Test bulk jobs are visible to listQueue()
     */
    public function testBulkJobsVisibleToListQueue(): void
    {
        $jobs = [
            ['job' => 'ListJob1', 'payload' => ['data' => 'a']],
            ['job' => 'ListJob2', 'payload' => ['data' => 'b']],
            ['job' => 'ListJob3', 'payload' => ['data' => 'c']],
        ];

        $this->queue->bulk($jobs, 'default');

        $listedJobs = $this->queue->listQueue('default');

        $this->assertCount(
            3,
            $listedJobs,
            'All bulk jobs should be visible to listQueue()'
        );

        // Verify job data integrity
        $jobNames = array_column($listedJobs, 'job');
        $this->assertContains('ListJob1', $jobNames);
        $this->assertContains('ListJob2', $jobNames);
        $this->assertContains('ListJob3', $jobNames);
    }

    /**
     * Test bulk jobs can be popped
     */
    public function testBulkJobsCanBePopped(): void
    {
        $jobs = [
            ['job' => 'PopJob1', 'payload' => ['index' => 1]],
            ['job' => 'PopJob2', 'payload' => ['index' => 2]],
        ];

        $this->queue->bulk($jobs, 'default');

        // Should be able to pop first job
        $job1 = $this->queue->pop('default');
        $this->assertNotNull($job1, 'Should be able to pop first bulk job');
        $this->assertEquals('PopJob1', $job1->getData()['job']);

        // Should be able to pop second job
        $job2 = $this->queue->pop('default');
        $this->assertNotNull($job2, 'Should be able to pop second bulk job');
        $this->assertEquals('PopJob2', $job2->getData()['job']);

        // Queue should be empty now
        $this->assertEquals(0, $this->queue->count('default'));
    }

    /**
     * Test bulk jobs to custom queue with prefix
     */
    public function testBulkToCustomQueueWithPrefix(): void
    {
        $customQueue = 'batch-processing';

        $jobs = [
            ['job' => 'BatchJob1', 'payload' => ['batch' => 1]],
            ['job' => 'BatchJob2', 'payload' => ['batch' => 2]],
            ['job' => 'BatchJob3', 'payload' => ['batch' => 3]],
        ];

        $this->queue->bulk($jobs, $customQueue);

        // Verify in correct prefixed location
        $correctKey = $this->testPrefix . $customQueue;
        $this->assertEquals(
            3,
            $this->redis->lLen($correctKey),
            'Bulk jobs should be in prefixed custom queue'
        );

        // Verify NOT in wrong location
        $wrongKey = 'queue:' . $customQueue;
        $this->assertEquals(
            0,
            $this->redis->lLen($wrongKey),
            'Bulk jobs should NOT be in unprefixed location'
        );

        // Verify can be listed and counted
        $this->assertEquals(3, $this->queue->count($customQueue));
        $listedJobs = $this->queue->listQueue($customQueue);
        $this->assertCount(3, $listedJobs);
    }

    /**
     * Test bulk validation still throws on missing job key
     */
    public function testBulkValidationStillWorks(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Bulk job entry missing 'job' key");

        $invalidJobs = [
            ['job' => 'ValidJob', 'payload' => []],
            ['payload' => ['invalid' => 'no job key']], // Missing 'job'
            ['job' => 'AnotherValidJob', 'payload' => []],
        ];

        $this->queue->bulk($invalidJobs);
    }

    /**
     * Test bulk with empty array does nothing
     */
    public function testBulkWithEmptyArrayDoesNothing(): void
    {
        $this->queue->bulk([], 'default');

        $this->assertEquals(0, $this->queue->count('default'));
    }

    /**
     * Test bulk jobs have proper queue tracking
     */
    public function testBulkJobsHaveQueueTracking(): void
    {
        $customQueue = 'tracked';

        $jobs = [
            ['job' => 'TrackedJob1', 'payload' => []],
            ['job' => 'TrackedJob2', 'payload' => []],
        ];

        $this->queue->bulk($jobs, $customQueue);

        // Pop and verify queue tracking
        $job1 = $this->queue->pop($customQueue);
        $this->assertEquals(
            $customQueue,
            $job1->getData()['queue'],
            'Bulk job should have queue tracking'
        );

        // Should ack from correct queue
        $this->queue->ack($job1);

        $processingKey = $this->testPrefix . $customQueue . ':processing';
        $this->assertEquals(
            0,
            $this->redis->lLen($processingKey),
            'Ack should work correctly for bulk job with queue tracking'
        );
    }

    /**
     * Test bulk followed by regular push uses same location
     */
    public function testBulkAndRegularPushUseSameLocation(): void
    {
        // Bulk push
        $bulkJobs = [
            ['job' => 'BulkJob', 'payload' => []],
        ];
        $this->queue->bulk($bulkJobs, 'default');

        // Regular push
        $this->queue->push([
            'job' => 'RegularJob',
            'payload' => [],
        ], 'default');

        // Both should be in same queue
        $this->assertEquals(
            2,
            $this->queue->count('default'),
            'Bulk and regular push should use same queue location'
        );

        // Both should be poppable
        $job1 = $this->queue->pop('default');
        $job2 = $this->queue->pop('default');

        $this->assertNotNull($job1);
        $this->assertNotNull($job2);

        $jobNames = [
            $job1->getData()['job'],
            $job2->getData()['job']
        ];

        $this->assertContains('BulkJob', $jobNames);
        $this->assertContains('RegularJob', $jobNames);
    }

    // ===================================================================
    // Integration Tests - Both Fixes Working Together
    // ===================================================================

    /**
     * Test bulk to non-default queue with proper ack/release
     */
    public function testBulkToNonDefaultQueueWithAckRelease(): void
    {
        $customQueue = 'integration';

        $jobs = [
            ['job' => 'IntJob1', 'payload' => ['id' => 1]],
            ['job' => 'IntJob2', 'payload' => ['id' => 2]],
            ['job' => 'IntJob3', 'payload' => ['id' => 3]],
        ];

        // Bulk push to custom queue
        $this->queue->bulk($jobs, $customQueue);

        // Pop first job
        $job1 = $this->queue->pop($customQueue);
        $this->assertNotNull($job1);

        // Ack it (should remove from correct processing queue)
        $this->queue->ack($job1);

        $processingKey = $this->testPrefix . $customQueue . ':processing';
        $this->assertEquals(
            0,
            $this->redis->lLen($processingKey),
            'Ack should work on bulk job from custom queue'
        );

        // Pop second job and release it
        $job2 = $this->queue->pop($customQueue);
        $this->queue->release($job2, 0);

        // Should be back in queue
        $this->assertEquals(
            2,
            $this->queue->count($customQueue),
            'Released bulk job should be back in custom queue'
        );

        // Processing queue should be empty
        $this->assertEquals(
            0,
            $this->redis->lLen($processingKey),
            'Release should remove from correct processing queue'
        );
    }

    /**
     * Stress test: Multiple queues with bulk and individual operations
     */
    public function testMultipleQueuesWithMixedOperations(): void
    {
        $queues = ['queue-a', 'queue-b', 'queue-c'];

        foreach ($queues as $queue) {
            // Bulk push 3 jobs
            $this->queue->bulk([
                ['job' => "Bulk1-{$queue}", 'payload' => []],
                ['job' => "Bulk2-{$queue}", 'payload' => []],
                ['job' => "Bulk3-{$queue}", 'payload' => []],
            ], $queue);

            // Regular push 2 jobs
            $this->queue->push(['job' => "Regular1-{$queue}", 'payload' => []], $queue);
            $this->queue->push(['job' => "Regular2-{$queue}", 'payload' => []], $queue);
        }

        // Each queue should have 5 jobs
        foreach ($queues as $queue) {
            $this->assertEquals(
                5,
                $this->queue->count($queue),
                "Queue {$queue} should have 5 jobs"
            );
        }

        // Process jobs from each queue
        foreach ($queues as $queue) {
            for ($i = 0; $i < 5; $i++) {
                $job = $this->queue->pop($queue);
                $this->assertNotNull($job);

                // Alternate between ack and release
                if ($i % 2 === 0) {
                    $this->queue->ack($job);
                } else {
                    $this->queue->release($job, 0);
                }
            }
        }

        // Verify processing queues are all empty
        foreach ($queues as $queue) {
            $processingKey = $this->testPrefix . $queue . ':processing';
            $this->assertEquals(
                0,
                $this->redis->lLen($processingKey),
                "Processing queue for {$queue} should be empty"
            );
        }

        // Each queue should have 2 released jobs (indices 1, 3)
        foreach ($queues as $queue) {
            $this->assertEquals(
                2,
                $this->queue->count($queue),
                "Queue {$queue} should have 2 released jobs"
            );
        }
    }

    /**
     * Test getStats works correctly with bulk jobs
     */
    public function testGetStatsWithBulkJobs(): void
    {
        // Bulk push to ready queue
        $this->queue->bulk([
            ['job' => 'StatJob1', 'payload' => []],
            ['job' => 'StatJob2', 'payload' => []],
        ], 'default');

        // Pop one to processing
        $job = $this->queue->pop('default');

        // Add delayed job
        $this->queue->later(10, ['job' => 'DelayedJob', 'payload' => []], 'default');

        // Get stats
        $stats = $this->queue->getStats('default');

        $this->assertEquals(1, $stats['ready'], 'Should count bulk job in ready');
        $this->assertEquals(1, $stats['processing'], 'Should count popped bulk job');
        $this->assertEquals(1, $stats['delayed'], 'Should count delayed job');
    }

    /**
     * Test clear works with bulk jobs
     */
    public function testClearWorksWithBulkJobs(): void
    {
        $this->queue->bulk([
            ['job' => 'ClearJob1', 'payload' => []],
            ['job' => 'ClearJob2', 'payload' => []],
            ['job' => 'ClearJob3', 'payload' => []],
        ], 'default');

        $this->assertEquals(3, $this->queue->count('default'));

        $this->queue->clear('default');

        $this->assertEquals(0, $this->queue->count('default'));
    }

    /**
     * Test purge removes bulk jobs from all queues
     */
    public function testPurgeRemovesBulkJobsFromAllQueues(): void
    {
        $this->queue->bulk([
            ['job' => 'Job1', 'payload' => []],
        ], 'queue1');

        $this->queue->bulk([
            ['job' => 'Job2', 'payload' => []],
        ], 'queue2');

        $this->queue->purge();

        $this->assertEquals(0, $this->queue->count('queue1'));
        $this->assertEquals(0, $this->queue->count('queue2'));
    }
}
