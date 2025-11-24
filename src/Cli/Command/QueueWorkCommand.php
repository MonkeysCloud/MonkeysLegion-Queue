<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Worker\Worker;

/**
 * Start processing jobs from the queue
 */
#[CommandAttr('queue:work', 'Start processing jobs from the queue')]
final class QueueWorkCommand extends Command
{
    use Cli;

    public function __construct(private QueueInterface $queueDriver)
    {
        return parent::__construct();
    }

    public function handle(): int
    {
        $queue = $this->option('queue') ?? 'default';
        $sleep = (int)($this->option('sleep') ?? 3);
        $maxTries = (int)($this->option('tries') ?? 3);
        $memory = (int)($this->option('memory') ?? 128);
        $timeout = (int)($this->option('timeout') ?? 60);

        try {
            $worker = new Worker(
                queue: $this->queueDriver,
                sleep: $sleep,
                maxTries: $maxTries,
                memory: $memory,
                timeout: $timeout
            );

            $worker->work($queue, $sleep);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->cliLine()->error('Failed to start worker: ' . $e->getMessage())->print();
            return self::FAILURE;
        }
    }
}
