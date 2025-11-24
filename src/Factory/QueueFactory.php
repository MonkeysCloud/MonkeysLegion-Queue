<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Factory;

use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Driver\RedisQueue;
use MonkeysLegion\Queue\Driver\NullQueue;
use Redis;

/**
 * Queue Factory
 * 
 * Creates queue driver instances based on configuration.
 */
class QueueFactory
{
    private array $settings;
    private array $stores;
    private string $defaultDriver;

    public function __construct(array $config)
    {
        $this->defaultDriver = $config['default'] ?? 'redis';
        $this->settings = $config['settings'] ?? [];
        $this->stores = $config['stores'] ?? [];
    }

    /**
     * Create a queue instance using the default driver
     */
    public function make(): QueueInterface
    {
        return $this->driver($this->defaultDriver);
    }

    /**
     * Create a queue instance for a specific driver
     * 
     * @param string $driver Driver name (redis, database, null)
     * @throws \InvalidArgumentException If driver is not supported
     */
    public function driver(string $driver): QueueInterface
    {
        return match ($driver) {
            'redis' => $this->createRedisQueue(),
            'null' => $this->createNullQueue(),
            'database' => throw new \RuntimeException('Database queue driver not yet implemented'),
            default => throw new \InvalidArgumentException("Queue driver '{$driver}' is not supported"),
        };
    }

    /**
     * Create a Redis queue instance
     */
    private function createRedisQueue(): RedisQueue
    {
        $storeConfig = $this->stores['redis'] ?? [];

        $redis = new Redis();

        try {
            // Attempt connection
            $connected = $redis->connect(
                $storeConfig['host'] ?? '127.0.0.1',
                (int)($storeConfig['port'] ?? 6379),
                (float)($storeConfig['timeout'] ?? 2.0)
            );

            if (!$connected) {
                throw new \RuntimeException('Failed to connect to Redis server');
            }

            // Authentication (username/password or just password)
            if (!empty($storeConfig['password'])) {
                try {
                    if (!empty($storeConfig['username'])) {
                        $redis->auth([$storeConfig['username'], $storeConfig['password']]);
                    } else {
                        $redis->auth($storeConfig['password']);
                    }
                } catch (\RedisException $e) {
                    throw new \RuntimeException('Redis authentication failed: ' . $e->getMessage(), 0, $e);
                }
            }

            // Select database
            if (isset($storeConfig['database'])) {
                try {
                    $redis->select((int)$storeConfig['database']);
                } catch (\RedisException $e) {
                    throw new \RuntimeException('Redis database selection failed: ' . $e->getMessage(), 0, $e);
                }
            }
        } catch (\RedisException $e) {
            // Connection-level redis exceptions (timed-out, refused, ACL)
            throw new \RuntimeException('Failed to connect to Redis server: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            // Anything unexpected
            throw new \RuntimeException('Unexpected Redis error: ' . $e->getMessage(), 0, $e);
        }

        return new RedisQueue($redis, $this->settings);
    }

    /**
     * Create a Null queue instance (no-op driver)
     */
    private function createNullQueue(): NullQueue
    {
        return new NullQueue($this->settings);
    }

    /**
     * Get the default driver name
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Get the queue settings
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Get available drivers
     * 
     * @return array<string>
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->stores);
    }
}
