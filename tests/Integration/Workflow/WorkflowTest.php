<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Integration\Workflow;

use MonkeysLegion\Queue\Driver\DatabaseQueue;
use MonkeysLegion\Queue\Driver\RedisQueue;
use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Queue\Worker\Worker;
use MonkeysLegion\Database\SQLite\Connection as SQLiteConnection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Redis;

// Define test job classes outside of the test method
class SuccessfulTestJob
{
    public static bool $handled = false;
    
    public function handle(): void
    {
        SuccessfulTestJob::$handled = true;
    }
}

class FailingTestJob
{
    public static bool $attempted = false;
    
    public function handle(): void
    {
        FailingTestJob::$attempted = true;
        throw new \RuntimeException('Job failed intentionally');
    }
}

class WorkflowTest extends TestCase
{
    private static ?Redis $redis = null;
    private static bool $redisAvailable = false;

    public static function setUpBeforeClass(): void
    {
        if (extension_loaded('redis')) {
            try {
                static::$redis = new Redis();
                static::$redis->connect('127.0.0.1', 6379);
                static::$redis->select(15);
                static::$redisAvailable = true;
            } catch (\RedisException) {
                static::$redisAvailable = false;
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (static::$redisAvailable && static::$redis) {
            $keys = static::$redis->keys('workflow_test*');
            if (!empty($keys)) {
                static::$redis->del($keys);
            }
            static::$redis->close();
        }
    }

    public static function driverProvider(): array
    {
        $drivers = [];
        
        // Database driver
        try {
            $conn = new SQLiteConnection([
                'memory' => true,
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            ]);

            $conn->pdo()->exec("
                CREATE TABLE jobs (
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
            
            $conn->pdo()->exec("
                CREATE TABLE failed_jobs (
                    id VARCHAR(64) PRIMARY KEY,
                    job VARCHAR(255) NOT NULL,
                    payload TEXT NOT NULL,
                    attempts INT NOT NULL DEFAULT 0,
                    exception TEXT NULL,
                    failed_at DOUBLE NOT NULL,
                    created_at DOUBLE NULL
                );
            ");

            $drivers['database'] = [new DatabaseQueue($conn, [
                'table' => 'jobs',
                'failed_table' => 'failed_jobs',
            ])];
        } catch (\Throwable $e) {
        }

        // Redis driver (if available)
        if (extension_loaded('redis')) {
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $redis->select(15);
                
                $keys = $redis->keys('workflow_test*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }

                $drivers['redis'] = [new RedisQueue($redis, [
                    'queue_prefix' => 'workflow_test:',
                    'default_queue' => 'default',
                    'failed_queue' => 'failed',
                ])];
            } catch (\RedisException $e) {
                // Redis not available, skip
            }
        }

        return $drivers;
    }

    /**
     * @dataProvider driverProvider
     */
    #[DataProvider('driverProvider')]
    public function testWorkflowSuccessAndFailure(DatabaseQueue|RedisQueue $queue): void
    {
        // Test successful job
        SuccessfulTestJob::$handled = false;
        
        $queue->push([
            'job' => SuccessfulTestJob::class,
            'payload' => []
        ]);

        $worker = new Worker($queue, sleep: 0, maxTries: 2, memory: 128, timeout: 5);

        $job = $queue->pop();
        $this->assertInstanceOf(JobInterface::class, $job);

        $worker->process($job);
        $this->assertTrue(SuccessfulTestJob::$handled);

        // Test failing job
        FailingTestJob::$attempted = false;
        
        $queue->push([
            'job' => FailingTestJob::class,
            'payload' => []
        ]);

        $failingJob = $queue->pop();
        $this->assertInstanceOf(JobInterface::class, $failingJob);

        try {
            $worker->process($failingJob);
        } catch (\Exception $e) {
            // Expected to fail
        }

        $this->assertTrue(FailingTestJob::$attempted);
    }
}