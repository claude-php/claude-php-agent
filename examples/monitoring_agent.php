#!/usr/bin/env php
<?php
/**
 * Monitoring Agent Basic Example
 *
 * Demonstrates basic usage of the MonitoringAgent for metric analysis,
 * anomaly detection, and threshold-based alerting.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;
use ClaudeAgents\Contracts\MonitorableInterface;
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

// Simple monitorable system metrics class
class SystemMetrics implements MonitorableInterface
{
    public function __construct(
        private string $name = 'system',
        private bool $simulateIssue = false
    ) {}

    public function getMetrics(): array
    {
        // Simulate getting system metrics
        $cpuUsage = $this->simulateIssue ? rand(85, 98) : rand(20, 75);
        $memoryUsage = $this->simulateIssue ? rand(88, 95) : rand(30, 70);
        $diskUsage = rand(40, 80);
        $networkLatency = $this->simulateIssue ? rand(150, 300) : rand(10, 50);
        
        return [
            new Metric('cpu_usage', $cpuUsage, ['unit' => 'percent']),
            new Metric('memory_usage', $memoryUsage, ['unit' => 'percent']),
            new Metric('disk_usage', $diskUsage, ['unit' => 'percent']),
            new Metric('network_latency', $networkLatency, ['unit' => 'ms']),
        ];
    }

    public function getName(): string
    {
        return $this->name;
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
echo "║                     Monitoring Agent Basic Example                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create monitoring agent with logger
$logger = new ConsoleLogger();
$monitoringAgent = new MonitoringAgent($client, [
    'name' => 'system_monitor',
    'check_interval' => 5,
    'thresholds' => [
        'cpu_usage' => 80,
        'memory_usage' => 85,
        'disk_usage' => 90,
        'network_latency' => 100,
    ],
    'logger' => $logger,
]);

echo "📊 Monitoring Agent Configuration:\n";
echo "   Name: {$monitoringAgent->getName()}\n";
echo "   Check Interval: 5 seconds\n";
echo "   Thresholds configured: cpu(80%), memory(85%), disk(90%), latency(100ms)\n\n";

// Example 1: Single metrics check
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Single Metrics Check\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$task1 = <<<METRICS
cpu_usage: 45.5
memory_usage: 62.3
disk_usage: 75.0
network_latency: 23
METRICS;

echo "📝 Analyzing normal metrics...\n\n";
$result1 = $monitoringAgent->run($task1);

if ($result1->isSuccess()) {
    echo $result1->getAnswer() . "\n\n";
    
    $metadata = $result1->getMetadata();
    echo "📊 Analysis Summary:\n";
    echo "   Metrics analyzed: {$metadata['metrics_analyzed']}\n";
    echo "   Alerts generated: {$metadata['alerts_generated']}\n\n";
}

sleep(1);

// Example 2: Threshold exceeded
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Threshold Alerts\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$task2 = <<<METRICS
cpu_usage: 92.5
memory_usage: 88.3
disk_usage: 65.0
network_latency: 125
METRICS;

echo "⚠️  Analyzing metrics with threshold violations...\n\n";
$result2 = $monitoringAgent->run($task2);

if ($result2->isSuccess()) {
    echo $result2->getAnswer() . "\n\n";
    
    $metadata = $result2->getMetadata();
    echo "📊 Analysis Summary:\n";
    echo "   Metrics analyzed: {$metadata['metrics_analyzed']}\n";
    echo "   Alerts generated: {$metadata['alerts_generated']}\n";
    
    if (!empty($metadata['alerts'])) {
        echo "\n🚨 Alerts Details:\n";
        foreach ($metadata['alerts'] as $i => $alert) {
            echo "   " . ($i + 1) . ". [{$alert['severity']}] {$alert['title']}\n";
            echo "      {$alert['message']}\n";
        }
    }
}

sleep(1);

// Example 3: Continuous monitoring (limited demo)
echo "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Continuous Monitoring (3 cycles)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create a system metrics source
$systemMetrics = new SystemMetrics('production-server-01');

echo "🔄 Starting continuous monitoring...\n";
echo "   Press Ctrl+C to stop (demo will auto-stop after 3 cycles)\n\n";

$alertCount = 0;
$checkCount = 0;
$maxChecks = 3;

// Manual monitoring loop for demo purposes
while ($checkCount < $maxChecks) {
    $checkCount++;
    echo "📊 Check #{$checkCount} - " . date('H:i:s') . "\n";
    
    $metrics = $systemMetrics->getMetrics();
    
    $metricLines = [];
    foreach ($metrics as $metric) {
        $metricLines[] = "{$metric->getName()}: {$metric->getValue()}";
        echo "   {$metric->getName()}: {$metric->getValue()}\n";
    }
    
    $task = implode("\n", $metricLines);
    $result = $monitoringAgent->run($task);
    
    if ($result->isSuccess()) {
        $metadata = $result->getMetadata();
        if ($metadata['alerts_generated'] > 0) {
            $alertCount += $metadata['alerts_generated'];
            echo "   🚨 {$metadata['alerts_generated']} alert(s) generated\n";
        } else {
            echo "   ✅ All metrics within normal range\n";
        }
    }
    
    echo "\n";
    
    if ($checkCount < $maxChecks) {
        sleep(2); // Short sleep for demo
    }
}

echo "⏹️  Monitoring stopped\n";
echo "📊 Final Statistics:\n";
echo "   Total checks: {$checkCount}\n";
echo "   Total alerts: {$alertCount}\n\n";

// Example 4: Simulated issue detection
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Issue Detection Simulation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$problemSystem = new SystemMetrics('problem-server-02', simulateIssue: true);

echo "🔍 Checking system with known issues...\n\n";

$metrics = $problemSystem->getMetrics();
$metricLines = [];
foreach ($metrics as $metric) {
    $metricLines[] = "{$metric->getName()}: {$metric->getValue()}";
}

$task = implode("\n", $metricLines);
$result = $monitoringAgent->run($task);

if ($result->isSuccess()) {
    echo $result->getAnswer() . "\n\n";
    
    $metadata = $result->getMetadata();
    
    if ($metadata['alerts_generated'] > 0) {
        echo "🚨 CRITICAL ISSUES DETECTED!\n\n";
        echo "Issues found:\n";
        foreach ($metadata['alerts'] as $i => $alert) {
            echo "   " . ($i + 1) . ". {$alert['title']}\n";
            echo "      Severity: {$alert['severity']}\n";
            echo "      Details: {$alert['message']}\n\n";
        }
        
        echo "💡 Recommended Actions:\n";
        echo "   1. Investigate high resource usage\n";
        echo "   2. Check for runaway processes\n";
        echo "   3. Review application logs\n";
        echo "   4. Consider scaling resources\n";
    }
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "✨ Monitoring Agent example completed!\n";
echo str_repeat("═", 80) . "\n";

