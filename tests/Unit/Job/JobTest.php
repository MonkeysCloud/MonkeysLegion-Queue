<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Job;

use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Job\Job;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    private QueueInterface&MockObject $mockQueue;

    protected function setUp(): void
    {
        $this->mockQueue = $this->createMock(QueueInterface::class);
    }

    public function testJobCanBeInstantiated(): void
    {
        $jobData = [
            'id' => 'test-job-123',
            'job' => 'TestJob',
            'payload' => ['param1' => 'value1'],
            'attempts' => 0,
            'created_at' => microtime(true),
        ];

        $job = new Job($jobData, $this->mockQueue);

        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals('test-job-123', $job->getId());
        $this->assertEquals(0, $job->attempts());
    }

    public function testJobGeneratesIdIfNotProvided(): void
    {
        $jobData = [
            'job' => 'TestJob',
            'payload' => [],
            'attempts' => 0,
        ];

        $job = new Job($jobData, $this->mockQueue);

        $this->assertNotEmpty($job->getId());
        $this->assertStringStartsWith('job_', $job->getId());
    }

    public function testJobDefaultsAttemptsToZero(): void
    {
        $jobData = [
            'id' => 'test-job-123',
            'job' => 'TestJob',
            'payload' => [],
        ];

        $job = new Job($jobData, $this->mockQueue);

        $this->assertEquals(0, $job->attempts());
    }

    public function testGetDataReturnsOriginalJobData(): void
    {
        $jobData = [
            'id' => 'test-job-123',
            'job' => 'TestJob',
            'payload' => ['key' => 'value'],
            'attempts' => 2,
            'created_at' => 1234567890.123,
        ];

        $job = new Job($jobData, $this->mockQueue);

        $this->assertEquals($jobData, $job->getData());
    }

    public function testFailCallsQueueFailMethod(): void
    {
        $jobData = [
            'id' => 'test-job-123',
            'job' => 'TestJob',
            'payload' => [],
            'attempts' => 3,
        ];

        $exception = new \Exception('Job failed');

        $this->mockQueue->expects($this->once())
            ->method('fail')
            ->with(
                $this->isInstanceOf(Job::class),
                $this->identicalTo($exception)
            );

        $job = new Job($jobData, $this->mockQueue);
        $job->fail($exception);
    }

    public function testHandleThrowsExceptionIfJobClassNotFound(): void
    {
        $jobData = [
            'id' => 'test-job-123',
            'job' => 'NonExistentJobClass',
            'payload' => [],
        ];

        $job = new Job($jobData, $this->mockQueue);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Job class 'NonExistentJobClass' not found");

        $job->handle();
    }

    public function testHandleExecutesJobClass(): void
    {
        // Create a simple test job class inline
        $testJobCode = <<<'PHP'
namespace MonkeysLegion\Queue\Tests\Fixtures {
    class TestExecutableJob
    {
        public static bool $wasExecuted = false;
        
        public function __construct(public string $message = '') {}
        
        public function handle(): void
        {
            self::$wasExecuted = true;
        }
    }
}
PHP;

        eval($testJobCode);

        $jobData = [
            'id' => 'test-job-123',
            'job' => 'MonkeysLegion\Queue\Tests\Fixtures\TestExecutableJob',
            'payload' => ['test message'],
        ];

        $job = new Job($jobData, $this->mockQueue);
        $job->handle();

        $this->assertTrue("\MonkeysLegion\Queue\Tests\Fixtures\TestExecutableJob"::$wasExecuted);
    }
}
