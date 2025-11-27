<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;
use MonkeysLegion\Queue\Contracts\QueueInterface;

/**
 * Flush all failed jobs
 */
#[CommandAttr('queue:flush', 'Remove all failed jobs permanently')]
final class QueueFlushCommand extends Command
{
    use Cli;

    public function __construct(private QueueInterface $queueDriver)
    {
        return parent::__construct();
    }

    public function handle(): int
    {
        try {
            $count = $this->queueDriver->countFailed();

            if ($count === 0) {
                $this->cliLine()->info('No failed jobs to flush')->print();
                return self::SUCCESS;
            }

            $this->cliLine()->warning("This will permanently delete {$count} failed jobs!")->print();
            $this->cliLine()->plain('Are you sure? (yes/no): ')->print();

            $handle = fopen('php://stdin', 'r');
            $confirmation = trim(fgets($handle));
            fclose($handle);

            if (strtolower($confirmation) !== 'yes') {
                $this->cliLine()->info('Operation cancelled')->print();
                return self::SUCCESS;
            }

            $this->queueDriver->clearFailed();

            $this->cliLine()->success("Flushed {$count} failed jobs")->print();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->cliLine()->error('Error: ' . $e->getMessage())->print();
            return self::FAILURE;
        }
    }
}
