<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Integration;

use MonkeysLegion\Database\SQLite\Connection;
use MonkeysLegion\Queue\Driver\DatabaseQueue;
use MonkeysLegion\Queue\Job\Job;
use MonkeysLegion\Queue\Tests\Fixtures\IntegrationTestJob;
use PHPUnit\Framework\TestCase;
use PDO;

class BackwardCompatibilityTest extends TestCase
{
    private Connection $connection;
    private DatabaseQueue $queue;

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
            CREATE TABLE IF NOT EXISTS jobs (
                id VARCHAR(64) PRIMARY KEY,
                queue VARCHAR(64) NOT NULL DEFAULT 'default',
                job VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                created_at REAL NOT NULL,
                available_at REAL NULL,
                reserved_at REAL NULL,
                failed_at REAL NULL
            );
        ");

        $this->queue = new DatabaseQueue($this->connection);
    }

    public function testHandlesOldPureJsonFormat(): void
    {
        // Simulate a job stored in the OLD pure JSON format
        $oldJobData = [
            'id' => 'old-job-123',
            'job' => IntegrationTestJob::class,
            'payload' => ['data' => 'from-the-past'],
            'attempts' => 0,
            'created_at' => microtime(true),
            'queue' => 'default'
        ];

        $jsonPayload = json_encode($oldJobData);
        
        // Manually insert into DB
        $this->connection->pdo()->prepare("
            INSERT INTO jobs (id, queue, job, payload, attempts, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $oldJobData['id'],
            $oldJobData['queue'],
            $oldJobData['job'],
            $jsonPayload,
            $oldJobData['attempts'],
            $oldJobData['created_at']
        ]);

        // Try to pop and handle
        $job = $this->queue->pop();
        $this->assertNotNull($job, "Job should be poppable even if it's in old JSON format");
        
        IntegrationTestJob::$handled = false;
        IntegrationTestJob::$receivedData = '';
        
        $job->handle();
        
        $this->assertTrue(IntegrationTestJob::$handled);
        $this->assertEquals('from-the-past', IntegrationTestJob::$receivedData);
    }

    public function testHandlesSemiOldJsonWithFrozenObjects(): void
    {
        // Simulate a job stored in JSON format but with "frozen" objects in payload
        // This was the intermediate state after the first refactor
        $semiOldJobData = [
            'id' => 'semi-old-job-456',
            'job' => IntegrationTestJob::class,
            'payload' => [
                'data' => [
                    '__type' => 'serialized_object',
                    'data' => serialize('frozen-value')
                ]
            ],
            'attempts' => 0,
            'created_at' => microtime(true),
            'queue' => 'default'
        ];

        $jsonPayload = json_encode($semiOldJobData);
        
        $this->connection->pdo()->prepare("
            INSERT INTO jobs (id, queue, job, payload, attempts, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $semiOldJobData['id'],
            $semiOldJobData['queue'],
            $semiOldJobData['job'],
            $jsonPayload,
            $semiOldJobData['attempts'],
            $semiOldJobData['created_at']
        ]);

        $job = $this->queue->pop();
        $this->assertNotNull($job);
        
        IntegrationTestJob::$handled = false;
        IntegrationTestJob::$receivedData = '';
        
        $job->handle();
        
        $this->assertTrue(IntegrationTestJob::$handled);
        $this->assertEquals('frozen-value', IntegrationTestJob::$receivedData);
    }

    public function testHandlesNewJsonWrappedSerializedFormat(): void
    {
        // Verify current format works too
        $newJobData = [
            'id' => 'new-job-789',
            'job' => IntegrationTestJob::class,
            'payload' => ['data' => 'modern-value'],
            'attempts' => 0,
            'created_at' => microtime(true),
            'queue' => 'default'
        ];

        // Currently encodeJobData does json_encode(serialize($data))
        $this->queue->push($newJobData);

        $job = $this->queue->pop();
        $this->assertNotNull($job);
        
        IntegrationTestJob::$handled = false;
        IntegrationTestJob::$receivedData = '';
        
        $job->handle();
        
        $this->assertTrue(IntegrationTestJob::$handled);
        $this->assertEquals('modern-value', IntegrationTestJob::$receivedData);
    }

    public function testHandlesInvalidJsonEscapesInOldData(): void
    {
        // Simulate a job with invalid JSON (single backslashes in namespace)
        // This often happens if the DB storer didn't escape backslashes correctly.
        $id = 'bad-json-job';
        // Note the single backslashes in the middle of the string
        $badJson = '{"id": "bad-json-job", "job": "MonkeysLegion\Notifications\Jobs\SendNotificationJob", "payload": {"data": "fixed"}}';
        
        $this->connection->pdo()->prepare("INSERT INTO jobs (id, job, payload, created_at) VALUES (?, ?, ?, ?)")
            ->execute([$id, 'IntegrationTestJob', $badJson, microtime(true)]);

        $job = $this->queue->pop();
        $this->assertNotNull($job, "Should handle invalid JSON by fixing backslashes");
        $this->assertEquals('bad-json-job', $job->getId());
    }
}
