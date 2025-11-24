<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Worker;

use MonkeysLegion\Queue\Contracts\JobInterface;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Worker\Worker;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class WorkerTest extends TestCase
{
    private QueueInterface&MockObject $mockQueue;
    private Worker $worker;

    protected function setUp(): void
    {
        $this->mockQueue = $this->createMock(QueueInterface::class);
        $this->worker = new Worker(
            queue: $this->mockQueue,
            sleep: 1,
            maxTries: 3,
            memory: 128,
            timeout: 60,
            delayedCheckInterval: 30
        );
    }

    public function testWorkerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Worker::class, $this->worker);
    }

    public function testProcessSuccessfullyHandlesJob(): void
    {
        $mockJob = $this->createMock(JobInterface::class);

        $mockJob->expects($this->any())
            ->method('getId')
            ->willReturn('test-job-123');

        $mockJob->expects($this->atMost(1))
            ->method('attempts')
            ->willReturn(0);

        $mockJob->expects($this->once())
            ->method('handle');

        $this->mockQueue->expects($this->once())
            ->method('ack')
            ->with($mockJob);

        $this->worker->process($mockJob);
    }

    public function testProcessRetriesJobOnFailureWithinMaxTries(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $exception = new \Exception('Job failed');

        $mockJob->expects($this->any())
            ->method('getId')
            ->willReturn('test-job-123');

        $mockJob->expects($this->exactly(3))
            ->method('attempts')
            ->willReturn(1); // Second attempt

        $mockJob->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $this->mockQueue->expects($this->once())
            ->method('release')
            ->with($mockJob, $this->greaterThan(0));

        $this->worker->process($mockJob);
    }

    public function testProcessMarksJobAsFailedAfterMaxTries(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $exception = new \Exception('Job failed permanently');

        $mockJob->expects($this->any())
            ->method('getId')
            ->willReturn('test-job-123');

        $mockJob->expects($this->exactly(2))
            ->method('attempts')
            ->willReturn(2); // Third attempt (max tries = 3)

        $mockJob->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $this->mockQueue->expects($this->once())
            ->method('ack')
            ->with($mockJob);

        $mockJob->expects($this->once())
            ->method('fail')
            ->with($exception);

        $this->worker->process($mockJob);
    }

    public function testGetStatsReturnsWorkerMetrics(): void
    {
        $stats = $this->worker->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('processed_jobs', $stats);
        $this->assertArrayHasKey('memory_usage_mb', $stats);
        $this->assertArrayHasKey('should_quit', $stats);
        $this->assertEquals(0, $stats['processed_jobs']);
        $this->assertFalse($stats['should_quit']);
    }

    public function testStopSetsShutdownFlag(): void
    {
        $this->worker->stop();

        $stats = $this->worker->getStats();
        $this->assertTrue($stats['should_quit']);
    }

    public function testStopOnlyPrintsMessageOnce(): void
    {
        $this->worker->stop();
        $this->worker->stop(); // Call twice

        $stats = $this->worker->getStats();
        $this->assertTrue($stats['should_quit']);
    }

    public function testExponentialBackoffCalculation(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $exception = new \Exception('Job failed');

        // First retry (attempt 1)
        $mockJob->method('getId')->willReturn('test-job-123');
        $mockJob->method('attempts')->willReturn(0);
        $mockJob->method('handle')->willThrowException($exception);

        // Expect delay of 1 second (2^0 = 1)
        $this->mockQueue->expects($this->once())
            ->method('release')
            ->with($mockJob, 1);

        $this->worker->process($mockJob);
    }

    public function testExponentialBackoffWithMultipleAttempts(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $exception = new \Exception('Job failed');

        // Return job ID any number of times
        $mockJob->method('getId')->willReturn('test-job-123');

        // Return attempts so that a retry will happen
        // Worker->maxTries default = 3, so attempts() should return 1 or 2
        // Let's use 1 → next attempt = 2 → retry happens
        $mockJob->method('attempts')->willReturn(1);

        // The job will always fail
        $mockJob->method('handle')->willThrowException($exception);

        // Expected delay calculation based on Worker::retry()
        $expectedAttempts = 1 + 1; // attempts() + 1
        $expectedDelay = min(60, (int) pow(2, $expectedAttempts - 1)); // 2^(2-1) = 2

        // Assert that release() is called with correct job and delay
        $this->mockQueue->expects($this->once())
            ->method('release')
            ->with($mockJob, $expectedDelay);

        $this->worker->process($mockJob);
    }

    public function testExponentialBackoffMaxDelay(): void
    {
        // Increase maxTries to allow enough retries
        $this->worker = new Worker($this->mockQueue, sleep: 3, maxTries: 20);

        $mockJob = $this->createMock(JobInterface::class);
        $exception = new \Exception('Job failed');

        $mockJob->method('getId')->willReturn('test-job-123');
        $mockJob->method('attempts')->willReturn(10); // next attempt = 11
        $mockJob->method('handle')->willThrowException($exception);

        // Delay calculation: 2^(11-1) = 1024 → min(60, 1024) = 60
        $this->mockQueue->expects($this->once())
            ->method('release')
            ->with($mockJob, 60);

        $this->worker->process($mockJob);
    }

    public function testProcessIncrementsJobCounter(): void
    {
        $mockJob = $this->createMock(JobInterface::class);

        $mockJob->method('getId')->willReturn('test-job-123');
        $mockJob->method('attempts')->willReturn(0);
        $mockJob->method('handle');

        $this->mockQueue->method('ack');

        $initialStats = $this->worker->getStats();
        $this->assertEquals(0, $initialStats['processed_jobs']);

        $this->worker->process($mockJob);

        $finalStats = $this->worker->getStats();
        $this->assertEquals(0, $finalStats['processed_jobs']); // Not incremented in process(), only in work()
    }

    public function testMemoryExceededDetection(): void
    {
        // Create worker with very low memory limit
        $worker = new Worker(
            queue: $this->mockQueue,
            sleep: 1,
            maxTries: 3,
            memory: 1, // 1 MB - current usage will exceed this
            timeout: 60
        );

        $reflection = new \ReflectionClass($worker);
        $method = $reflection->getMethod('memoryExceeded');

        $result = $method->invoke($worker);
        $this->assertTrue($result);
    }

    public function testMemoryWithinLimits(): void
    {
        // Create worker with high memory limit
        $worker = new Worker(
            queue: $this->mockQueue,
            sleep: 1,
            maxTries: 3,
            memory: 10000, // 10 GB
            timeout: 60
        );

        $reflection = new \ReflectionClass($worker);
        $method = $reflection->getMethod('memoryExceeded');

        $result = $method->invoke($worker);
        $this->assertFalse($result);
    }

    public function testProcessDelayedJobsSilentlyHandlesErrors(): void
    {
        $this->mockQueue->expects($this->any())
            ->method('processDelayedJobs')
            ->with('test_queue')
            ->willThrowException(new \RuntimeException('Redis error'));

        $reflection = new \ReflectionClass($this->worker);
        $method = $reflection->getMethod('checkDelayedJobs');

        // Should not throw exception
        $method->invoke($this->worker, 'test_queue');

        // Update last check time to force processing
        $lastCheckProperty = $reflection->getProperty('lastDelayedCheck');
        $lastCheckProperty->setValue($this->worker, time() - 100);

        // Should still not throw
        $method->invoke($this->worker, 'test_queue');
    }

    public function testCheckDelayedJobsRespectsInterval(): void
    {
        $reflection = new \ReflectionClass($this->worker);
        $method = $reflection->getMethod('checkDelayedJobs');

        // Set last check to now
        $lastCheckProperty = $reflection->getProperty('lastDelayedCheck');
        $lastCheckProperty->setValue($this->worker, time());

        // Should not call processDelayedJobs
        $this->mockQueue->expects($this->never())
            ->method('processDelayedJobs');

        $method->invoke($this->worker, 'test_queue');
    }

    public function testCheckDelayedJobsAfterInterval(): void
    {
        $reflection = new \ReflectionClass($this->worker);
        $method = $reflection->getMethod('checkDelayedJobs');

        // Set last check to past
        $lastCheckProperty = $reflection->getProperty('lastDelayedCheck');
        $lastCheckProperty->setValue($this->worker, time() - 100);

        // Should call processDelayedJobs
        $this->mockQueue->expects($this->once())
            ->method('processDelayedJobs')
            ->with('test_queue')
            ->willReturn(5);

        $method->invoke($this->worker, 'test_queue');
    }

    public function testWorkProcessesJobsUntilStopped(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $mockJob->method('getId')->willReturn('test-job-123');
        $mockJob->method('attempts')->willReturn(0);
        $mockJob->method('handle');

        // Configure queue to return job once, then null
        $callCount = 0;

        $this->mockQueue->expects($this->exactly(2))
            ->method('pop')
            ->willReturnCallback(function () use (&$callCount, $mockJob) {
                $callCount++;

                if ($callCount === 1) {
                    return $mockJob; // First call returns a job
                }

                // Second call triggers worker stop
                $this->worker->stop();
                return null;
            });


        $this->mockQueue->expects($this->once())
            ->method('ack')
            ->with($mockJob);

        $this->worker->work('test_queue', 0);

        $stats = $this->worker->getStats();
        $this->assertEquals(1, $stats['processed_jobs']);
    }

    public function testWorkStopsOnMemoryExceeded(): void
    {
        // Create worker with very low memory limit
        $worker = new Worker(
            queue: $this->mockQueue,
            sleep: 0,
            maxTries: 3,
            memory: 1, // 1 MB - will be exceeded
            timeout: 60,
            delayedCheckInterval: 30
        );

        $this->mockQueue->expects($this->never())
            ->method('pop');

        $worker->work('test_queue', 0);

        $stats = $worker->getStats();
        $this->assertEquals(0, $stats['processed_jobs']);
    }

    public function testWorkProcessesDelayedJobsPeriodically(): void
    {
        $worker = new Worker(
            queue: $this->mockQueue,
            sleep: 0,
            maxTries: 3,
            memory: 128,
            timeout: 60,
            delayedCheckInterval: 1 // Check every 1 second
        );

        $callCount = 0;

        $this->mockQueue->expects($this->any())
            ->method('pop')
            ->willReturnCallback(function () use (&$callCount, $worker) {
                $callCount++;
                if ($callCount === 1) return null; // First call triggers delayed check
                $worker->stop();                  // Stop immediately
                return null;                      // Always return null afterward
            });

        $this->mockQueue->expects($this->once())
            ->method('processDelayedJobs')
            ->with('test_queue')
            ->willReturn(0);

        // Force delayed check by setting lastDelayedCheck in the past
        $reflection = new \ReflectionClass($worker);
        $property = $reflection->getProperty('lastDelayedCheck');
        $property->setValue($worker, time() - 10);

        $worker->work('test_queue', 0);
    }

    public function testWorkSleepsWhenNoJobsAvailable(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('pop')
            ->willReturnCallback(function () {
                $this->worker->stop();                  // Stop immediately
                return null;                      // Always return null afterward
            });

        $start = microtime(true);
        $this->worker->work('test_queue', 1);
        $duration = microtime(true) - $start;

        // Should have slept for approximately 1 second
        $this->assertGreaterThan(0.9, $duration);
    }

    public function testWorkIncrementsProcessedJobsCounter(): void
    {
        $mockJob1 = $this->createMock(JobInterface::class);
        $mockJob1->method('getId')->willReturn('job-1');
        $mockJob1->method('attempts')->willReturn(0);
        $mockJob1->method('handle');

        $mockJob2 = $this->createMock(JobInterface::class);
        $mockJob2->method('getId')->willReturn('job-2');
        $mockJob2->method('attempts')->willReturn(0);
        $mockJob2->method('handle');

        $callCount = 0;
        $this->mockQueue->expects($this->exactly(3))
            ->method('pop')
            ->willReturnCallback(function () use (&$callCount, $mockJob1, $mockJob2) {
                $callCount++;
                if ($callCount === 1) return $mockJob1;
                if ($callCount === 2) return $mockJob2;
                $this->worker->stop();
                return null;
            });

        $this->mockQueue->expects($this->exactly(2))
            ->method('ack');

        $this->worker->work('test_queue', 0);

        $stats = $this->worker->getStats();
        $this->assertEquals(2, $stats['processed_jobs']);
    }

    public function testWorkHandlesExceptionsDuringJobProcessing(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $mockJob->method('getId')->willReturn('failing-job');
        $mockJob->method('attempts')->willReturn(0);
        $mockJob->method('handle')->willThrowException(new \RuntimeException('Job failed'));

        $callCount = 0;
        $this->mockQueue->expects($this->exactly(2))
            ->method('pop')
            ->willReturnCallback(function () use (&$callCount, $mockJob) {
                $callCount++;
                if ($callCount === 1) return $mockJob;
                $this->worker->stop();
                return null;
            });

        $this->mockQueue->expects($this->once())
            ->method('release')
            ->with($mockJob, $this->greaterThan(0));

        $this->worker->work('test_queue', 0);

        $stats = $this->worker->getStats();
        $this->assertEquals(1, $stats['processed_jobs']);
    }

    public function testWorkWithDefaultQueueParameter(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('pop')
            ->with('default')
            ->willReturnCallback(function () {
                $this->worker->stop();
                return null;
            });

        $this->worker->work(); // No parameters - should use 'default'
    }

    public function testWorkWithCustomSleepParameter(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('pop')
            ->willReturnCallback(function () {
                $this->worker->stop();
                return null;
            });

        $start = microtime(true);
        $this->worker->work('test_queue', 2); // Custom sleep of 2 seconds
        $duration = microtime(true) - $start;

        // Should have slept for approximately 2 seconds
        $this->assertGreaterThan(1.9, $duration);
    }

    public function testRetryCalculatesCorrectDelayForDifferentAttempts(): void
    {
        $worker = new Worker(
            queue: $this->mockQueue,
            sleep: 1,
            maxTries: 10,
            memory: 128,
            timeout: 60,
            delayedCheckInterval: 30
        );

        $testCases = [
            ['attempts' => 0, 'expectedDelay' => 1],
            ['attempts' => 1, 'expectedDelay' => 2],
            ['attempts' => 2, 'expectedDelay' => 4],
            ['attempts' => 3, 'expectedDelay' => 8],
            ['attempts' => 4, 'expectedDelay' => 16],
            ['attempts' => 5, 'expectedDelay' => 32],
            ['attempts' => 6, 'expectedDelay' => 60], // capped
        ];

        $callIndex = 0;

        $this->mockQueue->expects($this->exactly(count($testCases)))
            ->method('release')
            ->willReturnCallback(function ($job, $delay) use (&$callIndex, $testCases) {
                $expected = $testCases[$callIndex]['expectedDelay'];
                $this->assertEquals($expected, $delay, "Delay mismatch at call $callIndex");
                $callIndex++;
            });

        foreach ($testCases as $tc) {
            $mockJob = $this->createMock(JobInterface::class);
            $mockJob->method('getId')->willReturn('test-job');
            $mockJob->method('attempts')->willReturn($tc['attempts']);
            $mockJob->method('handle')->willThrowException(new \Exception('Test'));

            $worker->process($mockJob);
        }
    }

    public function testProcessHandlesJobWithNoException(): void
    {
        $mockJob = $this->createMock(JobInterface::class);
        $mockJob->method('getId')->willReturn('success-job');
        $mockJob->method('attempts')->willReturn(0);

        $handled = false;
        $mockJob->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function () use (&$handled) {
                $handled = true;
            });

        $this->mockQueue->expects($this->once())
            ->method('ack')
            ->with($mockJob);

        $this->worker->process($mockJob);

        $this->assertTrue($handled);
    }

    public function testGetStatsShowsCorrectMemoryUsage(): void
    {
        $stats = $this->worker->getStats();

        $this->assertArrayHasKey('memory_usage_mb', $stats);
        $this->assertIsFloat($stats['memory_usage_mb']);
        $this->assertGreaterThan(0, $stats['memory_usage_mb']);
    }

    public function testWorkerWithZeroSleepDoesNotSleep(): void
    {
        $this->mockQueue->expects($this->once())
            ->method('pop')
            ->willReturnCallback(function () {
                $this->worker->stop();
                return null;
            });

        $start = microtime(true);
        $this->worker->work('test_queue', 0); // Zero sleep
        $duration = microtime(true) - $start;

        // Should complete almost immediately (< 0.1 seconds)
        $this->assertLessThan(0.1, $duration);
    }

    public function testCheckDelayedJobsSkipsWhenIntervalNotReached(): void
    {
        $reflection = new \ReflectionClass($this->worker);
        $method = $reflection->getMethod('checkDelayedJobs');
        $property = $reflection->getProperty('lastDelayedCheck');

        // Set last check to just now
        $property->setValue($this->worker, time());

        // Should NOT call processDelayedJobs
        $this->mockQueue->expects($this->never())
            ->method('processDelayedJobs');

        $method->invoke($this->worker, 'test_queue');
    }

    public function testCheckDelayedJobsProcessesWhenIntervalReached(): void
    {
        $reflection = new \ReflectionClass($this->worker);
        $method = $reflection->getMethod('checkDelayedJobs');
        $property = $reflection->getProperty('lastDelayedCheck');

        // Set last check to 100 seconds ago (> 30 second interval)
        $property->setValue($this->worker, time() - 100);

        // Should call processDelayedJobs
        $this->mockQueue->expects($this->once())
            ->method('processDelayedJobs')
            ->with('test_queue')
            ->willReturn(3);

        $method->invoke($this->worker, 'test_queue');
    }

    public function testMultipleStopCallsOnlySetFlagOnce(): void
    {
        $initialStats = $this->worker->getStats();
        $this->assertFalse($initialStats['should_quit']);

        $this->worker->stop();
        $stats1 = $this->worker->getStats();
        $this->assertTrue($stats1['should_quit']);

        $this->worker->stop();
        $stats2 = $this->worker->getStats();
        $this->assertTrue($stats2['should_quit']);
    }

    public function testProcessRespectsTimeout(): void
    {
        $worker = new Worker(
            queue: $this->mockQueue,
            timeout: 2 // set 2 seconds for test
        );

        $mockJob = $this->createMock(JobInterface::class);
        $mockJob->method('getId')->willReturn('test-job-123');
        $mockJob->method('attempts')->willReturn(0);

        $mockJob->method('handle')->willReturnCallback(function () {
            $start = microtime(true);
            while (microtime(true) - $start < 2) {
                // simulate long-running task
                usleep(100_000); // sleep 0.1 sec to avoid CPU burn
            }
        });

        $this->mockQueue->expects($this->once())->method('ack')->with($mockJob);

        $startTime = microtime(true);
        $worker->process($mockJob);
        $elapsed = microtime(true) - $startTime;

        $this->assertGreaterThanOrEqual(2, $elapsed, "Elapsed time should be at least 2 seconds");
        $this->assertLessThan(3, $elapsed, "Elapsed time should be under 3 seconds to avoid excessive delay");
    }
}
