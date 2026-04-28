# MonkeysLegion Queue 2.0

A production-grade, driver-agnostic queue system for PHP 8.4+ with automatic retries, job chaining & batching, rate limiting, lifecycle events, a built-in web dashboard, and a comprehensive CLI toolkit.

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

---

## What's New in 2.0

| Area | v1 | v2 |
|---|---|---|
| Job contract | Plain classes with `handle()` | `DispatchableJobInterface` — typed, explicit |
| Serialization | Manual payload arrays | `JobSerializer` trait — auto-extracts constructor params, freezes objects |
| Worker DI | All dependencies passed manually | `ContainerAware` trait — auto-resolves `QueueEventDispatcher`, `RateLimiterInterface`, `BatchRepository`, `MonkeysLoggerInterface` |
| Rate limiting | Concrete `RateLimiter` only | `RateLimiterInterface` contract — pluggable implementations |
| Dashboard | ❌ | Built-in web UI with `QueueDashboardProvider` auto-discovery |
| Provider system | ❌ | `#[Provider]` attribute + `composer.json` auto-registration |
| Configuration | PHP array | `.mlc` config format (env-driven) |
| Dispatcher | Returns `void` | Returns `Batch` from `batch()->dispatch()` for progress tracking |

---

## Features

✨ **Multiple Queue Drivers** — Redis (production), Database (production), Null (testing)

🔄 **Automatic Retries** — Exponential backoff, configurable max attempts, failed job tracking

⏰ **Delayed Jobs & Dispatching** — Schedule future execution, priority queue support, clean dispatcher API

🔗 **Job Batching & Chaining** — Sequential chains, parallel batches with completion/failure/finally callbacks

⚡ **Rate Limiting** — Token-bucket `RateLimiterInterface`, per-queue or per-job-type throttling

🎯 **Queue Events** — `JobProcessing`, `JobProcessed`, `JobFailed`, `BatchCompleted`

📊 **Web Dashboard** — Real-time queue stats, failed job inspection, maintenance actions — auto-registered via `QueueDashboardProvider`

🛡️ **Production Ready** — Graceful SIGTERM/SIGINT shutdown, memory-limit protection, GC-aware polling, DI-driven worker

---

## Installation

```bash
composer require monkeyscloud/monkeyslegion-queue
```

> Requires **PHP 8.4+**, the `ext-redis` extension (for the Redis driver), and the MonkeysLegion CLI package.

---

## Configuration

### MLC Format (`config/queue.mlc`)

```mlc
queue {
    # The default store to use (redis, database, null)
    default = ${QUEUE_DEFAULT:-null}

    # Core queue behavior
    settings {
        default_queue      = ${QUEUE_DEFAULT_QUEUE:-default}
        failed_queue       = ${QUEUE_FAILED_QUEUE:-failed}
        queue_prefix       = ${QUEUE_PREFIX:-ml_queue}
        retry_after        = ${QUEUE_RETRY_AFTER:-90}
        visibility_timeout = ${QUEUE_VISIBILITY_TIMEOUT:-300}
        max_attempts       = ${QUEUE_MAX_ATTEMPTS:-3}
        path               = ${QUEUE_VIEW_PATH:-ml-queue}
    }

    # Queue drivers
    stores {
        redis {
            host     = ${REDIS_HOST:-127.0.0.1}
            port     = ${REDIS_PORT:-6379}
            username = ${REDIS_USERNAME:-null}
            password = ${REDIS_PASSWORD:-null}
            database = ${REDIS_DATABASE:-0}
            timeout  = ${REDIS_TIMEOUT:-2.0}
        }

        null {}

        database {
            table        = ${QUEUE_DATABASE_TABLE:-jobs}
            failed_table = ${QUEUE_DATABASE_FAILED_TABLE:-failed_jobs}
        }
    }
}
```

### Environment Variables

```env
# Queue
QUEUE_DEFAULT=redis
QUEUE_DEFAULT_QUEUE=default
QUEUE_FAILED_QUEUE=failed
QUEUE_PREFIX=ml_queue
QUEUE_MAX_ATTEMPTS=3

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0
REDIS_TIMEOUT=2.0

# Database driver
QUEUE_DATABASE_TABLE=jobs
QUEUE_DATABASE_FAILED_TABLE=failed_jobs
```

---

## Quick Start

### 1 — Create a Queue Instance

```php
use MonkeysLegion\Queue\Factory\QueueFactory;
use MonkeysLegion\Database\Contracts\ConnectionInterface;

$config = require 'config/queue.php';

// Null / Redis (no DB connection needed)
$factory = new QueueFactory($config);
$queue   = $factory->make();          // default driver from config

// Database driver requires a ConnectionInterface
$factory = new QueueFactory($config, $dbConnection);
$queue   = $factory->driver('database');
```

### 2 — Define a Job

All dispatchable jobs **must** implement `DispatchableJobInterface`:

```php
<?php

namespace App\Jobs;

use MonkeysLegion\Queue\Contracts\DispatchableJobInterface;

class SendEmailJob implements DispatchableJobInterface
{
    public function __construct(
        public string $email,
        public string $subject,
        public string $message,
    ) {}

    public function handle(): void
    {
        mail($this->email, $this->subject, $this->message);
    }
}
```

> **Tip:** Generate the boilerplate with `php console make:job SendEmailJob`.

#### Marker Interfaces

| Interface | Behaviour |
|---|---|
| `DispatchableJobInterface` | **Required.** Provides the `handle()` contract. |
| `ShouldQueue` | *Optional semantic marker.* Explicitly signals "this class is async." All jobs are async by default. |
| `ShouldSync` | Causes the dispatcher to run `handle()` **synchronously** in the current process, skipping the queue entirely. |

### 3 — Dispatch

```php
use MonkeysLegion\Queue\Dispatcher\QueueDispatcher;
use App\Jobs\SendEmailJob;

$dispatcher = new QueueDispatcher($queue);

$job = new SendEmailJob('user@example.com', 'Welcome!', 'Thanks for joining');

// Immediate push
$dispatcher->dispatch($job);

// To a specific queue
$dispatcher->dispatch($job, queue: 'emails');

// With delay (seconds)
$dispatcher->dispatch($job, queue: 'emails', delay: 60);

// At an absolute timestamp
$dispatcher->dispatchAt($job, timestamp: time() + 3600, queue: 'emails');
```

The `JobSerializer` trait auto-extracts constructor parameters—including nested objects (serialised for safe JSON/DB storage)—so you never build payload arrays by hand.

### 4 — Run a Worker

```bash
php console queue:work --queue=emails --sleep=3 --tries=5 --memory=256 --timeout=120
```

| Flag | Default | Description |
|---|---|---|
| `--queue` | `default` | Comma-separated list; first queue has highest priority |
| `--sleep` | `3` | Seconds to idle when the queue is empty |
| `--tries` | `3` | Max retry attempts per job |
| `--memory` | `128` | Memory limit in MB |
| `--timeout` | `60` | Per-job timeout in seconds |

**Priority queues** — `--queue=high,default,low` processes `high` first, then `default`, then `low`.

Worker output:

```
[09:45:12] • Worker started (queue=default)
[09:45:13] → Processing (class=App\Jobs\SendEmailJob, job_id=1a2b3c4d, attempts=1, queue=default)
[09:45:14] ✓ Completed  (class=App\Jobs\SendEmailJob, job_id=1a2b3c4d, duration_ms=1250.45, queue=default)
[09:45:18] ⚠ Pushed to retry later (class=App\Jobs\SendEmailJob, job_id=5e6f7g8h, attempts=1, delay=1)
[09:45:22] ✗ Failed     (class=App\Jobs\SendEmailJob, job_id=5e6f7g8h, attempts=3)
```

Graceful shutdown: `kill -SIGTERM <pid>` or `Ctrl+C`. The worker finishes the current job, then exits.

---

## Advanced Usage

### Job Chaining

Jobs run **sequentially** — each starts only after the previous succeeds. Chain metadata is embedded in the job payload; the `Job` wrapper dispatches the next job on completion.

```php
$dispatcher->chain([
    new DownloadFileJob($url),
    new ProcessFileJob($path),
    new NotifyUserJob($userId),
])->onQueue('files')->dispatch();
```

> [!IMPORTANT]
> Jobs inside a Chain or Batch are **always** queued, regardless of `ShouldSync`.

### Job Batching

Group parallel jobs, track progress, and register callbacks:

```php
$batch = $dispatcher->batch([
    new ProcessImageJob($image1),
    new ProcessImageJob($image2),
    new ProcessImageJob($image3),
])
->onQueue('images')
->then('App\\Callbacks\\BatchSuccess')
->catch('App\\Callbacks\\BatchFailed')
->finally('App\\Callbacks\\BatchComplete')
->dispatch();

echo $batch->progress() . '% complete';
echo $batch->getPendingJobs() . ' jobs remaining';
```

> Batching requires a `BatchRepository` (database-backed). Pass it to `QueueDispatcher`:
> ```php
> $dispatcher = new QueueDispatcher($queue, new BatchRepository($dbConnection));
> ```

### Rate Limiting

Throttle job throughput with the `RateLimiterInterface` contract. The built-in `RateLimiter` uses an in-memory token-bucket:

```php
use MonkeysLegion\Queue\RateLimiter\RateLimiter;

$rateLimiter = new RateLimiter(
    maxAttempts:  60,   // 60 jobs …
    decaySeconds: 60,   // … per minute
);
```

The `Worker` resolves `RateLimiterInterface` from the DI container automatically. When rate-limited, jobs are released back with a delay.

### Queue Events

```php
use MonkeysLegion\Queue\Events\QueueEventDispatcher;
use MonkeysLegion\Queue\Events\JobProcessed;
use MonkeysLegion\Queue\Events\JobFailed;
use MonkeysLegion\Queue\Events\JobProcessing;
use MonkeysLegion\Queue\Events\BatchCompleted;

$events = new QueueEventDispatcher();

$events->listen(JobProcessed::class, function (JobProcessed $e) {
    Log::info("Job {$e->job->getId()} done in {$e->processingTimeMs}ms");
});

$events->listen(JobFailed::class, function (JobFailed $e) {
    Log::error("Job failed: " . $e->exception->getMessage());
    if (!$e->willRetry) {
        // Final failure — alert ops
    }
});
```

The worker dispatches events automatically when `QueueEventDispatcher` is registered in the container.

---

## Dashboard

The queue package ships a full web dashboard, auto-registered via the `QueueDashboardProvider`.

### Setup

Add the provider to your framework's `composer.json` extra key (done automatically on install):

```json
{
  "extra": {
    "monkeyslegion": {
      "providers": [
        "MonkeysLegion\\Queue\\Providers\\QueueDashboardProvider"
      ]
    }
  }
}
```

The provider registers template views, scans the `DashboardController`, and resolves the URL prefix from your queue config's `path` setting.

### Routes

All routes live under `/{path}/dashboard` (default: `/ml-queue/dashboard`):

| Method | Route | Action |
|---|---|---|
| `GET` | `/` | Overview — queue list, stats, failed count |
| `GET` | `/queues` | Browse jobs in a specific queue |
| `GET` | `/delayed` | Inspect delayed/scheduled jobs |
| `GET` | `/failed` | List failed jobs |
| `GET` | `/maintenance` | Maintenance panel |
| `GET` | `/job?id=…&queue=…` | Single job detail |
| `POST` | `/job/delete` | Delete a job |
| `POST` | `/failed/retry` | Retry failed jobs |
| `POST` | `/failed/delete` | Delete specific failed jobs |
| `POST` | `/failed/clear` | Purge all failed jobs |
| `POST` | `/queue/clear` | Clear a queue |
| `POST` | `/queue/purge` | Purge **all** queues |
| `POST` | `/delayed/process` | Force-process delayed jobs now |

---

## CLI Commands

### Setup

```bash
# Create the jobs and failed_jobs tables interactively
php console queue:setup
```

### Queue Management

```bash
php console queue:list           # List all queues with statistics
php console queue:stats default  # View stats for a specific queue
php console queue:clear default  # Clear a queue
```

### Failed Jobs

```bash
php console queue:failed --limit=20   # List failed jobs
php console queue:retry  --limit=100  # Retry failed → move back to original queue
php console queue:flush               # Permanently delete all failed jobs
```

### Job Scaffolding

```bash
php console make:job ProcessOrderJob
php console make:job Notifications/SendPushNotification
```

---

## Programmatic Queue Operations

### Push & Pop

```php
// Direct push (raw array format)
$queue->push([
    'job'     => 'App\\Jobs\\SendEmailJob',
    'payload' => ['user@example.com', 'Welcome!', 'Thanks'],
], 'emails');

// Delayed push
$queue->later(60, [
    'job'     => 'App\\Jobs\\SendReminderJob',
    'payload' => ['user_id' => 123],
]);

// Bulk push
$queue->bulk([
    ['job' => 'App\\Jobs\\SendEmailJob', 'payload' => ['a@b.com', 'Hi', 'Body']],
    ['job' => 'App\\Jobs\\SendEmailJob', 'payload' => ['c@d.com', 'Hi', 'Body']],
], 'emails');
```

### Monitoring

```php
$stats = $queue->getStats('default');
// ['ready' => 10, 'processing' => 2, 'delayed' => 5, 'failed' => 1]

$count       = $queue->count('emails');
$failedCount = $queue->countFailed();
$queues      = $queue->getQueues();
```

### Inspection

```php
$jobs    = $queue->listQueue('default', 10);
$nextJob = $queue->peek('default');
$job     = $queue->findJob('job_abc123', 'default');
```

### Management

```php
$queue->deleteJob('job_abc123', 'default');
$queue->moveJobToQueue('job_abc123', 'from_queue', 'to_queue');
$queue->clear('default');
$queue->purge();
```

### Failed Jobs

```php
$failedJobs = $queue->getFailed(20);
$queue->retryFailed(100);
$queue->removeFailedJobs(['job_123', 'job_456']);
$queue->clearFailed();
```

---

## Null Queue (Testing)

```php
$factory = new QueueFactory([
    'default'  => 'null',
    'settings' => [],
    'stores'   => ['null' => []],
]);

$queue = $factory->make();

$queue->push(['job' => 'TestJob', 'payload' => []]); // no-op
$queue->pop();   // null
$queue->count(); // 0
```

---

## Job Retries — Exponential Backoff

| Attempt | Delay |
|---|---|
| 1 | 1 s |
| 2 | 2 s |
| 3 | 4 s |
| 4 | 8 s |
| 5 | 16 s |
| 6 | 32 s |
| 7+ | 60 s (cap) |

---

## Architecture

```
src/
├── Abstract/
│   └── AbstractQueue.php            # Base queue with config, encode/decode, default impls
├── Batch/
│   ├── Batch.php                    # Batch state container (progress, cancel, toArray)
│   ├── BatchRepository.php          # Database-backed batch storage
│   └── PendingBatch.php             # Fluent batch builder (then/catch/finally)
├── Chain/
│   └── PendingChain.php             # Fluent chain builder (onQueue/dispatch)
├── Cli/
│   └── Command/
│       ├── MakeJobCommand.php
│       ├── QueueWorkCommand.php
│       ├── QueueListCommand.php
│       ├── QueueClearCommand.php
│       ├── QueueFailedCommand.php
│       ├── QueueRetryCommand.php
│       ├── QueueFlushCommand.php
│       ├── QueueStatsCommand.php
│       └── SetupDatabaseCommand.php # Interactive table creation
├── Contracts/
│   ├── DispatchableJobInterface.php # handle() contract for user jobs
│   ├── JobInterface.php             # Internal job wrapper contract
│   ├── QueueDispatcherInterface.php # dispatch() / dispatchAt()
│   ├── QueueInterface.php           # Queue driver contract (22 methods)
│   ├── ShouldQueue.php              # Semantic async marker
│   ├── ShouldSync.php               # Forces synchronous execution
│   └── WorkerInterface.php          # work() / process() / stop()
├── Dashboard/
│   └── DashboardController.php      # Web UI controller (attribute-routed)
├── Dispatcher/
│   └── QueueDispatcher.php          # Dispatch, chain, batch entry point
├── Driver/
│   ├── DatabaseQueue.php            # PDO / ConnectionInterface driver
│   ├── RedisQueue.php               # ext-redis driver
│   └── NullQueue.php                # No-op driver for tests
├── Events/
│   ├── QueueEventDispatcher.php     # listen() / dispatch() / forget()
│   ├── JobProcessing.php            # Before execution
│   ├── JobProcessed.php             # After success (includes processingTimeMs)
│   ├── JobFailed.php                # On failure (includes willRetry flag)
│   └── BatchCompleted.php           # When batch finishes
├── Factory/
│   └── QueueFactory.php             # make() / driver() factory
├── Helpers/
│   └── CliPrinter.php               # Styled CLI output
├── Job/
│   └── Job.php                      # Internal wrapper — deserialise, handle, chain dispatch
├── Providers/
│   └── QueueDashboardProvider.php   # Auto-registers dashboard views & routes
├── RateLimiter/
│   ├── RateLimiterInterface.php     # attempt() / availableIn() / remaining() / reset()
│   └── RateLimiter.php              # In-memory token-bucket implementation
├── Template/
│   └── views/dashboard/             # Dashboard Blade-like templates
├── Traits/
│   └── JobSerializer.php            # serializeJob() / unserializeJob() — shared across dispatcher, chain, batch
└── Worker/
    └── Worker.php                   # ContainerAware worker with signal handling & GC
```

### Data Flow

```
┌─────────────────┐
│   Dispatcher    │
│  dispatch(job)  │
└────────┬────────┘
         │  serialiseJob()
         ▼
┌─────────────────┐
│  Queue Driver   │
│  (Redis / DB)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│     Worker      │
│   pop → handle  │
└────────┬────────┘
         │
    ┌────┴────┐
    ▼         ▼
 Success    Failure
    │         │
    ▼         ▼
  ack()    attempts < max?
            ├─ yes → release(delay)
            └─ no  → fail() → failed queue
```

---

## Database Migrations

Three migration templates ship under `migration/`:

- `queue_jobs.sql` — main jobs table
- `failed_jobs.sql` — failed jobs table
- `job_batches.sql` — batch tracking table

Run `php console queue:setup` for interactive table creation, or apply the SQL files directly.

---

## Requirements

- PHP 8.4 or higher
- Redis extension (`ext-redis`) for the Redis driver
- `monkeyscloud/monkeyslegion-cli` ^2.0
- `monkeyscloud/monkeyslegion-database` ^2.0 (for the Database driver & batch storage)

---

## License

MIT License. See [LICENSE](LICENSE) for details.

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues, questions, or suggestions, please open an issue on GitHub.

---

## Roadmap

- [x] Priority queues
- [x] Job batching
- [x] Job chaining
- [x] Rate limiting
- [x] Queue events / hooks
- [x] Dashboard UI
- [ ] Metrics & analytics
- [ ] Horizontal scaling helpers

---

Made with ❤️ by MonkeysLegion
