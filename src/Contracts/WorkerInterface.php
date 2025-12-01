<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Contracts;

interface WorkerInterface
{
    /**
     * Start reading and executing jobs from a queue.
     *
     * @param string|string[] $queue Queue name or array of queue names to listen to.
     * @param int $sleep Seconds to wait when queue is empty.
     */
    public function work(string|array $queue = 'default', int $sleep = 3): void;

    /**
     * Execute a single job instance.
     */
    public function process(JobInterface $job): void;

    /**
     * Stop worker gracefully.
     */
    public function stop(): void;
}
