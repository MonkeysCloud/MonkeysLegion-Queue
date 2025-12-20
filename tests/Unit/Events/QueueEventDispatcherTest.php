<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Events;

use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Queue\Events\JobFailed;
use MonkeysLegion\Queue\Events\JobProcessed;
use MonkeysLegion\Queue\Events\JobProcessing;
use MonkeysLegion\Queue\Events\QueueEventDispatcher;
use PHPUnit\Framework\TestCase;

class QueueEventDispatcherTest extends TestCase
{
    private QueueEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new QueueEventDispatcher();
    }

    public function testListenAndDispatchEvent(): void
    {
        $called = false;
        $receivedEvent = null;

        $this->dispatcher->listen(JobProcessing::class, function ($event) use (&$called, &$receivedEvent) {
            $called = true;
            $receivedEvent = $event;
        });

        $job = $this->createMock(JobInterface::class);
        $event = new JobProcessing($job, 'default');

        $this->dispatcher->dispatch($event);

        $this->assertTrue($called);
        $this->assertSame($event, $receivedEvent);
    }

    public function testMultipleListenersForSameEvent(): void
    {
        $callCount = 0;

        $this->dispatcher->listen(JobProcessing::class, function () use (&$callCount) {
            $callCount++;
        });

        $this->dispatcher->listen(JobProcessing::class, function () use (&$callCount) {
            $callCount++;
        });

        $job = $this->createMock(JobInterface::class);
        $this->dispatcher->dispatch(new JobProcessing($job, 'default'));

        $this->assertEquals(2, $callCount);
    }

    public function testDispatchWithNoListenersDoesNotError(): void
    {
        $job = $this->createMock(JobInterface::class);
        $event = new JobProcessing($job, 'default');

        // Should not throw
        $this->dispatcher->dispatch($event);
        $this->assertTrue(true);
    }

    public function testHasListenersReturnsTrueWhenListenersExist(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners(JobProcessing::class));

        $this->dispatcher->listen(JobProcessing::class, fn() => null);

        $this->assertTrue($this->dispatcher->hasListeners(JobProcessing::class));
    }

    public function testForgetRemovesListeners(): void
    {
        $called = false;

        $this->dispatcher->listen(JobProcessing::class, function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->forget(JobProcessing::class);

        $job = $this->createMock(JobInterface::class);
        $this->dispatcher->dispatch(new JobProcessing($job, 'default'));

        $this->assertFalse($called);
        $this->assertFalse($this->dispatcher->hasListeners(JobProcessing::class));
    }

    public function testJobProcessingEventContainsCorrectData(): void
    {
        $job = $this->createMock(JobInterface::class);
        $queue = 'high-priority';

        $event = new JobProcessing($job, $queue);

        $this->assertSame($job, $event->job);
        $this->assertEquals($queue, $event->queue);
    }

    public function testJobProcessedEventContainsCorrectData(): void
    {
        $job = $this->createMock(JobInterface::class);
        $queue = 'default';
        $processingTime = 123.45;

        $event = new JobProcessed($job, $queue, $processingTime);

        $this->assertSame($job, $event->job);
        $this->assertEquals($queue, $event->queue);
        $this->assertEquals($processingTime, $event->processingTimeMs);
    }

    public function testJobFailedEventContainsCorrectData(): void
    {
        $job = $this->createMock(JobInterface::class);
        $queue = 'default';
        $exception = new \RuntimeException('Test error');
        $willRetry = true;

        $event = new JobFailed($job, $queue, $exception, $willRetry);

        $this->assertSame($job, $event->job);
        $this->assertEquals($queue, $event->queue);
        $this->assertSame($exception, $event->exception);
        $this->assertTrue($event->willRetry);
    }
}
