<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Worker;

/**
 * Mocking set_time_limit to prevent test execution fatal errors.
 */
function set_time_limit(int $seconds): bool
{
    return true;
}
