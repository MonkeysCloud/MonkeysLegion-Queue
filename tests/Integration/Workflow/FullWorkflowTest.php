<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Integration\Workflow;

use MonkeysLegion\Queue\Dispatcher\QueueDispatcher;
use MonkeysLegion\Queue\Tests\Fixtures\IntegrationTestJob;
use MonkeysLegion\Queue\Tests\Fixtures\MemoryQueue;
use MonkeysLegion\Queue\Worker\Worker;
use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;
use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects
require_once __DIR__ . '/../../Unit/Worker/WorkerFunctionsMock.php';
// phpcs:enable PSR1.Files.SideEffects

class FullWorkflowTest extends TestCase
{
    private MemoryQueue $queue;
    private Worker $worker;
    private QueueDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->queue = new MemoryQueue(['default_queue' => 'default']);

        // Setup worker with small timeout and immediate processing
        $this->worker = new Worker(
            $this->queue,
            sleep: 0,
            maxTries: 3
        );

        $this->dispatcher = new QueueDispatcher($this->queue);

        // Reset job state
        IntegrationTestJob::$handled = false;
        IntegrationTestJob::$receivedData = '';
    }

    public function testDispatchAndProcessJobSuccessfully(): void
    {
        // 1. Dispatch Job
        $job = new IntegrationTestJob('workflow-data');
        $this->dispatcher->dispatch($job);

        $this->assertEquals(1, $this->queue->count(), 'Job should be in queue');

        // 2. Process with Worker
        // Determine what job is in queue without popping it (Mock implementation just checks array)
        $this->assertNotEmpty($this->queue->jobs['default']);

        // Worker->work loops, here we just want to process one job
        // We can manually pop and process using worker->process
        $jobFromQueue = $this->queue->pop();
        $this->assertNotNull($jobFromQueue);

        $this->expectOutputRegex('/Processing/');
        $this->worker->process($jobFromQueue);

        // 3. Verify Result
        $this->assertTrue(IntegrationTestJob::$handled, 'Job handle method should have been called');
        $this->assertEquals('workflow-data', IntegrationTestJob::$receivedData);
    }

    public function testJobRetriesOnFailure(): void
    {
        // 1. setup failing job (using anonymous class for failure)
        $failingJob = new class implements DispatchableJobInterface {
            public function handle(): void
            {
                throw new \RuntimeException('Intentional failure');
            }
        };

        $this->queue->push([
            'job' => get_class($failingJob),
            'payload' => [],
            'id' => 'fail-job'
        ]);

        // 2. Process (Expect failure and retry)
        $jobFromQueue = $this->queue->pop();
        $this->expectOutputRegex('/Processing/');
        $this->worker->process($jobFromQueue);

        // 3. Verify
        // Should have been released (delayed)
        $this->assertNotEmpty($this->queue->delayed, 'Job should be in delayed queue for retry');
        // Check attempts incremented (MemoryQueue logic needs to support this in mock release)
        // MemoryQueue::release calls push or later.
    }
}
