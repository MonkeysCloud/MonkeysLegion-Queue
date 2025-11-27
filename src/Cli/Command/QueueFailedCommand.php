<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;
use MonkeysLegion\Queue\Contracts\QueueInterface;

/**
 * List failed jobs
 */
#[CommandAttr('queue:failed', 'List all failed jobs')]
final class QueueFailedCommand extends Command
{
    use Cli;

    public function __construct(private QueueInterface $queueDriver)
    {
        return parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int)($this->option('limit') ?? 10);

        try {
            $failedJobs = $this->queueDriver->getFailed('failed', $limit);
            $totalFailed = $this->queueDriver->countFailed();

            $this->cliLine()->success("Failed Jobs (showing {$limit} of {$totalFailed}):")->print();
            $this->cliLine()->print();

            if (empty($failedJobs)) {
                $this->cliLine()->info('No failed jobs found')->print();
                return self::SUCCESS;
            }

            foreach ($failedJobs as $job) {
                $this->cliLine()->error("ID: {$job['id']}")->print();
                $this->cliLine()->plain("  Job: {$job['job']}")->print();
                $this->cliLine()->plain("  Attempts: {$job['attempts']}")->print();

                if (isset($job['exception']['message'])) {
                    $this->cliLine()->plain("  Error: {$job['exception']['message']}")->print();
                }

                if (isset($job['failed_at'])) {
                    $failedAt = date('Y-m-d H:i:s', (int)$job['failed_at']);
                    $this->cliLine()->plain("  Failed At: {$failedAt}")->print();
                }

                $this->cliLine()->print();
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->cliLine()->error('Error: ' . $e->getMessage())->print();
            return self::FAILURE;
        }
    }
}
