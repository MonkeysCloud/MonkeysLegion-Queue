<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Fixtures;

class FailingTestJob
{
    public static bool $attempted = false;

    public function handle(): void
    {
        FailingTestJob::$attempted = true;
        throw new \RuntimeException('Job failed intentionally');
    }
}
