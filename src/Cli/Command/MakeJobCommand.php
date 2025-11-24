<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Command\MakerHelpers;
use MonkeysLegion\Cli\Console\Traits\Cli;

/**
 * Generate a new Job class
 */
#[CommandAttr('make:job', 'Generate a new Job class')]
final class MakeJobCommand extends Command
{
    use MakerHelpers, Cli;

    public function handle(): int
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->cliLine()->error('Job name is required')->print();
            $this->cliLine()->info('Usage: make:job <JobName>')->print();
            return self::FAILURE;
        }

        $className = $this->sanitizeClassName($name);
        $namespace = 'App\\Jobs';
        $directory = base_path('app/Jobs');

        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . '/' . $className . '.php';

        if (file_exists($filePath)) {
            $this->cliLine()->error("Job {$className} already exists!")->print();
            return self::FAILURE;
        }

        $stub = $this->getStub();
        $content = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $className],
            $stub
        );

        file_put_contents($filePath, $content);

        $this->cliLine()->success("Job created successfully!")->print();
        $this->cliLine()->info("Location: {$filePath}")->print();

        return self::SUCCESS;
    }

    private function getStub(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{namespace}};

/**
 * Job class: {{class}}
 * 
 * This job will be processed by the queue worker.
 */
class {{class}}
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        // Add your job dependencies here
        // Example: public string $userId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Your job logic goes here
        // Example: Process user data, send emails, etc.
    }
}
PHP;
    }

    private function sanitizeClassName(string $name): string
    {
        // Remove .php extension if present
        $name = preg_replace('/\.php$/i', '', $name);

        // Convert to PascalCase
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }
}
