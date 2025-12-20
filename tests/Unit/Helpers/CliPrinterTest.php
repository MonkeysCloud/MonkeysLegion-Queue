<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Helpers;

use MonkeysLegion\Queue\Helpers\CliPrinter;
use PHPUnit\Framework\TestCase;

class CliPrinterTest extends TestCase
{
    private $outputBuffer;



    public function testPrintCliMessageRunsWithoutError(): void
    {
        $this->expectOutputRegex('/Test Message/');
        CliPrinter::printCliMessage('Test Message', [], 'info');
    }

    public function testPrintCliMessageWithContextRunsWithoutError(): void
    {
        $this->expectOutputRegex('/Job Processed/');
        CliPrinter::printCliMessage('Job Processed', [
            'job_id' => '12345',
            'queue' => 'default'
        ], 'notice');
    }

    public function testPrintCliMessageWithDifferentLevelsRunsWithoutError(): void
    {
        $this->expectOutputRegex('/Error.*Warning.*Processing/s');
        CliPrinter::printCliMessage('Error', [], 'error');
        CliPrinter::printCliMessage('Warning', [], 'warning');
        CliPrinter::printCliMessage('Processing', [], 'processing');
    }
}
