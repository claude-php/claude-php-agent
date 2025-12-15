#!/usr/bin/env php
<?php
/**
 * Advanced Monitoring Agent Example
 *
 * Demonstrates advanced features:
 * - Integration with AlertAgent for notifications
 * - Custom monitorable data sources
 * - Historical data analysis
 * - Multi-source monitoring
 * - Pattern detection and anomaly alerting
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;
use ClaudeAgents\Contracts\MonitorableInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Advanced console logger with color support
class ColorLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('H:i:s');
        $color = match ($level) {
            'error' => "\033[31m",     // Red
            'warning' => "\033[33m",   // Yellow
            'info' => "\033[36m",      // Cyan
            'debug' => "\033[90m",     // Gray
            default => "\033[0m",      // Default
        };
        $reset = "\033[0m";
        
        $emoji = match ($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            'debug' => '🔍',
            default => '📝',
        };
        
        echo "{$color}[{$timestamp}] {$emoji} [{$level}] {$message}{$reset}\n";
    }
}

// Database metrics source
class DatabaseMetrics implements MonitorableInterface
{
    private int $queryCount = 0;
    private array $history = [];
    
    public function __construct(
        private string $name = 'database',
        private bool $simulateLoad = false
    ) {}

    public function getMetrics(): array
    {
        $this->queryCount++;
        
        // Simulate database metrics with realistic patterns
        $baseConnections = $this->simulateLoad ? 450 : 100;
        $baseQueryTime = $this->simulateLoad ? 250 : 50;
        
        $connections = $baseConnections + rand(-20, 20);
        $queryTime = $baseQueryTime + rand(-10, 10);
        $lockWaitTime = $this->simulateLoad ? rand(10, 50) : rand(0, 5);
        $cacheHitRate = $this->simulateLoad ? rand(75, 85) : rand(92, 98);
        $activeTransactions = $this->simulateLoad ? rand(50, 100) : rand(5, 20);
        
        $metrics = [
            new Metric('db_connections', $connections, [
                'max' => 500,
                'host' => $this->name,
            ]),
            new Metric('query_time_ms', $queryTime, [
                'type' => 'average',
                'host' => $this->name,
            ]),
            new Metric('lock_wait_time_ms', $lockWaitTime, [
                'host' => $this->name,
            ]),
            new Metric('cache_hit_rate', $cacheHitRate, [
                'unit' => 'percent',
                'host' => $this->name,
            ]),
            new Metric('active_transactions', $activeTransactions, [
                'host' => $this->name,
            ]),
        ];
        
        $this->history[] = $metrics;
        
        return $metrics;
    }

    public function getName(): string
    {
        return $this->name;
    }
    
    public function getHistory(): array
    {
        return $this->history;
    }
}

// Application metrics source
class ApplicationMetrics implements MonitorableInterface
{
    public function __construct(
        private string $name = 'application',
        private bool $simulateErrors = false
    ) {}

    public function getMetrics(): array
    {
        $baseRps = $this->simulateErrors ? 800 : 1200;
        $baseErrorRate = $this->simulateErrors ? 5.0 : 0.5;
        
        return [
            new Metric('requests_per_second', $baseRps + rand(-50, 50), [
                'endpoint' => '/api/*',
                'app' => $this->name,
            ]),
            new Metric('error_rate', $baseErrorRate + (rand(0, 10) / 10), [
                'unit' => 'percent',
                'app' => $this->name,
            ]),
            new Metric('response_time_p95', rand(100, 300), [
                'unit' => 'ms',
                'percentile' => 95,
                'app' => $this->name,
            ]),
            new Metric('active_sessions', rand(500, 2000), [
                'app' => $this->name,
            ]),
            new Metric('queue_depth', $this->simulateErrors ? rand(500, 1000) : rand(0, 50), [
                'queue' => 'background_jobs',
                'app' => $this->name,
            ]),
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }
}

// Infrastructure metrics source
class InfrastructureMetrics implements MonitorableInterface
{
    public function __construct(
        private string $name = 'infrastructure',
        private bool $degraded = false
    ) {}

    public function getMetrics(): array
    {
        return [
            new Metric('cpu_usage', $this->degraded ? rand(80, 95) : rand(20, 60), [
                'unit' => 'percent',
                'cluster' => $this->name,
            ]),
            new Metric('memory_usage', $this->degraded ? rand(85, 95) : rand(40, 70), [
                'unit' => 'percent',
                'cluster' => $this->name,
            ]),
            new Metric('disk_io_wait', $this->degraded ? rand(15, 30) : rand(1, 5), [
                'unit' => 'percent',
                'cluster' => $this->name,
            ]),
            new Metric('network_throughput', rand(100, 500), [
                'unit' => 'mbps',
                'cluster' => $this->name,
            ]),
            new Metric('pod_count', rand(10, 50), [
                'status' => 'running',
                'cluster' => $this->name,
            ]),
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

echo "\033[1m╔════════════════════════════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1m║                  Advanced Monitoring Agent Example                        ║\033[0m\n";
echo "\033[1m╚════════════════════════════════════════════════════════════════════════════╝\033[0m\n\n";

$logger = new ColorLogger();

// Create monitoring agent with comprehensive thresholds
$monitoringAgent = new MonitoringAgent($client, [
    'name' => 'production_monitor',
    'check_interval' => 10,
    'thresholds' => [
        // Database thresholds
        'db_connections' => 450,
        'query_time_ms' => 200,
        'lock_wait_time_ms' => 20,
        'cache_hit_rate' => 90,
        
        // Application thresholds
        'error_rate' => 2.0,
        'response_time_p95' => 250,
        'queue_depth' => 100,
        
        // Infrastructure thresholds
        'cpu_usage' => 75,
        'memory_usage' => 80,
        'disk_io_wait' => 10,
    ],
    'logger' => $logger,
]);

// Create alert agent for notifications
$alertAgent = new AlertAgent($client, [
    'name' => 'notification_agent',
    'logger' => $logger,
]);

// Register alert channel
$alertAgent->registerChannel('console', function (Alert $alert, string $message) {
    $emoji = match ($alert->getSeverity()) {
        Alert::SEVERITY_CRITICAL => '🔴',
        Alert::SEVERITY_ERROR => '🟠',
        Alert::SEVERITY_WARNING => '🟡',
        Alert::SEVERITY_INFO => '🔵',
        default => '⚪',
    };

    echo "\n\033[1m" . str_repeat("━", 80) . "\033[0m\n";
    echo "{$emoji} \033[1mALERT: {$alert->getTitle()}\033[0m\n";
    echo str_repeat("━", 80) . "\n";
    echo "Severity: \033[1m" . strtoupper($alert->getSeverity()) . "\033[0m\n";
    
    if ($alert->getMetric()) {
        echo "Metric: {$alert->getMetric()->getName()} = {$alert->getMetric()->getValue()}\n";
    }
    
    echo "\n{$message}\n";
    echo str_repeat("━", 80) . "\n\n";
});

echo "🚀 Starting Advanced Monitoring System\n\n";

// Initialize data sources
$dbMetrics = new DatabaseMetrics('prod-db-primary');
$appMetrics = new ApplicationMetrics('web-app');
$infraMetrics = new InfrastructureMetrics('k8s-prod-cluster');

echo "📊 Monitoring Sources:\n";
echo "   • {$dbMetrics->getName()}\n";
echo "   • {$appMetrics->getName()}\n";
echo "   • {$infraMetrics->getName()}\n\n";

// Example 1: Normal operation monitoring
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Normal Operation Baseline\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$sources = [$dbMetrics, $appMetrics, $infraMetrics];

foreach ($sources as $source) {
    echo "📊 Checking {$source->getName()}...\n";
    
    $metrics = $source->getMetrics();
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
            echo "   ⚠️  {$metadata['alerts_generated']} alert(s) detected\n";
        } else {
            echo "   ✅ All metrics normal\n";
        }
    }
    echo "\n";
}

sleep(1);

// Example 2: Degraded database performance
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Database Performance Degradation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$stressedDb = new DatabaseMetrics('prod-db-primary', simulateLoad: true);

echo "🔍 Simulating high database load...\n\n";

$metrics = $stressedDb->getMetrics();
$metricLines = [];

foreach ($metrics as $metric) {
    $metricLines[] = "{$metric->getName()}: {$metric->getValue()}";
}

$task = implode("\n", $metricLines);
$result = $monitoringAgent->run($task);

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    echo $result->getAnswer() . "\n\n";
    
    if ($metadata['alerts_generated'] > 0) {
        echo "🚨 ALERTS TRIGGERED:\n\n";
        
        foreach ($metadata['alerts'] as $alertData) {
            $alert = new Alert(
                title: $alertData['title'],
                message: $alertData['message'],
                severity: $alertData['severity']
            );
            
            $alertAgent->processAlert($alert);
        }
    }
}

sleep(1);

// Example 3: Application issues
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Application Error Spike\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$errorApp = new ApplicationMetrics('web-app', simulateErrors: true);

echo "🔍 Simulating application errors...\n\n";

$metrics = $errorApp->getMetrics();
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
        foreach ($metadata['alerts'] as $alertData) {
            $alert = new Alert(
                title: $alertData['title'],
                message: $alertData['message'],
                severity: Alert::SEVERITY_ERROR
            );
            
            $alertAgent->processAlert($alert);
        }
    }
}

sleep(1);

// Example 4: Multi-source correlation
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Multi-Source Monitoring & Correlation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Simulate a cascading failure
$degradedInfra = new InfrastructureMetrics('k8s-prod-cluster', degraded: true);
$stressedApp = new ApplicationMetrics('web-app', simulateErrors: true);
$overloadedDb = new DatabaseMetrics('prod-db-primary', simulateLoad: true);

echo "🔥 Simulating cascading system issues...\n\n";

$allSources = [
    'Infrastructure' => $degradedInfra,
    'Application' => $stressedApp,
    'Database' => $overloadedDb,
];

$totalAlerts = 0;

foreach ($allSources as $type => $source) {
    echo "📊 Analyzing {$type} ({$source->getName()})...\n";
    
    $metrics = $source->getMetrics();
    $metricLines = [];
    
    foreach ($metrics as $metric) {
        $metricLines[] = "{$metric->getName()}: {$metric->getValue()}";
    }
    
    $task = implode("\n", $metricLines);
    $result = $monitoringAgent->run($task);
    
    if ($result->isSuccess()) {
        $metadata = $result->getMetadata();
        $alertCount = $metadata['alerts_generated'];
        $totalAlerts += $alertCount;
        
        if ($alertCount > 0) {
            echo "   🚨 {$alertCount} alert(s)\n";
        } else {
            echo "   ✅ Normal\n";
        }
    }
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🚨 INCIDENT SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Total Alerts: {$totalAlerts}\n";
echo "Affected Systems: " . count($allSources) . "\n\n";

echo "📋 Correlation Analysis:\n";
echo "   • Infrastructure degradation detected\n";
echo "   • Application performance impacted\n";
echo "   • Database under heavy load\n\n";

echo "💡 Recommended Actions:\n";
echo "   1. Scale infrastructure resources (CPU/Memory)\n";
echo "   2. Enable application rate limiting\n";
echo "   3. Optimize slow database queries\n";
echo "   4. Review application logs for errors\n";
echo "   5. Consider failing over to backup systems\n\n";

// Example 5: Historical trend analysis
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Historical Trend Analysis\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📈 Collecting baseline metrics over time...\n\n";

$trendDb = new DatabaseMetrics('prod-db-replica');

for ($i = 1; $i <= 5; $i++) {
    echo "Sample {$i}/5... ";
    $trendDb->getMetrics();
    echo "✓\n";
    usleep(200000); // 0.2 seconds
}

$history = $trendDb->getHistory();
echo "\n📊 Collected {" . count($history) . "} samples\n";

// Analyze trends
echo "\n🔍 Trend Analysis:\n";
$firstSample = $history[0];
$lastSample = $history[count($history) - 1];

foreach ($firstSample as $i => $metric) {
    $first = $metric->getValue();
    $last = $lastSample[$i]->getValue();
    $change = $last - $first;
    $trend = $change > 0 ? "📈" : ($change < 0 ? "📉" : "➡️");
    
    echo "   {$trend} {$metric->getName()}: {$first} → {$last}";
    if (abs($change) > 0) {
        $changeStr = $change > 0 ? "+{$change}" : "{$change}";
        echo " ({$changeStr})";
    }
    echo "\n";
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "✨ Advanced Monitoring example completed!\n";
echo "   • Multi-source monitoring demonstrated\n";
echo "   • Threshold-based alerting configured\n";
echo "   • Alert integration with notification system\n";
echo "   • Trend analysis performed\n";
echo str_repeat("═", 80) . "\n";

