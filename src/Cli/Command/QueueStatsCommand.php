<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;
use MonkeysLegion\Queue\Contracts\QueueInterface;

/**
 * Display queue statistics
 */
#[CommandAttr('queue:stats', 'Display detailed queue statistics')]
final class QueueStatsCommand extends Command
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
            $stats = $this->queueDriver->getStats($queue);

            $this->cliLine()->success("Queue Statistics: {$queue}")->print();
            $this->cliLine()->print();

            $this->cliLine()->info('Ready Jobs:')->plain(" {$stats['ready']}")->print();
            $this->cliLine()->info('Processing:')->plain(" {$stats['processing']}")->print();
            $this->cliLine()->info('Delayed:')->plain(" {$stats['delayed']}")->print();
            $this->cliLine()->error('Failed:')->plain(" {$stats['failed']}")->print();

            $total = $stats['ready'] + $stats['processing'] + $stats['delayed'];
            $this->cliLine()->print();
            $this->cliLine()->success('Total Active:')->plain(" {$total}")->print();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->cliLine()->error('Error: ' . $e->getMessage())->print();
            return self::FAILURE;
        }
    }
}
