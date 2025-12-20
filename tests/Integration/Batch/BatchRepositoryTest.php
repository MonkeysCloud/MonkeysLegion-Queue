<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Integration\Batch;

use MonkeysLegion\Queue\Batch\Batch;
use MonkeysLegion\Queue\Batch\BatchRepository;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Database\SQLite\Connection;
use PHPUnit\Framework\TestCase;
use PDO;

class BatchRepositoryTest extends TestCase
{
    private ConnectionInterface $connection;
    private BatchRepository $repository;
    private string $table = 'job_batches';

    protected function setUp(): void
    {
        $this->connection = new Connection([
            'memory' => true,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        ]);

        $this->connection->pdo()->exec("
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

        $this->repository = new BatchRepository($this->connection, $this->table);
    }

    protected function tearDown(): void
    {
        $this->connection->disconnect();
    }

    public function testStoreAndFindBatch(): void
    {
        $batch = new Batch('batch-123', 5, microtime(true));

        $this->repository->store($batch);

        $found = $this->repository->find('batch-123');
        $this->assertNotNull($found);
        $this->assertEquals('batch-123', $found->id);
        $this->assertEquals(5, $found->getTotalJobs());
    }

    public function testFindReturnsNullForNonExistentBatch(): void
    {
        $found = $this->repository->find('non-existent');
        $this->assertNull($found);
    }

    public function testUpdateBatch(): void
    {
        $batch = new Batch('batch-123', 5, microtime(true));
        $this->repository->store($batch);

        $batch->recordSuccess();
        $this->repository->update($batch);

        $found = $this->repository->find('batch-123');
        $this->assertEquals(4, $found->getPendingJobs());
    }

    public function testDeleteBatch(): void
    {
        $batch = new Batch('batch-123', 5, microtime(true));
        $this->repository->store($batch);

        $this->repository->delete('batch-123');

        $this->assertNull($this->repository->find('batch-123'));
    }

    public function testRecordJobCompletionUpdatesSuccessfully(): void
    {
        $batch = new Batch('batch-123', 2, microtime(true));
        $this->repository->store($batch);

        $this->repository->recordJobCompletion('batch-123', true, 'job-1');

        $found = $this->repository->find('batch-123');
        $this->assertEquals(1, $found->getPendingJobs());
    }

    public function testRecordJobCompletionTracksFailures(): void
    {
        $batch = new Batch('batch-123', 2, microtime(true));
        $this->repository->store($batch);

        $this->repository->recordJobCompletion('batch-123', false, 'job-1');

        $found = $this->repository->find('batch-123');
        $this->assertEquals(1, $found->getFailedJobs());
        $this->assertContains('job-1', $found->getFailedJobIds());
    }

    public function testRecordJobCompletionIgnoresNonExistentBatch(): void
    {
        // Should not throw
        $this->repository->recordJobCompletion('non-existent', true, 'job-1');
        $this->assertTrue(true);
    }

    public function testAllReturnsBatches(): void
    {
        $batch1 = new Batch('batch-1', 5, microtime(true));
        $batch2 = new Batch('batch-2', 3, microtime(true));

        $this->repository->store($batch1);
        $this->repository->store($batch2);

        $all = $this->repository->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('batch-1', $all);
        $this->assertArrayHasKey('batch-2', $all);
    }

    public function testClearRemovesAllBatches(): void
    {
        $this->repository->store(new Batch('batch-1', 5, microtime(true)));
        $this->repository->store(new Batch('batch-2', 3, microtime(true)));

        $this->repository->clear();

        $this->assertEmpty($this->repository->all());
    }
}
