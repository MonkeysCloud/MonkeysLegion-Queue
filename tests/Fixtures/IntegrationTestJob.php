<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Fixtures;

use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;

class IntegrationTestJob implements DispatchableJobInterface
{
    public static bool $handled = false;
    public static string $receivedData = '';

    public function __construct(public string $data) {}

    public function handle(): void
    {
        self::$handled = true;
        self::$receivedData = $this->data;
    }
}
