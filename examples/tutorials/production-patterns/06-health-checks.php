<?php

/**
 * Production Patterns Tutorial 6: Health Checks
 * 
 * Run: php examples/tutorials/production-patterns/06-health-checks.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Observability\HealthCheck;

echo "=== Production Patterns Tutorial 6: Health Checks ===\n\n";

$healthCheck = new HealthCheck();

// Add checks
$healthCheck->addCheck('api_key', function () {
    $key = getenv('ANTHROPIC_API_KEY');
    return [
        'status' => $key ? 'healthy' : 'unhealthy',
        'message' => $key ? 'API key configured' : 'API key missing',
    ];
});

$healthCheck->addCheck('disk_space', function () {
    $free = disk_free_space('/');
    $total = disk_total_space('/');
    $percent = ($free / $total) * 100;
    
    return [
        'status' => $percent > 10 ? 'healthy' : 'unhealthy',
        'free_percent' => round($percent, 2),
    ];
});

// Run health checks
echo "Running health checks...\n\n";
$status = $healthCheck->check();

echo "Health Status:\n";
echo json_encode($status, JSON_PRETTY_PRINT) . "\n";

echo "\n✓ Example complete!\n";
