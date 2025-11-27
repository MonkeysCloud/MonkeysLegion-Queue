<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Disable CLI output during tests
if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}
