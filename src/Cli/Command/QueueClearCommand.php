<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;
use MonkeysLegion\Queue\Contracts\QueueInterface;

/**
 * Clear jobs from a specific queue
 */
#[CommandAttr('queue:clear', 'Clear all jobs from a specific queue')]
final class QueueClearCommand extends Command
{
    use Cli;

    public function __construct(private QueueInterface $queueDriver)
    {
        return parent::__construct();
    }

    public function handle(): int
    {
        $queue = $this->argument(0) ?? 'default';

        try {
            $count = $this->queueDriver->count($queue);

            if ($count === 0) {
                $this->cliLine()->info("Queue '{$queue}' is already empty")->print();
                return self::SUCCESS;
            }

            $this->queueDriver->clear($queue);

            $this->cliLine()->success("Cleared {$count} jobs from queue '{$queue}'")->print();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->cliLine()->error('Error: ' . $e->getMessage())->print();
            return self::FAILURE;
        }
    }
}
