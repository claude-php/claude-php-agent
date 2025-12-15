# MonitoringAgent Tutorial: Building an Intelligent Monitoring System

## Introduction

This tutorial will guide you through building a production-ready monitoring system using the MonitoringAgent. We'll start with basic metric analysis and progress to advanced patterns like anomaly detection, multi-source monitoring, and integration with alerting systems.

By the end of this tutorial, you'll be able to:

- Create and analyze metrics from various sources
- Configure threshold-based alerting
- Implement continuous monitoring
- Use LLM-powered anomaly detection
- Build custom monitorable data sources
- Create a complete monitoring solution

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and system monitoring concepts

## Table of Contents

1. [Getting Started](#getting-started)
2. [Understanding Metrics](#understanding-metrics)
3. [Basic Monitoring with Thresholds](#basic-monitoring-with-thresholds)
4. [Continuous Monitoring](#continuous-monitoring)
5. [Custom Data Sources](#custom-data-sources)
6. [LLM-Powered Anomaly Detection](#llm-powered-anomaly-detection)
7. [Multi-Source Monitoring](#multi-source-monitoring)
8. [Integration with AlertAgent](#integration-with-alertagent)
9. [Production Best Practices](#production-best-practices)

## Getting Started

### Installation

First, ensure you have the claude-php-agent package installed:

```bash
composer require your-org/claude-php-agent
```

### Basic Setup

Create a simple script to test the MonitoringAgent:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Monitoring\Metric;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create the monitoring agent
$monitoringAgent = new MonitoringAgent($client, [
    'name' => 'tutorial_monitor',
]);

echo "Monitoring agent ready!\n";
```

## Understanding Metrics

### What is a Metric?

A metric is a measurable value that represents some aspect of your system's state or performance. Examples include CPU usage, response time, error rate, etc.

### Creating Your First Metric

```php
use ClaudeAgents\Monitoring\Metric;

// Basic metric
$cpuMetric = new Metric('cpu_usage', 75.5);

echo "Metric: {$cpuMetric->getName()}\n";
echo "Value: {$cpuMetric->getValue()}\n";
echo "Timestamp: {$cpuMetric->getTimestamp()}\n";
```

### Adding Metadata to Metrics

Metadata provides context about where and how the metric was collected:

```php
$cpuMetric = new Metric('cpu_usage', 75.5, [
    'host' => 'web-server-01',
    'region' => 'us-east-1',
    'environment' => 'production',
]);

print_r($cpuMetric->getMetadata());
```

### Metric Types

```php
// Percentage metrics
$cpuUsage = new Metric('cpu_usage', 85.5, ['unit' => 'percent']);
$memoryUsage = new Metric('memory_usage', 72.3, ['unit' => 'percent']);

// Time-based metrics
$responseTime = new Metric('response_time', 250, ['unit' => 'ms']);
$uptime = new Metric('uptime', 86400, ['unit' => 'seconds']);

// Count metrics
$activeUsers = new Metric('active_users', 1543);
$errorCount = new Metric('errors', 12, ['timeframe' => '5m']);

// String metrics (status indicators)
$status = new Metric('database_status', 'healthy');
```

## Basic Monitoring with Thresholds

### Configuring Thresholds

Thresholds define acceptable limits for your metrics:

```php
$monitoringAgent = new MonitoringAgent($client, [
    'name' => 'system_monitor',
    'thresholds' => [
        'cpu_usage' => 80,        // Alert if CPU > 80%
        'memory_usage' => 90,     // Alert if memory > 90%
        'disk_usage' => 85,       // Alert if disk > 85%
        'response_time' => 500,   // Alert if response > 500ms
        'error_rate' => 2.0,      // Alert if errors > 2%
    ],
]);
```

### Analyzing Metrics

```php
// Format metrics as "name: value" pairs
$task = <<<METRICS
cpu_usage: 45.5
memory_usage: 62.3
disk_usage: 75.0
response_time: 250
error_rate: 0.5
METRICS;

$result = $monitoringAgent->run($task);

if ($result->isSuccess()) {
    echo $result->getAnswer() . "\n";
    
    $metadata = $result->getMetadata();
    echo "\nMetrics Analyzed: {$metadata['metrics_analyzed']}\n";
    echo "Alerts Generated: {$metadata['alerts_generated']}\n";
}
```

**Output:**
```
Monitoring Analysis
==================

Metrics Analyzed: 5
  - cpu_usage: 45.5
  - memory_usage: 62.3
  - disk_usage: 75
  - response_time: 250
  - error_rate: 0.5

Alerts Generated: 0

Metrics Analyzed: 5
Alerts Generated: 0
```

### Triggering Threshold Alerts

```php
$task = <<<METRICS
cpu_usage: 92.5
memory_usage: 88.3
disk_usage: 65.0
response_time: 750
error_rate: 3.5
METRICS;

$result = $monitoringAgent->run($task);

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    if ($metadata['alerts_generated'] > 0) {
        echo "🚨 ALERTS DETECTED!\n\n";
        
        foreach ($metadata['alerts'] as $alert) {
            echo "[{$alert['severity']}] {$alert['title']}\n";
            echo "  {$alert['message']}\n\n";
        }
    }
}
```

**Output:**
```
🚨 ALERTS DETECTED!

[warning] cpu_usage exceeds threshold
  Metric 'cpu_usage' value 92.5 exceeds threshold 80

[warning] memory_usage exceeds threshold
  Metric 'memory_usage' value 88.3 exceeds threshold 85

[warning] response_time exceeds threshold
  Metric 'response_time' value 750 exceeds threshold 500

[warning] error_rate exceeds threshold
  Metric 'error_rate' value 3.5 exceeds threshold 2
```

## Continuous Monitoring

### Creating a Monitorable Data Source

To enable continuous monitoring, implement the `MonitorableInterface`:

```php
use ClaudeAgents\Contracts\MonitorableInterface;
use ClaudeAgents\Monitoring\Metric;

class SystemMonitor implements MonitorableInterface
{
    public function getMetrics(): array
    {
        return [
            new Metric('cpu_usage', $this->getCpuUsage()),
            new Metric('memory_usage', $this->getMemoryUsage()),
            new Metric('disk_usage', $this->getDiskUsage()),
        ];
    }
    
    public function getName(): string
    {
        return 'system';
    }
    
    private function getCpuUsage(): float
    {
        // Real implementation would read from system
        // For demo, we'll use a placeholder
        $load = sys_getloadavg()[0];
        $cores = 4; // Get actual core count
        return min(100, ($load / $cores) * 100);
    }
    
    private function getMemoryUsage(): float
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $memInfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $available);
            
            $totalMem = $total[1];
            $availableMem = $available[1];
            $usedMem = $totalMem - $availableMem;
            
            return ($usedMem / $totalMem) * 100;
        }
        
        return 0.0; // Fallback
    }
    
    private function getDiskUsage(): float
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        
        return ($used / $total) * 100;
    }
}
```

### Starting Continuous Monitoring

```php
$systemMonitor = new SystemMonitor();
$alerts = [];

// Start monitoring (this will run indefinitely)
$monitoringAgent->watch($systemMonitor, function ($alert) use (&$alerts) {
    $alerts[] = $alert;
    
    echo "\n🚨 ALERT: {$alert->getTitle()}\n";
    echo "Severity: {$alert->getSeverity()}\n";
    echo "Message: {$alert->getMessage()}\n";
    
    // Send notification, log, etc.
    error_log("[{$alert->getSeverity()}] {$alert->getTitle()}");
});

// To stop monitoring (call from signal handler or separate thread)
// $monitoringAgent->stop();
```

### Background Monitoring Service

Create a long-running service:

```php
#!/usr/bin/env php
<?php
// monitor-service.php

require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MonitoringAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$monitoringAgent = new MonitoringAgent($client, [
    'check_interval' => 60, // Check every 60 seconds
    'thresholds' => [
        'cpu_usage' => 80,
        'memory_usage' => 90,
    ],
]);

// Handle termination signals
pcntl_signal(SIGTERM, function () use ($monitoringAgent) {
    echo "Stopping monitoring service...\n";
    $monitoringAgent->stop();
    exit(0);
});

pcntl_signal(SIGINT, function () use ($monitoringAgent) {
    echo "Stopping monitoring service...\n";
    $monitoringAgent->stop();
    exit(0);
});

echo "Starting monitoring service...\n";

$systemMonitor = new SystemMonitor();
$monitoringAgent->watch($systemMonitor, function ($alert) {
    // Handle alerts
    file_put_contents(
        '/var/log/monitoring-alerts.log',
        date('Y-m-d H:i:s') . " [{$alert->getSeverity()}] {$alert->getTitle()}\n",
        FILE_APPEND
    );
});
```

Run the service:

```bash
php monitor-service.php &
```

## Custom Data Sources

### Database Monitoring

```php
class DatabaseMonitor implements MonitorableInterface
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function getMetrics(): array
    {
        $metrics = [];
        
        // Connection count
        $stmt = $this->pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $connections = $stmt->fetchColumn(1);
        $metrics[] = new Metric('db_connections', (int)$connections);
        
        // Slow queries
        $stmt = $this->pdo->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
        $slowQueries = $stmt->fetchColumn(1);
        $metrics[] = new Metric('slow_queries', (int)$slowQueries);
        
        // Query cache hit rate
        $stmt = $this->pdo->query("SHOW STATUS LIKE 'Qcache_hits'");
        $hits = (int)$stmt->fetchColumn(1);
        
        $stmt = $this->pdo->query("SHOW STATUS LIKE 'Qcache_inserts'");
        $inserts = (int)$stmt->fetchColumn(1);
        
        $total = $hits + $inserts;
        $hitRate = $total > 0 ? ($hits / $total) * 100 : 0;
        $metrics[] = new Metric('cache_hit_rate', $hitRate, ['unit' => 'percent']);
        
        return $metrics;
    }
    
    public function getName(): string
    {
        return 'database';
    }
}

// Usage
$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'pass');
$dbMonitor = new DatabaseMonitor($pdo);

$monitoringAgent = new MonitoringAgent($client, [
    'thresholds' => [
        'db_connections' => 100,
        'slow_queries' => 10,
        'cache_hit_rate' => 80, // Alert if below 80%
    ],
]);

$monitoringAgent->watch($dbMonitor, function ($alert) {
    echo "Database alert: {$alert->getTitle()}\n";
});
```

### Application Performance Monitoring

```php
class ApplicationMonitor implements MonitorableInterface
{
    private string $logFile;
    
    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }
    
    public function getMetrics(): array
    {
        $metrics = [];
        
        // Parse last 5 minutes of logs
        $logs = $this->parseRecentLogs(300);
        
        // Request rate
        $requestCount = count($logs);
        $rps = $requestCount / 300; // Requests per second
        $metrics[] = new Metric('requests_per_second', $rps);
        
        // Error rate
        $errors = array_filter($logs, fn($log) => $log['status'] >= 500);
        $errorRate = $requestCount > 0 ? (count($errors) / $requestCount) * 100 : 0;
        $metrics[] = new Metric('error_rate', $errorRate, ['unit' => 'percent']);
        
        // Response time (95th percentile)
        $times = array_column($logs, 'response_time');
        sort($times);
        $p95Index = (int)(count($times) * 0.95);
        $p95 = $times[$p95Index] ?? 0;
        $metrics[] = new Metric('response_time_p95', $p95, [
            'unit' => 'ms',
            'percentile' => 95,
        ]);
        
        return $metrics;
    }
    
    public function getName(): string
    {
        return 'application';
    }
    
    private function parseRecentLogs(int $seconds): array
    {
        $cutoff = time() - $seconds;
        $logs = [];
        
        $fp = fopen($this->logFile, 'r');
        if (!$fp) {
            return [];
        }
        
        while (($line = fgets($fp)) !== false) {
            // Parse log line (adjust format as needed)
            if (preg_match('/\[(\d+)\] (\d+) (\d+)ms/', $line, $matches)) {
                $timestamp = (int)$matches[1];
                if ($timestamp >= $cutoff) {
                    $logs[] = [
                        'timestamp' => $timestamp,
                        'status' => (int)$matches[2],
                        'response_time' => (int)$matches[3],
                    ];
                }
            }
        }
        
        fclose($fp);
        return $logs;
    }
}
```

### Redis Monitoring

```php
class RedisMonitor implements MonitorableInterface
{
    private Redis $redis;
    
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }
    
    public function getMetrics(): array
    {
        $info = $this->redis->info();
        
        return [
            new Metric('redis_connections', (int)$info['connected_clients']),
            new Metric('redis_memory_used', (int)$info['used_memory'], [
                'unit' => 'bytes',
            ]),
            new Metric('redis_memory_usage', 
                ((int)$info['used_memory'] / (int)$info['maxmemory']) * 100,
                ['unit' => 'percent']
            ),
            new Metric('redis_ops_per_sec', (int)$info['instantaneous_ops_per_sec']),
            new Metric('redis_hit_rate',
                $this->calculateHitRate($info),
                ['unit' => 'percent']
            ),
        ];
    }
    
    public function getName(): string
    {
        return 'redis';
    }
    
    private function calculateHitRate(array $info): float
    {
        $hits = (int)($info['keyspace_hits'] ?? 0);
        $misses = (int)($info['keyspace_misses'] ?? 0);
        $total = $hits + $misses;
        
        return $total > 0 ? ($hits / $total) * 100 : 0;
    }
}
```

## LLM-Powered Anomaly Detection

### How It Works

The MonitoringAgent uses Claude AI to detect anomalies by:

1. Building a history of metric values (requires 5+ data points)
2. Analyzing current values against historical patterns
3. Identifying deviations like sudden spikes, drops, or unusual trends

### Building History

```php
$monitoringAgent = new MonitoringAgent($client, [
    'thresholds' => ['cpu_usage' => 80],
]);

// Collect baseline data
echo "Building baseline...\n";
for ($i = 0; $i < 10; $i++) {
    $cpuUsage = rand(40, 60); // Normal range
    $result = $monitoringAgent->run("cpu_usage: {$cpuUsage}");
    echo "Sample {$i}: CPU {$cpuUsage}%\n";
    sleep(5);
}

echo "Baseline established!\n";
```

### Detecting Anomalies

```php
// Now send an anomalous value
$anomalousCpu = 95; // Sudden spike
$result = $monitoringAgent->run("cpu_usage: {$anomalousCpu}");

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    foreach ($metadata['alerts'] as $alert) {
        if (strpos($alert['title'], 'Anomaly') !== false) {
            echo "🔍 ANOMALY DETECTED!\n";
            echo "Title: {$alert['title']}\n";
            echo "Analysis: {$alert['message']}\n";
        }
    }
}
```

### Anomaly Detection Example

```php
class AnomalyDetectionDemo
{
    private MonitoringAgent $agent;
    private array $normalRange = [40, 60];
    
    public function __construct(MonitoringAgent $agent)
    {
        $this->agent = $agent;
    }
    
    public function run(): void
    {
        // Phase 1: Build normal baseline
        echo "Phase 1: Building baseline (20 samples)...\n";
        $this->collectBaseline(20);
        
        // Phase 2: Normal operation
        echo "\nPhase 2: Normal operation (5 samples)...\n";
        $this->simulateNormalOperation(5);
        
        // Phase 3: Introduce anomaly
        echo "\nPhase 3: Introducing anomaly...\n";
        $this->simulateAnomaly();
        
        // Phase 4: Return to normal
        echo "\nPhase 4: Returning to normal...\n";
        $this->simulateNormalOperation(3);
    }
    
    private function collectBaseline(int $samples): void
    {
        for ($i = 0; $i < $samples; $i++) {
            $value = rand($this->normalRange[0], $this->normalRange[1]);
            $this->agent->run("metric_value: {$value}");
            echo "  Sample {$i}: {$value}\n";
            usleep(100000); // 0.1 seconds
        }
    }
    
    private function simulateNormalOperation(int $samples): void
    {
        for ($i = 0; $i < $samples; $i++) {
            $value = rand($this->normalRange[0], $this->normalRange[1]);
            $result = $this->agent->run("metric_value: {$value}");
            
            $metadata = $result->getMetadata();
            $status = $metadata['alerts_generated'] > 0 ? "⚠️" : "✅";
            echo "  {$status} Value: {$value}\n";
            
            sleep(1);
        }
    }
    
    private function simulateAnomaly(): void
    {
        // Sudden spike
        $anomalousValue = 150;
        $result = $this->agent->run("metric_value: {$anomalousValue}");
        
        $metadata = $result->getMetadata();
        if ($metadata['alerts_generated'] > 0) {
            echo "  🚨 ANOMALY: Value {$anomalousValue}\n";
            foreach ($metadata['alerts'] as $alert) {
                echo "     {$alert['title']}\n";
            }
        }
    }
}

// Run demo
$demo = new AnomalyDetectionDemo($monitoringAgent);
$demo->run();
```

## Multi-Source Monitoring

### Monitoring Multiple Systems

```php
class MultiSourceMonitor
{
    private MonitoringAgent $agent;
    private array $sources = [];
    
    public function __construct(MonitoringAgent $agent)
    {
        $this->agent = $agent;
    }
    
    public function addSource(MonitorableInterface $source): void
    {
        $this->sources[] = $source;
    }
    
    public function checkAll(): array
    {
        $results = [];
        
        foreach ($this->sources as $source) {
            $metrics = $source->getMetrics();
            $metricLines = array_map(
                fn($m) => "{$m->getName()}: {$m->getValue()}",
                $metrics
            );
            
            $task = implode("\n", $metricLines);
            $result = $this->agent->run($task);
            
            $results[$source->getName()] = $result;
        }
        
        return $results;
    }
    
    public function generateReport(): string
    {
        $results = $this->checkAll();
        $report = "Multi-Source Monitoring Report\n";
        $report .= str_repeat("=", 50) . "\n\n";
        
        $totalAlerts = 0;
        
        foreach ($results as $sourceName => $result) {
            if ($result->isSuccess()) {
                $metadata = $result->getMetadata();
                $alertCount = $metadata['alerts_generated'];
                $totalAlerts += $alertCount;
                
                $status = $alertCount > 0 ? "🔴" : "🟢";
                $report .= "{$status} {$sourceName}\n";
                $report .= "  Metrics: {$metadata['metrics_analyzed']}\n";
                $report .= "  Alerts: {$alertCount}\n\n";
            }
        }
        
        $report .= str_repeat("=", 50) . "\n";
        $report .= "Total Alerts: {$totalAlerts}\n";
        
        return $report;
    }
}

// Usage
$multiMonitor = new MultiSourceMonitor($monitoringAgent);
$multiMonitor->addSource(new SystemMonitor());
$multiMonitor->addSource(new DatabaseMonitor($pdo));
$multiMonitor->addSource(new ApplicationMonitor('/var/log/app.log'));
$multiMonitor->addSource(new RedisMonitor($redis));

echo $multiMonitor->generateReport();
```

## Integration with AlertAgent

### Combined Monitoring and Alerting

```php
use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Alert;

class MonitoringAlertSystem
{
    private MonitoringAgent $monitoringAgent;
    private AlertAgent $alertAgent;
    
    public function __construct(ClaudePhp $client)
    {
        $this->monitoringAgent = new MonitoringAgent($client, [
            'thresholds' => [
                'cpu_usage' => 80,
                'memory_usage' => 90,
                'error_rate' => 2.0,
            ],
        ]);
        
        $this->alertAgent = new AlertAgent($client);
        $this->setupAlertChannels();
    }
    
    private function setupAlertChannels(): void
    {
        // Console output
        $this->alertAgent->registerChannel('console', function ($alert, $message) {
            echo "\n🚨 [{$alert->getSeverity()}] {$alert->getTitle()}\n";
            echo $message . "\n";
        });
        
        // Log file
        $this->alertAgent->registerChannel('logfile', function ($alert, $message) {
            $entry = sprintf(
                "[%s] [%s] %s\n",
                date('Y-m-d H:i:s'),
                $alert->getSeverity(),
                $alert->getTitle()
            );
            file_put_contents('/var/log/monitoring.log', $entry, FILE_APPEND);
        });
        
        // Email for critical alerts
        $this->alertAgent->registerChannel('email', function ($alert, $message) {
            if ($alert->isCritical()) {
                mail(
                    'ops@example.com',
                    "CRITICAL: {$alert->getTitle()}",
                    $message
                );
            }
        });
    }
    
    public function monitor(MonitorableInterface $source): void
    {
        $metrics = $source->getMetrics();
        $metricLines = array_map(
            fn($m) => "{$m->getName()}: {$m->getValue()}",
            $metrics
        );
        
        $task = implode("\n", $metricLines);
        $result = $this->monitoringAgent->run($task);
        
        if ($result->isSuccess()) {
            $metadata = $result->getMetadata();
            
            foreach ($metadata['alerts'] as $alertData) {
                $alert = new Alert(
                    title: $alertData['title'],
                    message: $alertData['message'],
                    severity: $alertData['severity'],
                    metric: $alertData['metric'] ?? null
                );
                
                $this->alertAgent->processAlert($alert);
            }
        }
    }
    
    public function startContinuousMonitoring(array $sources): void
    {
        while (true) {
            foreach ($sources as $source) {
                $this->monitor($source);
            }
            
            sleep(60); // Check every minute
        }
    }
}

// Usage
$system = new MonitoringAlertSystem($client);
$system->startContinuousMonitoring([
    new SystemMonitor(),
    new DatabaseMonitor($pdo),
    new ApplicationMonitor('/var/log/app.log'),
]);
```

## Production Best Practices

### 1. Proper Threshold Configuration

Set thresholds based on actual system capacity and historical data:

```php
// ❌ Bad: Arbitrary thresholds
$monitoringAgent = new MonitoringAgent($client, [
    'thresholds' => [
        'cpu_usage' => 50, // Too low, many false positives
    ],
]);

// ✅ Good: Based on capacity and testing
$monitoringAgent = new MonitoringAgent($client, [
    'thresholds' => [
        'cpu_usage' => 80,      // 80% of 8 cores
        'memory_usage' => 85,   // 85% of 32GB
        'disk_usage' => 90,     // 90% of 500GB
    ],
]);
```

### 2. Structured Logging

Use proper logging for debugging and auditing:

```php
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('monitoring');
$logger->pushHandler(new StreamHandler('/var/log/monitoring.log', Logger::INFO));

$monitoringAgent = new MonitoringAgent($client, [
    'logger' => $logger,
]);
```

### 3. Error Handling

Always handle failures gracefully:

```php
try {
    $result = $monitoringAgent->run($task);
    
    if ($result->isSuccess()) {
        // Process result
    } else {
        $logger->error("Monitoring failed: {$result->getError()}");
    }
} catch (\Throwable $e) {
    $logger->error("Monitoring exception: {$e->getMessage()}");
    
    // Send fallback alert
    $fallbackAlert = new Alert(
        'Monitoring System Error',
        "Monitoring failed: {$e->getMessage()}",
        Alert::SEVERITY_ERROR
    );
}
```

### 4. Resource Management

Monitor the monitoring system itself:

```php
class MonitoringHealthCheck
{
    private int $lastSuccessfulCheck = 0;
    private int $failureCount = 0;
    
    public function recordSuccess(): void
    {
        $this->lastSuccessfulCheck = time();
        $this->failureCount = 0;
    }
    
    public function recordFailure(): void
    {
        $this->failureCount++;
    }
    
    public function isHealthy(): bool
    {
        $timeSinceLastCheck = time() - $this->lastSuccessfulCheck;
        
        // Unhealthy if no successful check in 5 minutes
        if ($timeSinceLastCheck > 300) {
            return false;
        }
        
        // Unhealthy if 3 consecutive failures
        if ($this->failureCount >= 3) {
            return false;
        }
        
        return true;
    }
}
```

### 5. Metric Batching

Batch metrics to reduce API calls:

```php
// ✅ Good: Single API call
$metrics = [
    'cpu_usage' => 75,
    'memory_usage' => 80,
    'disk_usage' => 65,
];

$task = implode("\n", array_map(
    fn($name, $value) => "{$name}: {$value}",
    array_keys($metrics),
    $metrics
));

$monitoringAgent->run($task);

// ❌ Bad: Multiple API calls
foreach ($metrics as $name => $value) {
    $monitoringAgent->run("{$name}: {$value}");
}
```

### 6. Alert Rate Limiting

Prevent alert fatigue:

```php
class RateLimitedMonitoring
{
    private array $lastAlertTime = [];
    private int $cooldown = 300; // 5 minutes
    
    public function shouldAlert(string $metricName): bool
    {
        $now = time();
        $lastAlert = $this->lastAlertTime[$metricName] ?? 0;
        
        if ($now - $lastAlert < $this->cooldown) {
            return false; // Still in cooldown
        }
        
        $this->lastAlertTime[$metricName] = $now;
        return true;
    }
}
```

### 7. Graceful Degradation

Continue monitoring even if some components fail:

```php
public function monitorAllSources(array $sources): void
{
    foreach ($sources as $source) {
        try {
            $this->monitor($source);
        } catch (\Throwable $e) {
            // Log error but continue with other sources
            $this->logger->error(
                "Failed to monitor {$source->getName()}: {$e->getMessage()}"
            );
            
            // Continue with next source
            continue;
        }
    }
}
```

## Complete Example

Here's a complete, production-ready monitoring system:

```php
#!/usr/bin/env php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Alert;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ProductionMonitoringSystem
{
    private MonitoringAgent $monitoringAgent;
    private AlertAgent $alertAgent;
    private Logger $logger;
    private array $sources = [];
    private bool $running = false;
    
    public function __construct(string $apiKey)
    {
        $client = new ClaudePhp(apiKey: $apiKey);
        
        // Setup logger
        $this->logger = new Logger('monitoring');
        $this->logger->pushHandler(
            new StreamHandler('/var/log/monitoring.log', Logger::INFO)
        );
        
        // Create monitoring agent
        $this->monitoringAgent = new MonitoringAgent($client, [
            'name' => 'production_monitor',
            'check_interval' => 60,
            'thresholds' => [
                'cpu_usage' => 80,
                'memory_usage' => 90,
                'disk_usage' => 85,
                'db_connections' => 100,
                'error_rate' => 2.0,
            ],
            'logger' => $this->logger,
        ]);
        
        // Create alert agent
        $this->alertAgent = new AlertAgent($client, [
            'logger' => $this->logger,
        ]);
        
        $this->setupAlertChannels();
        $this->setupSignalHandlers();
    }
    
    private function setupAlertChannels(): void
    {
        $this->alertAgent->registerChannel('log', function ($alert, $message) {
            $this->logger->warning($alert->getTitle(), [
                'severity' => $alert->getSeverity(),
                'message' => $message,
            ]);
        });
        
        $this->alertAgent->registerChannel('email', function ($alert, $message) {
            if ($alert->isCritical()) {
                mail(
                    getenv('ALERT_EMAIL'),
                    "CRITICAL: {$alert->getTitle()}",
                    $message
                );
            }
        });
    }
    
    private function setupSignalHandlers(): void
    {
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
    }
    
    public function addSource(MonitorableInterface $source): void
    {
        $this->sources[] = $source;
        $this->logger->info("Added monitoring source: {$source->getName()}");
    }
    
    public function start(): void
    {
        $this->logger->info("Starting monitoring system");
        $this->running = true;
        
        while ($this->running) {
            pcntl_signal_dispatch();
            
            foreach ($this->sources as $source) {
                $this->monitorSource($source);
            }
            
            sleep(60);
        }
    }
    
    private function monitorSource(MonitorableInterface $source): void
    {
        try {
            $metrics = $source->getMetrics();
            $metricLines = array_map(
                fn($m) => "{$m->getName()}: {$m->getValue()}",
                $metrics
            );
            
            $task = implode("\n", $metricLines);
            $result = $this->monitoringAgent->run($task);
            
            if ($result->isSuccess()) {
                $metadata = $result->getMetadata();
                
                if ($metadata['alerts_generated'] > 0) {
                    foreach ($metadata['alerts'] as $alertData) {
                        $alert = new Alert(
                            $alertData['title'],
                            $alertData['message'],
                            $alertData['severity']
                        );
                        
                        $this->alertAgent->processAlert($alert);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                "Monitoring failed for {$source->getName()}: {$e->getMessage()}"
            );
        }
    }
    
    public function shutdown(): void
    {
        $this->logger->info("Shutting down monitoring system");
        $this->running = false;
        $this->monitoringAgent->stop();
    }
}

// Initialize and run
$system = new ProductionMonitoringSystem(getenv('ANTHROPIC_API_KEY'));
$system->addSource(new SystemMonitor());
$system->addSource(new DatabaseMonitor($pdo));
$system->addSource(new ApplicationMonitor('/var/log/app.log'));
$system->start();
```

## Conclusion

You now have a comprehensive understanding of the MonitoringAgent and how to build production-ready monitoring systems. Key takeaways:

- Use **threshold-based alerting** for known limits
- Implement **LLM anomaly detection** for pattern analysis
- Create **custom monitorable sources** for your specific needs
- Combine with **AlertAgent** for comprehensive alerting
- Follow **best practices** for production deployments

## Next Steps

- Explore the [MonitoringAgent API Documentation](../MonitoringAgent.md)
- Review the [example implementations](../../examples/)
- Read about [AlertAgent integration](AlertAgent_Tutorial.md)
- Learn about [multi-agent systems](HierarchicalAgent_Tutorial.md)

## Additional Resources

- [Examples Directory](../../examples/) - Working code examples
- [API Reference](../MonitoringAgent.md) - Complete API documentation
- [AlertAgent Documentation](../AlertAgent.md) - Alert processing system
- [GitHub Issues](https://github.com/your-org/claude-php-agent/issues) - Report bugs or request features

