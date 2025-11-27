<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Helpers;

use MonkeysLegion\Cli\Console\Traits\Cli;

class CliPrinter
{
    use Cli;

    /**
     * Print a CLI-friendly colored message
     * 
     * @param array<string, mixed> $context Additional context for the message
     */
    public static function printCliMessage(string $message, array $context = [], string $level = 'info'): void
    {
        $instance = new self();
        $line = $instance->cliLine();

        // Add timestamp prefix
        $line->muted('[' . date('H:i:s') . ']')->space();

        // Add level indicator with color
        match ($level) {
            'error' => $line->error('✗')->space()->add($message, 'red'),
            'warning' => $line->warning('⚠')->space()->add($message, 'yellow'),
            'notice' => $line->success('✓')->space()->add($message, 'green'),
            'processing' => $line->info('→')->space()->add($message, 'cyan'),
            default => $line->info('•')->space()->add($message, 'white'),
        };

        // Add important context details inline
        if (!empty($context)) {
            $importantKeys = ['job_id', 'attempts', 'max_tries', 'duration_ms', 'memory_usage_mb', 'error_message', 'count', 'queue', 'delay', 'class'];
            $details = [];

            foreach ($importantKeys as $key) {
                if (isset($context[$key])) {
                    $value = is_scalar($context[$key]) ? (string)$context[$key] : json_encode($context[$key]);
                    $details[] = "$key=$value";
                }
            }

            if (!empty($details)) {
                $line->space()->muted('(' . implode(', ', $details) . ')');
            }
        }

        $line->print();
    }
}
