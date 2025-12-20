<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Batch;

use MonkeysLegion\Queue\Batch\Batch;
use PHPUnit\Framework\TestCase;

class BatchTest extends TestCase
{
    public function testBatchInitialization(): void
    {
        $batch = new Batch('batch-123', 5, microtime(true), 'default');

        $this->assertEquals('batch-123', $batch->id);
        $this->assertEquals(5, $batch->getTotalJobs());
        $this->assertEquals(5, $batch->getPendingJobs());
        $this->assertEquals(0, $batch->getFailedJobs());
        $this->assertFalse($batch->finished());
    }

    public function testRecordSuccessDecreasesPendingJobs(): void
    {
        $batch = new Batch('batch-123', 3, microtime(true));

        $batch->recordSuccess();
        $this->assertEquals(2, $batch->getPendingJobs());

        $batch->recordSuccess();
        $this->assertEquals(1, $batch->getPendingJobs());
    }

    public function testRecordFailureTracksFailedJobs(): void
    {
        $batch = new Batch('batch-123', 3, microtime(true));

        $batch->recordFailure('job-1');
        $this->assertEquals(2, $batch->getPendingJobs());
        $this->assertEquals(1, $batch->getFailedJobs());
        $this->assertContains('job-1', $batch->getFailedJobIds());
    }

    public function testBatchFinishesWhenAllJobsComplete(): void
    {
        $batch = new Batch('batch-123', 2, microtime(true));

        $this->assertFalse($batch->finished());

        $batch->recordSuccess();
        $this->assertFalse($batch->finished());

        $batch->recordSuccess();
        $this->assertTrue($batch->finished());
        $this->assertNotNull($batch->getFinishedAt());
    }

    public function testSuccessfulReturnsTrueWhenAllJobsSucceed(): void
    {
        $batch = new Batch('batch-123', 2, microtime(true));

        $batch->recordSuccess();
        $batch->recordSuccess();

        $this->assertTrue($batch->successful());
    }

    public function testSuccessfulReturnsFalseWhenAnyJobFails(): void
    {
        $batch = new Batch('batch-123', 2, microtime(true));

        $batch->recordSuccess();
        $batch->recordFailure('job-2');

        $this->assertFalse($batch->successful());
        $this->assertTrue($batch->failed());
    }

    public function testCancelSetsCancelledState(): void
    {
        $batch = new Batch('batch-123', 5, microtime(true));

        $this->assertFalse($batch->cancelled());

        $batch->cancel();

        $this->assertTrue($batch->cancelled());
        $this->assertTrue($batch->finished());
        $this->assertFalse($batch->successful());
    }

    public function testProgressCalculation(): void
    {
        $batch = new Batch('batch-123', 4, microtime(true));

        $this->assertEquals(0.0, $batch->progress());

        $batch->recordSuccess();
        $this->assertEquals(25.0, $batch->progress());

        $batch->recordSuccess();
        $this->assertEquals(50.0, $batch->progress());

        $batch->recordFailure('job-3');
        $this->assertEquals(75.0, $batch->progress());

        $batch->recordSuccess();
        $this->assertEquals(100.0, $batch->progress());
    }

    public function testToArrayReturnsAllData(): void
    {
        $batch = new Batch('batch-123', 3, 1234567890.123, 'high');

        $array = $batch->toArray();

        $this->assertEquals('batch-123', $array['id']);
        $this->assertEquals(3, $array['total_jobs']);
        $this->assertEquals(3, $array['pending_jobs']);
        $this->assertEquals(0, $array['failed_jobs']);
        $this->assertEquals(1234567890.123, $array['created_at']);
        $this->assertEquals('high', $array['queue']);
    }

    public function testFromArrayRestoresBatch(): void
    {
        $original = new Batch('batch-123', 5, 1234567890.123, 'default');
        $original->recordSuccess();
        $original->recordFailure('job-2');

        $array = $original->toArray();
        $restored = Batch::fromArray($array);

        $this->assertEquals($original->id, $restored->id);
        $this->assertEquals($original->getTotalJobs(), $restored->getTotalJobs());
        $this->assertEquals($original->getPendingJobs(), $restored->getPendingJobs());
        $this->assertEquals($original->getFailedJobs(), $restored->getFailedJobs());
    }
}
