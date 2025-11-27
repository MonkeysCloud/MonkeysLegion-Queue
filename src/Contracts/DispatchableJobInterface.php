<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Contracts;

interface DispatchableJobInterface
{
    /**
     * Execute the job.
     */
    public function handle(): void;
}
