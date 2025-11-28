<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;
use MonkeysLegion\Queue\Contracts\QueueInterface;

/**
 * Retry failed jobs
 */
#[CommandAttr('queue:retry', 'Retry failed jobs')]
final class QueueRetryCommand extends Command
{
    use Cli;

    public function __construct(private QueueInterface $queueDriver)
    {
        return parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int)($this->option('limit') ?? 100);

        try {
            $beforeCount = $this->queueDriver->countFailed();

            if ($beforeCount === 0) {
                $this->cliLine()->info('No failed jobs to retry')->print();
                return self::SUCCESS;
            }

            $this->queueDriver->retryFailed($limit);

            $afterCount = $this->queueDriver->countFailed();
            $retriedCount = $beforeCount - $afterCount;

            $this->cliLine()->success("Retried {$retriedCount} failed jobs to their original queues")->print();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->cliLine()->error('Error: ' . $e->getMessage())->print();
            return self::FAILURE;
        }
    }
}
