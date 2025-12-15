#!/usr/bin/env php
<?php
/**
 * Alert Agent Basic Example
 *
 * Demonstrates basic usage of the AlertAgent for intelligent alert processing
 * and notification handling with LLM-enhanced messages.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Simple console logger
class ConsoleLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('H:i:s');
        $emoji = match ($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📝',
        };
        echo "[{$timestamp}] {$emoji} [{$level}] {$message}\n";
    }
}

// Load environment
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? throw new RuntimeException('ANTHROPIC_API_KEY not set');
$client = new ClaudePhp(apiKey: $apiKey);

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                        Alert Agent Basic Example                          ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create alert agent with logger
$logger = new ConsoleLogger();
$alertAgent = new AlertAgent($client, [
    'name' => 'demo_alert_agent',
    'aggregation_window' => 300, // 5 minutes
    'logger' => $logger,
]);

echo "📡 Setting up alert channels...\n\n";

// Register console channel (for demo purposes)
$alertAgent->registerChannel('console', function (Alert $alert, string $message) {
    $emoji = match ($alert->getSeverity()) {
        Alert::SEVERITY_CRITICAL => '🔴',
        Alert::SEVERITY_ERROR => '🟠',
        Alert::SEVERITY_WARNING => '🟡',
        Alert::SEVERITY_INFO => '🔵',
        default => '⚪',
    };

    echo "\n" . str_repeat("─", 80) . "\n";
    echo "{$emoji} ALERT: {$alert->getTitle()}\n";
    echo str_repeat("─", 80) . "\n";
    echo "Severity: " . strtoupper($alert->getSeverity()) . "\n";
    echo "Time: " . date('Y-m-d H:i:s', (int)$alert->getTimestamp()) . "\n";
    
    if ($alert->getMetric()) {
        echo "Metric: {$alert->getMetric()->getName()} = {$alert->getMetric()->getValue()}\n";
    }
    
    echo "\nEnhanced Message:\n{$message}\n";
    echo str_repeat("─", 80) . "\n\n";
});

// Register log file channel
$alertAgent->registerChannel('logfile', function (Alert $alert, string $message) {
    $logFile = __DIR__ . '/alert.log';
    $entry = sprintf(
        "[%s] [%s] %s: %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($alert->getSeverity()),
        $alert->getTitle(),
        $message
    );
    file_put_contents($logFile, $entry, FILE_APPEND);
});

echo "✅ Channels registered: console, logfile\n\n";

// Example 1: Basic alert
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Basic Alert\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$alert1 = new Alert(
    title: 'System Update Available',
    message: 'A new system update is available for installation',
    severity: Alert::SEVERITY_INFO
);

$alertAgent->processAlert($alert1);

sleep(1); // Brief pause between examples

// Example 2: Alert with metric
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Alert with Metric\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$cpuMetric = new Metric('cpu_usage', 92.5, [
    'host' => 'web-server-01',
    'region' => 'us-east-1',
]);

$alert2 = new Alert(
    title: 'High CPU Usage Detected',
    message: 'CPU usage has exceeded 90% threshold',
    severity: Alert::SEVERITY_WARNING,
    metric: $cpuMetric,
    context: ['threshold' => 90, 'duration' => '5 minutes']
);

$alertAgent->processAlert($alert2);

sleep(1);

// Example 3: Critical alert
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Critical Alert\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$errorMetric = new Metric('error_rate', 15.5, [
    'endpoint' => '/api/checkout',
    'normal_rate' => 0.5,
]);

$alert3 = new Alert(
    title: 'Critical Error Rate Spike',
    message: 'Error rate has increased dramatically on checkout endpoint',
    severity: Alert::SEVERITY_CRITICAL,
    metric: $errorMetric,
    context: [
        'endpoint' => '/api/checkout',
        'affected_users' => 247,
        'impact' => 'Revenue loss',
    ]
);

$alertAgent->processAlert($alert3);

sleep(1);

// Example 4: Using run() method with natural language
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Natural Language Alert Processing\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$result = $alertAgent->run(
    "Warning: Database connection pool exhausted. Current connections: 500/500. " .
    "Some requests are timing out. This needs immediate attention."
);

if ($result->isSuccess()) {
    echo "✅ Alert processed successfully\n";
    echo "📊 Metadata: " . json_encode($result->getMetadata(), JSON_PRETTY_PRINT) . "\n";
}

// Show alert history
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Alert History\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$history = $alertAgent->getSentAlerts(10);
echo "📜 Total alerts sent: " . count($history) . "\n\n";

foreach ($history as $i => $entry) {
    $alert = $entry['alert'];
    echo ($i + 1) . ". [{$alert->getSeverity()}] {$alert->getTitle()}\n";
    echo "   Time: " . date('H:i:s', (int)$entry['timestamp']) . "\n";
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "✨ Alert Agent example completed!\n";
echo "📝 Check alert.log file for logged alerts\n";
echo str_repeat("═", 80) . "\n";

