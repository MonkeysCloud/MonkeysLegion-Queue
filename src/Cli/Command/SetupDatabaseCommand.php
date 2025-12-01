<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use PDO;
use MonkeysLegion\Cli\Command\MakerHelpers;

/**
 * Setup the queue database tables
 */
#[CommandAttr('queue:setup', 'Setup the queue database tables')]
final class SetupDatabaseCommand extends Command
{
    use Cli, MakerHelpers;

    public function __construct(private ConnectionInterface $connection)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->cliLine()->info('Setting up queue database tables...')->print();

        // 1. Jobs Table
        $jobsTable = $this->askForTable('Jobs table name', 'jobs');
        if ($jobsTable === null) {
            return self::FAILURE;
        }

        // 2. Failed Jobs Table
        $failedTable = $this->askForTable('Failed jobs table name', 'failed_jobs');
        if ($failedTable === null) {
            return self::FAILURE;
        }

        // 3. Execute Migrations
        if (!$this->runMigration('queue_jobs.sql', 'jobs', $jobsTable)) {
            return self::FAILURE;
        }

        if (!$this->runMigration('failed_jobs.sql', 'failed_jobs', $failedTable)) {
            return self::FAILURE;
        }

        $this->cliLine()->success('Queue tables setup successfully!')->print();

        $this->cliLine()->warning('Please ensure your .env file contains the following configuration:')->print();
        $this->cliLine()->plain("QUEUE_DATABASE_TABLE={$jobsTable}")->print();
        $this->cliLine()->plain("QUEUE_DATABASE_FAILED_TABLE={$failedTable}")->print();

        return self::SUCCESS;
    }

    private function askForTable(string $label, string $default): ?string
    {
        while (true) {
            $input = $this->ask("{$label} [{$default}]: ");
            if (empty($input)) $input = $default;
            $tableName = $input;

            if (!$this->validateTableName($tableName)) {
                $this->cliLine()->error('Invalid table name. Use alphanumeric characters and underscores only.')->print();
                continue;
            }

            if ($this->tableExists($tableName)) {
                $this->cliLine()->warning("Table '{$tableName}' already exists.")->print();
                $confirm = strtolower($this->ask('Do you want to use it? (yes/no) [yes]: '));

                if ($confirm !== 'yes' && $confirm !== 'y' && !empty($confirm)) {
                    continue;
                }
            }

            return $tableName;
        }
    }

    private function validateTableName(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_]+$/', $name);
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $pdo = $this->connection->pdo();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            $sql = match ($driver) {
                'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
                'pgsql' => "SELECT tablename FROM pg_catalog.pg_tables WHERE tablename = ?",
                default => "SHOW TABLES LIKE ?", // MySQL, MariaDB
            };

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tableName]);

            return (bool) $stmt->fetch();
        } catch (\Throwable $e) {
            // If we can't check, assume it doesn't exist or ignore error
            return false;
        }
    }

    private function runMigration(string $filename, string $placeholder, string $realTableName): bool
    {
        // Resolve path relative to this file: src/Cli/Command/SetupDatabaseCommand.php
        // We need to go up 3 levels to reach root: src/Cli/Command -> src/Cli -> src -> root
        $rootPath = dirname(__DIR__, 3);
        $path = $rootPath . "/migration/{$filename}";

        if (!file_exists($path)) {
            $this->cliLine()->error("Migration file not found: {$path}")->print();
            return false;
        }

        $sqlContent = file_get_contents($path);
        $sqlContent = str_replace($placeholder, $realTableName, $sqlContent);

        // Split into statements to handle them individually
        // This allows us to ignore "already exists" errors for indexes
        $statements = array_filter(array_map('trim', explode(';', $sqlContent)));

        foreach ($statements as $statement) {
            if (empty($statement)) {
                continue;
            }

            try {
                $this->connection->pdo()->exec($statement);
            } catch (\Throwable $e) {
                // Check for "already exists" errors
                // MySQL: 1050 (Table exists), 1061 (Index exists)
                // SQLite: "already exists" in message
                // Postgres: 42P07 (Duplicate table/index)
                $msg = $e->getMessage();
                $code = null;
                if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
                    $code = $e->errorInfo[1];
                }

                if (
                    str_contains(strtolower($msg), 'already exists') || // SQLite / Postgres generic
                    str_contains(strtolower($msg), 'duplicate key name') || // MySQL generic message match
                    $code === 1050 || // MySQL Table exists
                    $code === 1061    // MySQL Index exists
                ) {
                    // specific warning for indexes to be clear
                    if (str_starts_with(strtoupper($statement), 'CREATE INDEX')) {
                        $this->cliLine()->warning("Index already exists, skipping creation.")->print();
                    }
                    continue;
                }

                $this->cliLine()->error("Failed to execute statement: " . substr($statement, 0, 50) . "...")->print();
                $this->cliLine()->error("Error: " . $e->getMessage())->print();
                return false;
            }
        }

        $this->cliLine()->info("Processed migration for: {$realTableName}")->print();
        return true;
    }

    private function readInput(): string
    {
        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        fclose($handle);
        return trim((string) $line);
    }
}
