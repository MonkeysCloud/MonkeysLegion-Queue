<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Fixtures;

class SuccessfulTestJob
{
    public static bool $handled = false;

    public function handle(): void
    {
        SuccessfulTestJob::$handled = true;
    }
}
