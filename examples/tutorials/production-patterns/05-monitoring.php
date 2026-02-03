<?php

/**
 * Production Patterns Tutorial 5: Monitoring
 * 
 * Run: php examples/tutorials/production-patterns/05-monitoring.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Observability\Metrics;

echo "=== Production Patterns Tutorial 5: Monitoring ===\n\n";

$metrics = new Metrics();

// Simulated agent execution monitoring
echo "Simulating agent executions...\n\n";

for ($i = 0; $i < 5; $i++) {
    $success = $i < 4; // 4 successes, 1 failure
    $duration = rand(100, 500) / 1000; // 0.1-0.5 seconds
    
    if ($success) {
        $metrics->increment('agent.executions.success');
    } else {
        $metrics->increment('agent.executions.failure');
    }
    
    $metrics->timing('agent.duration', $duration);
    $metrics->gauge('agent.tokens', rand(100, 500));
}

echo "Metrics Summary:\n";
echo "- Successes: {$metrics->get('agent.executions.success')}\n";
echo "- Failures: {$metrics->get('agent.executions.failure')}\n";
echo "- Success Rate: " . 
    ($metrics->get('agent.executions.success') / 5 * 100) . "%\n";

echo "\n✓ Example complete!\n";
