<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Batch;

use MonkeysLegion\Queue\Batch\Batch;
use MonkeysLegion\Queue\Batch\BatchRepository;
use MonkeysLegion\Queue\Batch\PendingBatch;
use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Database\Contracts\ConnectionManagerInterface;
use MonkeysLegion\Database\Connection\Connection;
use MonkeysLegion\Database\Connection\ConnectionManager;
use MonkeysLegion\Database\Config\DatabaseConfig;
use MonkeysLegion\Database\Config\DsnConfig;
use MonkeysLegion\Database\Types\DatabaseDriver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PendingBatchTest extends TestCase
{
    private QueueInterface&MockObject $queue;
    private BatchRepository $repository;
    private ConnectionManagerInterface $connectionManager;

    protected function setUp(): void
    {
        $this->queue = $this->createMock(QueueInterface::class);

        $config = new DatabaseConfig(
            name: 'test',
            driver: DatabaseDriver::SQLite,
            dsn: new DsnConfig(
                driver: DatabaseDriver::SQLite,
                memory: true,
            ),
        );

        $this->connectionManager = new ConnectionManager([
            'test' => $config,
        ]);

        $this->connectionManager->connection()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS job_batches (
                id VARCHAR(64) PRIMARY KEY,
                name VARCHAR(255) NULL,
                total_jobs INT NOT NULL,
                pending_jobs INT NOT NULL,
                failed_jobs INT NOT NULL,
                failed_job_ids TEXT NULL,
                options TEXT NULL,
                cancelled_at DOUBLE NULL,
                created_at DOUBLE NOT NULL,
                finished_at DOUBLE NULL
            );
        ");

        $this->repository = new BatchRepository($this->connectionManager);
    }

    public function testDispatchPushesAllJobsWithBatchId(): void
    {
        $job1 = new class implements DispatchableJobInterface {
            public function handle(): void
            {
            }
        };
        $job2 = new class implements DispatchableJobInterface {
            public function handle(): void
            {
            }
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
            public function handle(): void
            {
            }
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
            public function handle(): void
            {
            }
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

    #[AllowMockObjectsWithoutExpectations]
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
            public function handle(): void
            {
            }
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
            new class implements DispatchableJobInterface {
                public function handle(): void
                {
                }
            },
            new class implements DispatchableJobInterface {
                public function handle(): void
                {
                }
            },
            new class implements DispatchableJobInterface {
                public function handle(): void
                {
                }
            },
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
