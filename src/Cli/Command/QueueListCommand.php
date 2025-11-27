<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;
use MonkeysLegion\Queue\Contracts\QueueInterface;

/**
 * List all available queues
 */
#[CommandAttr('queue:list', 'List all available queues and their statistics')]
final class QueueListCommand extends Command
{
    use Cli;

    public function __construct(private QueueInterface $queueDriver)
    {
        return parent::__construct();
    }

    public function handle(): int
    {
        try {
            $queues = $this->queueDriver->getQueues();

            $this->cliLine()->success('Available Queues:')->print();
            $this->cliLine()->print();

            foreach ($queues as $queueName) {
                $stats = $this->queueDriver->getStats($queueName);

                $this->cliLine()
                    ->info("Queue: {$queueName}")
                    ->print();

                $this->cliLine()
                    ->plain("  Ready: {$stats['ready']}")
                    ->print();

                $this->cliLine()
                    ->plain("  Processing: {$stats['processing']}")
                    ->print();

                $this->cliLine()
                    ->plain("  Delayed: {$stats['delayed']}")
                    ->print();

                $this->cliLine()->print();
            }

            $failedCount = $this->queueDriver->countFailed();
            $this->cliLine()
                ->error("Failed Jobs: {$failedCount}")
                ->print();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->cliLine()->error('Error: ' . $e->getMessage())->print();
            return self::FAILURE;
        }
    }
}
