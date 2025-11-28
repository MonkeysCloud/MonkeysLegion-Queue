<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Integration\Driver;

use Exception;
use MonkeysLegion\Queue\Driver\DatabaseQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Database\SQLite\Connection;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

class DatabaseQueueTest extends TestCase
{
    private ConnectionInterface $connection;
    private DatabaseQueue $queue;
    private string $jobsTable = 'jobs';
    private string $failedTable = 'failed_jobs';

    protected function setUp(): void
    {
        $this->connection  = new Connection([
            'memory' => true,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        ]);

        $this->connection->pdo()->exec("
            CREATE TABLE IF NOT EXISTS jobs (
                id VARCHAR(64) PRIMARY KEY,
                queue VARCHAR(64) NOT NULL DEFAULT 'default',
                job VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                created_at DOUBLE NOT NULL,
                available_at DOUBLE NULL,
                reserved_at DOUBLE NULL,
                failed_at DOUBLE NULL
            );
        ");
        $this->connection->pdo()->exec("
            CREATE TABLE IF NOT EXISTS failed_jobs (
                id VARCHAR(64) PRIMARY KEY,
                job VARCHAR(255) NOT NULL,
                payload JSON NOT NULL,
                original_queue VARCHAR(64) NOT NULL DEFAULT 'default',
                attempts INT NOT NULL DEFAULT 0,
                exception JSON NULL,
                failed_at DOUBLE NOT NULL,
                created_at DOUBLE NULL
            );
        ");

        $this->queue = new DatabaseQueue($this->connection, [
            'table' => $this->jobsTable,
            'failed_table' => $this->failedTable,
        ]);
    }

    protected function tearDown(): void
    {
        $this->connection->disconnect();
    }

    public function testPushAddsJobToQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => ['key' => 'value'],
        ];

        $this->queue->push($jobData, 'default');
        $count = $this->queue->count('default');
        $this->assertEquals(1, $count);
    }

    public function testPopRetrievesJobFromQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => ['foo' => 'bar'],
        ];

        $this->queue->push($jobData, 'default');
        $job = $this->queue->pop('default');

        $this->assertInstanceOf(JobInterface::class, $job);
        $this->assertEquals('TestJob', $job->getData()['job']);
        $this->assertEquals(['foo' => 'bar'], $job->getData()['payload']);
    }

    public function testAckRemovesJobFromQueue(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'default');
        $job = $this->queue->pop('default');
        $this->queue->ack($job);

        $count = $this->queue->count('default');
        $this->assertEquals(0, $count);
    }

    public function testReleaseIncrementsAttemptsAndMakesJobAvailable(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'default');
        $job = $this->queue->pop('default');
        $this->queue->release($job, 0);

        $job2 = $this->queue->pop('default');
        $this->assertEquals(1, $job2->attempts());
    }

    public function testReleaseWithDelaySetsAvailableAt(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'default');
        $job = $this->queue->pop('default');
        $this->queue->release($job, 10);

        // Should not be available immediately
        $job2 = $this->queue->pop('default');
        $this->assertNull($job2);

        // Simulate time passing by updating available_at
        $this->connection->pdo()->exec("UPDATE jobs SET available_at = " . (microtime(true) - 1));

        $job3 = $this->queue->pop('default');
        $this->assertInstanceOf(JobInterface::class, $job3);
    }

    public function testFailMovesJobToFailedTable(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->push($jobData, 'default');
        $job = $this->queue->pop('default');
        $this->queue->fail($job, new \Exception('Failed'));

        $this->assertEquals(0, $this->queue->count('default'));
        $this->assertEquals(1, $this->queue->countFailed());
        $failed = $this->queue->getFailed();
        $this->assertEquals('TestJob', $failed[0]['job']);
        $this->assertStringContainsString('Failed', $failed[0]['exception']['message']);
    }

    public function testLaterAddsJobWithAvailableAt(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => ['delayed' => true],
        ];

        $this->queue->later(5, $jobData, 'default');
        $job = $this->queue->pop('default');
        $this->assertNull($job);

        // Simulate time passing
        $this->connection->pdo()->exec("UPDATE jobs SET available_at = " . (microtime(true) - 1));
        $job2 = $this->queue->pop('default');
        $this->assertInstanceOf(JobInterface::class, $job2);
    }

    public function testProcessDelayedJobsMovesJobsToReady(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
        ];

        $this->queue->later(10, $jobData, 'default');

        $this->assertEquals(0, $this->queue->getStats('default')['ready']);

        // Simulate time passing
        $this->connection->pdo()->exec("UPDATE jobs SET available_at = " . (microtime(true) - 1));
        $moved = $this->queue->processDelayedJobs('default');
        $this->assertEquals(1, $moved);

        $job = $this->queue->pop('default');
        $this->assertInstanceOf(JobInterface::class, $job);
    }

    public function testBulkPushesMultipleJobs(): void
    {
        $jobs = [
            ['job' => 'Job1', 'payload' => ['id' => 1]],
            ['job' => 'Job2', 'payload' => ['id' => 2]],
            ['job' => 'Job3', 'payload' => ['id' => 3]],
        ];

        $this->queue->bulk($jobs, 'default');
        $this->assertEquals(3, $this->queue->count('default'));
    }

    public function testClearRemovesAllJobsFromQueue(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'default');

        $this->queue->clear('default');
        $this->assertEquals(0, $this->queue->count('default'));
    }

    public function testListQueueReturnsJobsWithoutRemoving(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'default');

        $jobs = $this->queue->listQueue('default', 10);
        $this->assertCount(2, $jobs);
        $this->assertEquals(2, $this->queue->count('default'));
    }

    public function testPeekReturnsNextJobWithoutRemoving(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'default');
        $job = $this->queue->peek('default');
        $this->assertInstanceOf(JobInterface::class, $job);
        $this->assertEquals(1, $this->queue->count('default'));
    }

    public function testRetryFailedMovesJobsBackToQueue(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'default');
        $job = $this->queue->pop('default');
        $this->queue->fail($job, new \Exception('Failed'));

        $this->queue->retryFailed(10);

        $this->assertEquals(0, $this->queue->countFailed());
        $this->assertEquals(1, $this->queue->count('default'));
    }

    public function testFindJobLocatesJobById(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => ['find' => 'me']], 'default');
        $jobs = $this->queue->listQueue('default', 1);
        $jobId = $jobs[0]['id'];

        $foundJob = $this->queue->findJob($jobId, 'default');
        $this->assertInstanceOf(JobInterface::class, $foundJob);
        $this->assertEquals($jobId, $foundJob->getId());
    }

    public function testDeleteJobRemovesJobById(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'default');
        $jobs = $this->queue->listQueue('default', 1);
        $jobId = $jobs[0]['id'];

        $deleted = $this->queue->deleteJob($jobId, 'default');
        $this->assertTrue($deleted);
        $this->assertEquals(0, $this->queue->count('default'));
    }

    public function testMoveJobToQueueTransfersJob(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'queue_a');
        $jobs = $this->queue->listQueue('queue_a', 1);
        $jobId = $jobs[0]['id'];

        $this->queue->moveJobToQueue($jobId, 'queue_a', 'queue_b');
        $this->assertEquals(0, $this->queue->count('queue_a'));
        $this->assertEquals(1, $this->queue->count('queue_b'));
    }

    public function testGetStatsReturnsQueueMetrics(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'default');
        $this->queue->pop('default'); // Move one to processing (reserved)
        $this->queue->later(10, ['job' => 'Job3', 'payload' => []], 'default');

        $stats = $this->queue->getStats('default');

        $this->assertEquals(1, $stats['ready']);
        $this->assertEquals(1, $stats['processing']);
        $this->assertEquals(1, $stats['delayed']);
        $this->assertEquals(0, $stats['failed']);
    }

    public function testGetQueuesReturnsAllQueueNames(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'alpha');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'beta');
        $this->queue->push(['job' => 'Job3', 'payload' => []], 'gamma');

        $queues = $this->queue->getQueues();
        $this->assertContains('alpha', $queues);
        $this->assertContains('beta', $queues);
        $this->assertContains('gamma', $queues);
    }

    public function testRemoveFailedJobsDeletesSpecificJobs(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'default');
        $job = $this->queue->pop('default');
        $this->queue->fail($job, new \Exception('Failed 1'));

        $failedJobs = $this->queue->getFailed();
        $jobId = $failedJobs[0]['id'];

        $this->queue->removeFailedJobs($jobId);

        $this->assertEquals(0, $this->queue->countFailed());
    }

    public function testRemoveFailedJobsWithMultipleIds(): void
    {
        $this->queue->push(['job' => 'Job1', 'payload' => []], 'default');
        $this->queue->push(['job' => 'Job2', 'payload' => []], 'default');
        $job1 = $this->queue->pop('default');
        $job2 = $this->queue->pop('default');
        $this->queue->fail($job1, new \Exception('Failed 1'));
        $this->queue->fail($job2, new \Exception('Failed 2'));

        $failedJobs = $this->queue->getFailed();
        $ids = array_column($failedJobs, 'id');
        $this->queue->removeFailedJobs($ids);

        $this->assertEquals(0, $this->queue->countFailed());
    }

    public function testPeekReturnsNullIfNoJobAvailable(): void
    {
        $this->assertNull($this->queue->peek('default'));
    }

    public function testMoveJobToQueueDoesNothingIfJobNotFound(): void
    {
        // Should not throw
        $this->queue->moveJobToQueue('nonexistent', 'queue_a', 'queue_b');
        $this->assertTrue(true);
    }

    public function testProcessDelayedJobsReturnsZeroIfNoneReady(): void
    {
        $this->queue->later(100, ['job' => 'Job1', 'payload' => []], 'default');
        $moved = $this->queue->processDelayedJobs('default');
        $this->assertEquals(0, $moved);
    }

    public function testFindJobReturnsNullIfNotFound(): void
    {
        $this->assertNull($this->queue->findJob('nonexistent', 'default'));
    }

    public function testDeleteJobReturnsFalseIfNotFound(): void
    {
        $this->assertFalse($this->queue->deleteJob('nonexistent', 'default'));
    }

    public function testGetQueuesReturnsDefaultAndFailedIfNoJobs(): void
    {
        $queues = $this->queue->getQueues();
        $this->assertContains('default', $queues);
        $this->assertContains('failed', $queues);
    }

    public function testCountFailedReturnsZeroIfNoFailedJobs(): void
    {
        $this->assertEquals(0, $this->queue->countFailed());
    }

    public function testCountReturnsZeroIfNoJobs(): void
    {
        $this->assertEquals(0, $this->queue->count('default'));
    }

    public function testClearFailedDoesNotThrowIfNoJobs(): void
    {
        $this->queue->clearFailed();
        $this->assertTrue(true);
    }

    public function testClearDoesNotThrowIfNoJobs(): void
    {
        $this->queue->clear('default');
        $this->assertTrue(true);
    }

    public function testBulkWithEmptyArrayDoesNotThrow(): void
    {
        $this->queue->bulk([], 'default');
        $this->assertTrue(true);
    }

    public function testLaterWithZeroDelayPushesJob(): void
    {
        $jobData = ['job' => 'TestJob', 'payload' => []];
        $this->queue->later(0, $jobData, 'default');
        $job = $this->queue->pop('default');
        $this->assertInstanceOf(JobInterface::class, $job);
    }

    public function testPushWithCustomIdAndCreatedAt(): void
    {
        $jobData = [
            'id' => 'custom-id',
            'job' => 'TestJob',
            'payload' => [],
            'created_at' => 1234567890.0,
        ];
        $this->queue->push($jobData, 'default');
        $jobs = $this->queue->listQueue('default');
        $this->assertEquals('custom-id', $jobs[0]['id']);
        $this->assertEquals(1234567890.0, $jobs[0]['created_at']);
    }

    public function testFailWithNullException(): void
    {
        $this->queue->push(['job' => 'TestJob', 'payload' => []], 'default');
        $job = $this->queue->pop('default');
        $this->queue->fail($job, null);
        $failed = $this->queue->getFailed();
        $this->assertNull($failed[0]['exception']);
    }

    public function testGetStatsHandleException(): void
    {
        $brokenQueue = new DatabaseQueue($this->connection, [
            'table' => 'unknown_table'
        ]);
        $stats = $brokenQueue->getStats('default');
        $this->assertEquals(0, $stats['ready']);
        $this->assertEquals(0, $stats['processing']);
        $this->assertEquals(0, $stats['delayed']);
        $this->assertEquals(0, $stats['failed']);
    }

    public function testPopReturnsNullIfReservationFails(): void
    {
        $this->queue->push(['job' => 'Job', 'payload' => []], 'default');

        // manually set reserved_at to simulate another worker
        $this->connection->pdo()->exec("UPDATE jobs SET reserved_at = " . microtime(true));

        $this->assertNull($this->queue->pop('default'));
    }

    public function testRetryFailedOnEmptyFailedTableDoesNothing(): void
    {
        $this->queue->retryFailed();
        $this->assertEquals(0, $this->queue->count());
    }
}
