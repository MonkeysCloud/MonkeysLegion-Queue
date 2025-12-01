# MonkeysLegion Queue

A robust, feature-rich queue system for PHP applications with support for multiple drivers, job retries, delayed jobs, and comprehensive monitoring.

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## Features

✨ **Multiple Queue Drivers**

- Redis (Production-ready)
- Database (Production-ready)
- Null (Testing/Development)

🔄 **Automatic Retries**

- Exponential backoff strategy
- Configurable max attempts
- Failed job tracking

⏰ **Delayed Jobs & Dispatching**

- Schedule jobs for future execution
- Automatic delayed job processing
- Priority queue support (process queues in order)
- Clean dispatcher API for job dispatching

📊 **Monitoring & Management**

- Real-time queue statistics
- Failed job inspection
- Job search and management
- CLI commands for queue operations

🛡️ **Production Ready**

- Graceful shutdown handling
- Memory limit protection
- Signal handling (SIGTERM, SIGINT)
- Comprehensive error handling

## Installation

```bash
composer require monkeyscloud/monkeyslegion-queue
```

## Configuration

Create a configuration file (e.g., `config/queue.php`):

```php
<?php

return [
    // The default store to use (redis, database, null)
    'default' => $_ENV['QUEUE_DEFAULT'] ?? 'redis',

    // Core queue behavior
    'settings' => [
        'default_queue'      => $_ENV['QUEUE_DEFAULT_QUEUE'] ?? 'default',
        'failed_queue'       => $_ENV['QUEUE_FAILED_QUEUE'] ?? 'failed',
        'queue_prefix'       => $_ENV['QUEUE_PREFIX'] ?? 'ml_queue',
        'retry_after'        => $_ENV['QUEUE_RETRY_AFTER'] ?? 90,
        'visibility_timeout' => $_ENV['QUEUE_VISIBILITY_TIMEOUT'] ?? 300,
        'max_attempts'       => $_ENV['QUEUE_MAX_ATTEMPTS'] ?? 3,
    ],

    // Queue drivers
    'stores' => [
        'redis' => [
            'host'     => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port'     => $_ENV['REDIS_PORT'] ?? 6379,
            'username' => $_ENV['REDIS_USERNAME'] ?? null,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_DATABASE'] ?? 0,
            'timeout'  => $_ENV['REDIS_TIMEOUT'] ?? 2.0,
        ],

        'null' => [],

        'database' => [
            'table' => $_ENV['QUEUE_DATABASE_TABLE'] ?? 'jobs',
            'failed_table' => $_ENV['QUEUE_DATABASE_FAILED_TABLE'] ?? 'failed_jobs',
        ],
    ],
];
```

### Environment Variables

Add to your `.env` file:

```env
# Queue Configuration
QUEUE_DEFAULT=redis
QUEUE_DEFAULT_QUEUE=default
QUEUE_FAILED_QUEUE=failed
QUEUE_PREFIX=ml_queue
QUEUE_MAX_ATTEMPTS=3

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0
REDIS_TIMEOUT=2.0

# Database Configuration
QUEUE_DATABASE_TABLE=jobs
QUEUE_DATABASE_FAILED_TABLE=failed_jobs
```

## Usage

### Creating a Queue Instance

```php
use MonkeysLegion\Queue\Factory\QueueFactory;
use MonkeysLegion\Database\MySQL\Connection;

// Initialize connection in case of database driver
$conn = new Connection([
    'dsn' => 'mysql:host=localhost;dbname=myapp',
    'username' => 'root',
    'password' => 'secret'
]);

$config = require 'config/queue.php';
$factory = new QueueFactory($config, $conn); // pass connection for database driver only

// Get default queue driver
$queue = $factory->make();

// Or get specific driver
$redisQueue = $factory->driver('redis'); // if no connection passed nothing happens
$nullQueue = $factory->driver('null'); // always works
$databaseQueue = $factory->driver('database', $conn); // requires connection
```

### Creating Jobs

#### Generate Job Class

```bash
php console make:job SendEmailJob
```

This creates `app/Jobs/SendEmailJob.php`:

```php
<?php

namespace App\Jobs;

class SendEmailJob
{
    public function __construct(
        public string $email,
        public string $subject,
        public string $message
    ) {
    }

    public function handle(): void
    {
        // Your job logic here
        mail($this->email, $this->subject, $this->message);
    }
}
```

### Dispatching Jobs

#### Using QueueDispatcher (Recommended)

The `QueueDispatcher` provides a clean, object-oriented way to dispatch jobs:

```php
use MonkeysLegion\Queue\Dispatcher\QueueDispatcher;
use App\Jobs\SendEmailJob;

$dispatcher = new QueueDispatcher($queue);

// Dispatch immediately
$job = new SendEmailJob('user@example.com', 'Welcome!', 'Thanks for signing up');
$dispatcher->dispatch($job);

// Dispatch to specific queue
$dispatcher->dispatch($job, queue: 'emails');

// Dispatch with delay (in seconds)
$dispatcher->dispatch($job, queue: 'emails', delay: 60);

// Dispatch at specific timestamp
$dispatcher->dispatchAt($job, timestamp: time() + 3600, queue: 'emails');
```

#### Push to Queue (Direct)

```php
// Simple job
$queue->push([
    'job' => 'App\\Jobs\\SendEmailJob',
    'payload' => ['user@example.com', 'Welcome!', 'Thanks for signing up'],
]);

// To specific queue
$queue->push([
    'job' => 'App\\Jobs\\ProcessImageJob',
    'payload' => ['/path/to/image.jpg'],
], 'images');
```

#### Delayed Jobs

```php
// Delay by 60 seconds
$queue->later(60, [
    'job' => 'App\\Jobs\\SendReminderJob',
    'payload' => ['user_id' => 123],
]);

// Delay by 1 hour
$queue->later(3600, [
    'job' => 'App\\Jobs\\GenerateReportJob',
    'payload' => ['report_id' => 456],
]);
```

#### Bulk Jobs

```php
$jobs = [
    ['job' => 'App\\Jobs\\SendEmailJob', 'payload' => ['email1@example.com', 'Subject', 'Message']],
    ['job' => 'App\\Jobs\\SendEmailJob', 'payload' => ['email2@example.com', 'Subject', 'Message']],
    ['job' => 'App\\Jobs\\SendEmailJob', 'payload' => ['email3@example.com', 'Subject', 'Message']],
];

$queue->bulk($jobs, 'emails');
```

### Running Workers

#### Start Worker

```bash
# Basic worker
php console queue:work

# Process specific queue
php console queue:work --queue=emails

# Priority queues (processes in order: high, default, low)
php console queue:work --queue=high,default,low

# With options
php console queue:work \
    --queue=emails \
    --sleep=3 \
    --tries=5 \
    --memory=256 \
    --timeout=120
```

**Worker Options:**

- `--queue` - Queue name(s) to process. Use comma-separated list for priority queues (default: `default`)
- `--sleep` - Seconds to wait when queue is empty (default: `3`)
- `--tries` - Max retry attempts (default: `3`)
- `--memory` - Memory limit in MB (default: `128`)
- `--timeout` - Job timeout in seconds (default: `60`)

**Priority Queues:**
When multiple queues are specified, the worker processes them in order. Jobs from the first queue are always processed before jobs from subsequent queues, allowing you to implement priority-based job processing.

#### Worker Output

```
[09:45:12] • Worker started (queue=default)
[09:45:13] → Processing (job_id=1a2b3c4d, attempts=1)
[09:45:14] ✓ Completed (job_id=1a2b3c4d, duration_ms=1250.45)
[09:45:15] → Processing (job_id=5e6f7g8h, attempts=1)
[09:45:16] ⚠ Retrying (job_id=5e6f7g8h, attempts=1, delay=1)
[09:45:18] → Processing (job_id=5e6f7g8h, attempts=2)
[09:45:19] ✗ Failed (job_id=5e6f7g8h, attempts=3)
```

#### Graceful Shutdown

Workers handle `SIGTERM` and `SIGINT` signals:

```bash
# Stop worker gracefully (finishes current job)
kill -SIGTERM <worker_pid>

# Or use Ctrl+C
```

## CLI Commands

### Setup

```bash
# Setup database tables for the queue system
php console queue:setup
```

This command will interactively ask for the table names (defaults: `jobs` and `failed_jobs`) and create them if they don't exist. It also provides the necessary `.env` configuration.

### Queue Management

```bash
# List all queues with statistics
php console queue:list

# View queue statistics
php console queue:stats default

# Clear a queue
php console queue:clear default
```

### Failed Jobs

```bash
# List failed jobs
php console queue:failed --limit=20

# Retry failed jobs and moves them back to their original queue
php console queue:retry --limit=100

# Permanently delete all failed jobs
php console queue:flush
```

### Job Creation

```bash
# Generate a new job class
php console make:job ProcessOrderJob
php console make:job Notifications/SendPushNotification
```

## Queue Operations

### Monitoring

```php
// Get queue statistics
$stats = $queue->getStats('default');
/*
[
    'ready' => 10,
    'processing' => 2,
    'delayed' => 5,
    'failed' => 1
]
*/

// Count jobs in queue
$count = $queue->count('emails');

// Count failed jobs
$failedCount = $queue->countFailed();

// List all queues
$queues = $queue->getQueues();
```

### Queue Inspection

```php
// List jobs (without removing)
$jobs = $queue->listQueue('default', 10);

// Peek at next job (without removing)
$nextJob = $queue->peek('default');

// Find specific job by ID
$job = $queue->findJob('job_abc123', 'default');
```

### Job Management

```php
// Delete specific job
$queue->deleteJob('job_abc123', 'default');

// Move job between queues
$queue->moveJobToQueue('job_abc123', 'from_queue', 'to_queue');

// Clear entire queue
$queue->clear('default');

// Purge all queues
$queue->purge();
```

### Failed Jobs

```php
// Get failed jobs
$failedJobs = $queue->getFailed(20);

// Retry all failed jobs & move them back to their original queues
$queue->retryFailed(100);

// Remove specific failed jobs & Accept string or simple array of job IDs
$queue->removeFailedJobs(['job_123', 'job_456']);

// Clear all failed jobs
$queue->clearFailed();
```

## Advanced Usage

### Custom Worker

```php
use MonkeysLegion\Queue\Worker\Worker;
use MonkeysLegion\Queue\Factory\QueueFactory;

$config = require 'config/queue.php';
$factory = new QueueFactory($config);
$queue = $factory->make();

$worker = new Worker(
    queue: $queue,
    sleep: 3,
    maxTries: 5,
    memory: 256,
    timeout: 120,
    delayedCheckInterval: 30
);

// Start processing
$worker->work('default', 3);

// Get worker stats
$stats = $worker->getStats();
/*
[
    'processed_jobs' => 42,
    'memory_usage_mb' => 45.23,
    'should_quit' => false
]
*/
```

### Job Retries with Exponential Backoff

The worker automatically retries failed jobs with exponential backoff:

- **Attempt 1**: Retry after 1 second (2^0)
- **Attempt 2**: Retry after 2 seconds (2^1)
- **Attempt 3**: Retry after 4 seconds (2^2)
- **Attempt 4**: Retry after 8 seconds (2^3)
- **Attempt 5**: Retry after 16 seconds (2^4)
- **Attempt 6**: Retry after 32 seconds (2^5)
- **Attempt 7+**: Retry after 60 seconds (capped)

### Null Queue (Testing)

Use the Null queue driver for testing without actual queue operations:

```php
$factory = new QueueFactory([
    'default' => 'null',
    'settings' => [],
    'stores' => ['null' => []],
]);

$queue = $factory->make();

// All operations are no-ops
$queue->push(['job' => 'TestJob', 'payload' => []]);
$job = $queue->pop(); // Returns null
$count = $queue->count(); // Returns 0
```

## Architecture

### Components

```
src/
├── Abstract/
│   └── AbstractQueue.php        # Base queue implementation
├── Cli/
│   └── Command/                 # CLI commands
│       ├── MakeJobCommand.php
│       ├── QueueWorkCommand.php
│       ├── QueueListCommand.php
│       ├── QueueClearCommand.php
│       ├── QueueFailedCommand.php
│       ├── QueueRetryCommand.php
│       ├── QueueFlushCommand.php
│       └── QueueStatsCommand.php
├── Contracts/
│   ├── JobInterface.php         # Job contract
│   ├── DispatcherInterface.php  # Job dispatcher contract
│   ├── QueueInterface.php       # Queue driver contract
│   └── WorkerInterface.php      # Worker contract
├── Driver/
│   ├── RedisQueue.php          # Redis implementation
│   └── NullQueue.php           # Null implementation
├── Factory/
│   └── QueueFactory.php        # Queue factory
├── Helpers/
│   └── CliPrinter.php          # CLI output helper
├── Job/
│   └── Job.php                 # Job wrapper
└── Worker/
    └── Worker.php              # Queue worker
```

### Flow

```
┌─────────────┐
│   Dispatch  │
│     Job     │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│    Queue    │
│   (Redis)   │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   Worker    │
│   Polling   │
└──────┬──────┘
       │
       ▼
┌─────────────┐     Success     ┌─────────────┐
│   Process   ├────────────────►│     ACK     │
│     Job     │                 └─────────────┘
└──────┬──────┘
       │
       │ Failure
       ▼
┌─────────────┐     Max Tries   ┌─────────────┐
│    Retry    ├────────────────►│    Failed   │
│   (Delay)   │    Exceeded     │    Queue    │
└─────────────┘                 └─────────────┘
```

## Requirements

- PHP 8.4 or higher
- Redis extension (for Redis driver)
- MonkeysLegion CLI package

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues, questions, or suggestions, please open an issue on GitHub.

## Roadmap

- [x] Priority queues
- [ ] Job batching
- [ ] Job chaining
- [ ] Rate limiting
- [ ] Queue events/hooks
- [ ] Dashboard UI
- [ ] Metrics & analytics

---

Made with ❤️ by MonkeysLegion
