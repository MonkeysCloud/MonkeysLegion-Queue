<?php

/**
 * Test Bootstrap for MonkeysLegion Queue.
 *
 * This file initializes the environment for PHPUnit testing, including
 * the global DI container and common service mocks.
 */
declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects

require_once __DIR__ . '/../vendor/autoload.php';

// Disable CLI output during tests
if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

// Initialize a global DI container for tests to prevent "Container instance not set" errors
$container = new \MonkeysLegion\DI\Container();
\MonkeysLegion\DI\Container::setInstance($container);

// Prevent Worker from trying to autowire real database repositories by providing null factories.
// These are optional services that the Worker checks via has() before resolving.
$container->set(\MonkeysLegion\Queue\Batch\BatchRepository::class, fn() => null);
$container->set(\MonkeysLegion\Queue\Events\QueueEventDispatcher::class, fn() => null);
$container->set(\MonkeysLegion\Queue\RateLimiter\RateLimiterInterface::class, fn() => null);
$container->set(\MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface::class, fn() => null);
