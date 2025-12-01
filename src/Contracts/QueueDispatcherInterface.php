<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Contracts;

interface QueueDispatcherInterface
{
    public function dispatch(
        DispatchableJobInterface $job,
        string $queue = 'default',
        ?int $delay = null
    ): void;

    public function dispatchAt(
        DispatchableJobInterface $job,
        int $timestamp,
        string $queue = 'default'
    ): void;
}
