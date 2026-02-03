<?php

/**
 * Production Patterns Tutorial 2: Structured Logging
 * 
 * Run: php examples/tutorials/production-patterns/02-logging.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

echo "=== Production Patterns Tutorial 2: Structured Logging ===\n\n";

// Setup logger
$logger = new Logger('agent');

// Console handler
$consoleHandler = new StreamHandler('php://stdout', Logger::INFO);
$logger->pushHandler($consoleHandler);

// File handler with JSON format
$fileHandler = new StreamHandler(__DIR__ . '/app.log', Logger::INFO);
$fileHandler->setFormatter(new JsonFormatter());
$logger->pushHandler($fileHandler);

// Log examples
echo "Logging examples...\n";

$logger->info('Agent execution started', [
    'query' => 'What is 2+2?',
    'agent_type' => 'ReactAgent',
]);

$logger->warning('Rate limit approaching', [
    'current_usage' => 95,
    'limit' => 100,
]);

$logger->error('Agent execution failed', [
    'error' => 'Connection timeout',
    'retry_count' => 3,
]);

echo "\n✓ Logs written to console and file\n";
echo "✓ Check app.log for JSON formatted logs\n";

echo "\n✓ Example complete!\n";
