<?php

return [
    // The default store to use (redis, database, null)
    'default' => $_ENV['QUEUE_DEFAULT'] ?? 'redis',

    // Core queue behavior (used by AbstractQueue)
    'settings' => [
        'default_queue'      => $_ENV['QUEUE_DEFAULT_QUEUE'] ?? 'default',
        'failed_queue'       => $_ENV['QUEUE_FAILED_QUEUE'] ?? 'failed',
        'queue_prefix'       => $_ENV['QUEUE_PREFIX'] ?? 'ml_queue',
        'retry_after'        => $_ENV['QUEUE_RETRY_AFTER'] ?? 90,
        'visibility_timeout' => $_ENV['QUEUE_VISIBILITY_TIMEOUT'] ?? 300,
        'max_attempts'       => $_ENV['QUEUE_MAX_ATTEMPTS'] ?? 3,
    ],

    // Queue stores without "driver" key
    'stores' => [
        'redis' => [
            'host'     => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port'     =>  $_ENV['REDIS_PORT'] ?? 6379,
            'username' =>  $_ENV['REDIS_USERNAME'] ?? null,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_DATABASE'] ?? 0,
            'timeout'  => $_ENV['REDIS_TIMEOUT'] ?? 2.0,
        ],

        'database' => [
            // Only keep table name – NO driver key
            'table' => $_ENV['QUEUE_DATABASE_TABLE'] ?? 'jobs',
        ],

        'null' => [
            // Keep empty as requested
        ],
    ],
];
