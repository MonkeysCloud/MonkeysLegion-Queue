<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Factory;

use MonkeysLegion\Queue\Factory\QueueFactory;
use MonkeysLegion\Queue\Driver\RedisQueue;
use MonkeysLegion\Queue\Driver\NullQueue;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use PHPUnit\Framework\TestCase;

class QueueFactoryTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'default' => 'redis',
            'settings' => [
                'default_queue' => 'default',
                'failed_queue' => 'failed',
                'queue_prefix' => 'ml_queue',
                'retry_after' => 90,
                'visibility_timeout' => 300,
                'max_attempts' => 3,
            ],
            'stores' => [
                'redis' => [
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'password' => null,
                    'database' => 0,
                    'timeout' => 2.0,
                ],
                'null' => [],
            ],
        ];
    }

    public function testFactoryCanBeInstantiated(): void
    {
        $factory = new QueueFactory($this->config);
        $this->assertInstanceOf(QueueFactory::class, $factory);
    }

    public function testGetDefaultDriver(): void
    {
        $factory = new QueueFactory($this->config);
        $this->assertEquals('redis', $factory->getDefaultDriver());
    }

    public function testGetSettings(): void
    {
        $factory = new QueueFactory($this->config);
        $settings = $factory->getSettings();

        $this->assertIsArray($settings);
        $this->assertEquals('default', $settings['default_queue']);
        $this->assertEquals('failed', $settings['failed_queue']);
        $this->assertEquals('ml_queue', $settings['queue_prefix']);
    }

    public function testGetAvailableDrivers(): void
    {
        $factory = new QueueFactory($this->config);
        $drivers = $factory->getAvailableDrivers();

        $this->assertIsArray($drivers);
        $this->assertContains('redis', $drivers);
        $this->assertContains('null', $drivers);
    }

    public function testMakeCreatesDefaultDriver(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        try {
            $factory = new QueueFactory($this->config);
            $queue = $factory->make();

            $this->assertInstanceOf(QueueInterface::class, $queue);
            $this->assertInstanceOf(RedisQueue::class, $queue);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Redis server not available');
        }
    }

    public function testDriverCreatesNullQueue(): void
    {
        $factory = new QueueFactory($this->config);
        $queue = $factory->driver('null');

        $this->assertInstanceOf(QueueInterface::class, $queue);
        $this->assertInstanceOf(NullQueue::class, $queue);
    }

    public function testDriverCreatesRedisQueue(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        try {
            $factory = new QueueFactory($this->config);
            $queue = $factory->driver('redis');

            $this->assertInstanceOf(QueueInterface::class, $queue);
            $this->assertInstanceOf(RedisQueue::class, $queue);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Redis server not available');
        }
    }

    public function testDriverThrowsExceptionForUnsupportedDriver(): void
    {
        $factory = new QueueFactory($this->config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Queue driver 'unsupported' is not supported");

        $factory->driver('unsupported');
    }

    public function testDriverThrowsExceptionForDatabaseDriver(): void
    {
        $config = $this->config;
        $config['stores']['database'] = ['table' => 'jobs'];

        $factory = new QueueFactory($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection is required for Database queue driver');

        $factory->driver('database');
    }

    public function testFactoryWithCustomDefaultDriver(): void
    {
        $config = $this->config;
        $config['default'] = 'null';

        $factory = new QueueFactory($config);

        $this->assertEquals('null', $factory->getDefaultDriver());

        $queue = $factory->make();
        $this->assertInstanceOf(NullQueue::class, $queue);
    }

    public function testFactoryWithEmptyConfig(): void
    {
        $factory = new QueueFactory([]);

        $this->assertEquals('redis', $factory->getDefaultDriver());
        $this->assertEmpty($factory->getSettings());
        $this->assertEmpty($factory->getAvailableDrivers());
    }

    public function testRedisQueueWithCustomDatabase(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        $config = $this->config;
        $config['stores']['redis']['database'] = 5;

        try {
            $factory = new QueueFactory($config);
            $queue = $factory->driver('redis');

            $this->assertInstanceOf(RedisQueue::class, $queue);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Redis server not available');
        }
    }

    public function testRedisConnectionFailureThrowsException(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }

        $config = $this->config;
        $config['stores']['redis']['host'] = '192.0.2.1'; // Non-routable IP
        $config['stores']['redis']['timeout'] = 0.1;

        $factory = new QueueFactory($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to connect to Redis server');

        $factory->driver('redis');
    }

    public function testNullQueueUsesSettings(): void
    {
        $factory = new QueueFactory($this->config);
        $queue = $factory->driver('null');

        $this->assertInstanceOf(NullQueue::class, $queue);

        // Verify settings were passed
        $queues = $queue->getQueues();
        $this->assertContains('default', $queues);
        $this->assertContains('failed', $queues);
    }
}
