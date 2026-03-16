<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Dispatcher;

use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Contracts\ShouldQueue;
use MonkeysLegion\Queue\Dispatcher\QueueDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QueueDispatcherTest extends TestCase
{
    private QueueInterface&MockObject $mockQueue;
    private QueueDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->mockQueue = $this->createMock(QueueInterface::class);
        $this->dispatcher = new QueueDispatcher($this->mockQueue);
    }

    public function testDispatchPushesJobToQueue(): void
    {
        $job = new class ('test-data') implements DispatchableJobInterface, ShouldQueue {
            public function __construct(public string $data)
            {
            }
            public function handle(): void
            {
            }
        };

        $this->mockQueue->expects($this->once())
            ->method('push')
            ->with(
                $this->callback(function ($jobData) use ($job) {
                    return $jobData['job'] === get_class($job)
                        && $jobData['payload']['data'] === 'test-data';
                }),
                'default'
            );

        $this->dispatcher->dispatch($job);
    }

    public function testDispatchWithDelayCallsLater(): void
    {
        $job = new class implements DispatchableJobInterface, ShouldQueue {
            public function handle(): void
            {
            }
        };

        $this->mockQueue->expects($this->once())
            ->method('later')
            ->with(
                60,
                $this->callback(function ($jobData) use ($job) {
                    return $jobData['job'] === get_class($job);
                }),
                'default'
            );

        $this->dispatcher->dispatch($job, 'default', 60);
    }

    public function testDispatchToCustomQueue(): void
    {
        $job = new class implements DispatchableJobInterface, ShouldQueue {
            public function handle(): void
            {
            }
        };

        $this->mockQueue->expects($this->once())
            ->method('push')
            ->with(
                $this->anything(),
                'custom-queue'
            );

        $this->dispatcher->dispatch($job, 'custom-queue');
    }

    public function testDispatchAtCalculatesDelay(): void
    {
        $job = new class implements DispatchableJobInterface, ShouldQueue {
            public function handle(): void
            {
            }
        };
        $futureTime = time() + 120;

        $this->mockQueue->expects($this->once())
            ->method('later')
            ->with(
                $this->callback(function ($delay) {
                    // Allow small delta for execution time
                    return abs($delay - 120) <= 1;
                }),
                $this->anything(),
                'default'
            );

        $this->dispatcher->dispatchAt($job, $futureTime);
    }

    public function testBuildPayloadExtractsConstructorArgs(): void
    {
        $job = new class ('arg1', 123) implements DispatchableJobInterface, ShouldQueue {
            public function __construct(
                public string $param1,
                public int $param2
            ) {
            }
            public function handle(): void
            {
            }
        };

        $this->mockQueue->expects($this->once())
            ->method('push')
            ->with(
                $this->callback(function ($jobData) {
                    return $jobData['payload']['param1'] === 'arg1'
                        && $jobData['payload']['param2'] === 123;
                }),
                'default'
            );

        $this->dispatcher->dispatch($job);
    }

    public function testDispatchExecutesImmediatelyWhenShouldQueueNotImplemented(): void
    {
        $job = new class implements DispatchableJobInterface {
            public bool $handled = false;
            public function handle(): void
            {
                $this->handled = true;
            }
        };

        $this->mockQueue->expects($this->never())
            ->method('push');

        $this->mockQueue->expects($this->never())
            ->method('later');

        $this->dispatcher->dispatch($job);

        $this->assertTrue($job->handled);
    }

    public function testDispatchAtExecutesImmediatelyWhenShouldQueueNotImplemented(): void
    {
        $job = new class implements DispatchableJobInterface {
            public bool $handled = false;
            public function handle(): void
            {
                $this->handled = true;
            }
        };

        $this->mockQueue->expects($this->never())
            ->method('later');

        $this->dispatcher->dispatchAt($job, time() + 60);

        $this->assertTrue($job->handled);
    }
}
