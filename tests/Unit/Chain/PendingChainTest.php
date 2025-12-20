<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Chain;

use MonkeysLegion\Queue\Chain\PendingChain;
use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use PHPUnit\Framework\TestCase;

class PendingChainTest extends TestCase
{
    private QueueInterface $queue;

    protected function setUp(): void
    {
        $this->queue = $this->createMock(QueueInterface::class);
    }

    public function testDispatchPushesFirstJobWithChainMetadata(): void
    {
        $job1 = new class implements DispatchableJobInterface {
            public function __construct(public string $name = 'job1')
            {
            }
            public function handle(): void
            {
            }
        };

        $job2 = new class implements DispatchableJobInterface {
            public function __construct(public string $name = 'job2')
            {
            }
            public function handle(): void
            {
            }
        };

        $capturedJobData = null;
        $capturedQueue = null;

        $this->queue
            ->expects($this->once())
            ->method('push')
            ->willReturnCallback(function ($jobData, $queue) use (&$capturedJobData, &$capturedQueue) {
                $capturedJobData = $jobData;
                $capturedQueue = $queue;
            });

        $chain = new PendingChain($this->queue);
        $chain->add([$job1, $job2])->dispatch();

        $this->assertNotNull($capturedJobData);
        $this->assertArrayHasKey('chain', $capturedJobData);
        $this->assertCount(1, $capturedJobData['chain']);
        $this->assertEquals('default', $capturedQueue);
    }

    public function testOnQueueSetsQueueForAllJobs(): void
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

        $chain = new PendingChain($this->queue);
        $chain->add([$job])->onQueue('high-priority')->dispatch();

        $this->assertEquals('high-priority', $capturedQueue);
    }

    public function testEmptyChainDoesNotPush(): void
    {
        $this->queue
            ->expects($this->never())
            ->method('push');

        $chain = new PendingChain($this->queue);
        $chain->dispatch();
    }

    public function testChainQueueIsStoredInJobData(): void
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

        $capturedJobData = null;

        $this->queue
            ->expects($this->once())
            ->method('push')
            ->willReturnCallback(function ($jobData, $queue) use (&$capturedJobData) {
                $capturedJobData = $jobData;
            });

        $chain = new PendingChain($this->queue);
        $chain->add([$job1, $job2])->onQueue('custom-queue')->dispatch();

        $this->assertArrayHasKey('chain_queue', $capturedJobData);
        $this->assertEquals('custom-queue', $capturedJobData['chain_queue']);
    }
}
