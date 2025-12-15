<?php

/**
 * Production Operations Agents Example
 * 
 * Demonstrates MonitoringAgent, SchedulerAgent, and AlertAgent
 * working together for system monitoring and alerting.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Agents\SchedulerAgent;
use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Metric;
use ClaudeAgents\Monitoring\Alert;
use ClaudePhp\ClaudePhp;

// Load API key
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    $_ENV['ANTHROPIC_API_KEY'] = $env['ANTHROPIC_API_KEY'] ?? '';
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
if (empty($apiKey)) {
    die("Error: ANTHROPIC_API_KEY not set\n");
}

$client = new ClaudePhp($apiKey);

echo "Production Operations Agents Demo\n";
echo "==================================\n\n";

// 1. Monitoring Agent Example
echo "1. Monitoring Agent\n";
echo "-------------------\n";

$monitor = new MonitoringAgent($client, [
    'thresholds' => [
        'cpu_usage' => 80,
        'memory_usage' => 90,
        'error_rate' => 5,
    ],
]);

$metrics = <<<METRICS
cpu_usage: 85
memory_usage: 75
error_rate: 3
response_time: 250
METRICS;

$result = $monitor->run($metrics);
echo $result->getAnswer() . "\n\n";

// 2. Alert Agent Example
echo "2. Alert Agent\n";
echo "--------------\n";

$alertAgent = new AlertAgent($client, [
    'aggregation_window' => 300, // 5 minutes
]);

// Register alert channels
$alertAgent->registerChannel('log', function (Alert $alert, string $message) {
    echo "[ALERT] {$alert->getSeverity()}: {$message}\n";
});

$alertAgent->registerChannel('webhook', function (Alert $alert, string $message) {
    echo "[WEBHOOK] Sending to monitoring system: {$alert->getTitle()}\n";
});

// Process an alert
$alertTask = "Critical: CPU usage exceeded threshold\nCPU at 95%, threshold is 80%";
$result = $alertAgent->run($alertTask);
echo "\n";

// 3. Scheduler Agent Example
echo "3. Scheduler Agent\n";
echo "------------------\n";

$scheduler = new SchedulerAgent($client);

// Schedule a recurring task
$scheduler->schedule('health-check', '0 * * * *', function () {
    echo "Running hourly health check...\n";
    return "Health check completed";
});

// Schedule a one-time task
$scheduler->scheduleOnce('backup', '+2 hours', function () {
    echo "Running backup...\n";
    return "Backup completed";
});

// Simulate checking for pending tasks
$result = $scheduler->run("Check and execute pending tasks");
echo $result->getAnswer() . "\n";

echo "\nProduction Ops Demo Complete!\n";

