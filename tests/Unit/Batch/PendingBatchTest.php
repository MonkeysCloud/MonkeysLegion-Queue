<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Batch;

use MonkeysLegion\Queue\Batch\Batch;
use MonkeysLegion\Queue\Batch\BatchRepository;
use MonkeysLegion\Queue\Batch\PendingBatch;
use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use PHPUnit\Framework\TestCase;

class PendingBatchTest extends TestCase
{
    private QueueInterface $queue;
    private BatchRepository $repository;

    protected function setUp(): void
    {
        $this->queue = $this->createMock(QueueInterface::class);
        $this->repository = new BatchRepository();
        $this->repository->clear();
    }

    public function testDispatchPushesAllJobsWithBatchId(): void
    {
        $job1 = new class implements DispatchableJobInterface {
            public function handle(): void {}
        };
        $job2 = new class implements DispatchableJobInterface {
            public function handle(): void {}
        };

        $capturedJobData = [];

        $this->queue
            ->expects($this->exactly(2))
            ->method('push')
            ->willReturnCallback(function ($jobData, $queue) use (&$capturedJobData) {
                $capturedJobData[] = $jobData;
            });

        $pendingBatch = new PendingBatch($this->queue, $this->repository);
        $batch = $pendingBatch->add([$job1, $job2])->dispatch();

        $this->assertInstanceOf(Batch::class, $batch);
        $this->assertCount(2, $capturedJobData);
        $this->assertArrayHasKey('batch_id', $capturedJobData[0]);
        $this->assertEquals($batch->id, $capturedJobData[0]['batch_id']);
    }

    public function testDispatchStoresBatchInRepository(): void
    {
        $job = new class implements DispatchableJobInterface {
            public function handle(): void {}
        };

        $this->queue
            ->expects($this->once())
            ->method('push');

        $pendingBatch = new PendingBatch($this->queue, $this->repository);
        $batch = $pendingBatch->add([$job])->dispatch();

        $found = $this->repository->find($batch->id);
        $this->assertNotNull($found);
        $this->assertEquals($batch->id, $found->id);
    }

    public function testOnQueueSetsQueueForBatch(): void
    {
        $job = new class implements DispatchableJobInterface {
            public function handle(): void {}
        };

        $capturedQueue = null;

        $this->queue
            ->expects($this->once())
            ->method('push')
            ->willReturnCallback(function ($jobData, $queue) use (&$capturedQueue) {
                $capturedQueue = $queue;
            });

        $pendingBatch = new PendingBatch($this->queue, $this->repository);
        $pendingBatch->add([$job])->onQueue('high-priority')->dispatch();

        $this->assertEquals('high-priority', $capturedQueue);
    }

    public function testDispatchThrowsOnEmptyBatch(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot dispatch empty batch');

        $pendingBatch = new PendingBatch($this->queue, $this->repository);
        $pendingBatch->dispatch();
    }

    public function testThenCatchFinallyStoreCallbacks(): void
    {
        $job = new class implements DispatchableJobInterface {
            public function handle(): void {}
        };

        $this->queue
            ->expects($this->once())
            ->method('push');

        $pendingBatch = new PendingBatch($this->queue, $this->repository);
        $batch = $pendingBatch
            ->add([$job])
            ->then('ThenCallback')
            ->catch('CatchCallback')
            ->finally('FinallyCallback')
            ->dispatch();

        $this->assertEquals('ThenCallback', $batch->thenCallback);
        $this->assertEquals('CatchCallback', $batch->catchCallback);
        $this->assertEquals('FinallyCallback', $batch->finallyCallback);
    }

    public function testBatchTracksCorrectJobCount(): void
    {
        $jobs = [
            new class implements DispatchableJobInterface { public function handle(): void {} },
            new class implements DispatchableJobInterface { public function handle(): void {} },
            new class implements DispatchableJobInterface { public function handle(): void {} },
        ];

        $this->queue
            ->expects($this->exactly(3))
            ->method('push');

        $pendingBatch = new PendingBatch($this->queue, $this->repository);
        $batch = $pendingBatch->add($jobs)->dispatch();

        $this->assertEquals(3, $batch->getTotalJobs());
        $this->assertEquals(3, $batch->getPendingJobs());
    }
}
