# MonitoringAgent Documentation

## Overview

The `MonitoringAgent` is an intelligent monitoring system that watches data streams for anomalies and changes. It combines threshold-based alerting with LLM-powered anomaly detection to provide comprehensive monitoring capabilities for metrics, systems, and applications.

## Features

- 📊 **Metric Analysis**: Parse and analyze various metric formats
- 🎯 **Threshold-Based Alerting**: Configure custom thresholds for different metrics
- 🤖 **LLM-Powered Anomaly Detection**: Use Claude AI to detect unusual patterns
- 📈 **Historical Tracking**: Maintain metric history for trend analysis
- 🔄 **Continuous Monitoring**: Watch data sources in real-time
- 📝 **Flexible Data Sources**: Support for custom monitorable interfaces
- ⚡ **Real-time Analysis**: Immediate processing and alert generation

## Installation

The MonitoringAgent is included in the `claude-php-agent` package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Monitoring\Metric;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$monitoringAgent = new MonitoringAgent($client, [
    'thresholds' => [
        'cpu_usage' => 80,
        'memory_usage' => 90,
    ],
]);

// Analyze metrics
$task = "cpu_usage: 85\nmemory_usage: 75";
$result = $monitoringAgent->run($task);

if ($result->isSuccess()) {
    echo $result->getAnswer();
    $metadata = $result->getMetadata();
    echo "Alerts: {$metadata['alerts_generated']}\n";
}
```

## Configuration

The MonitoringAgent accepts configuration options in its constructor:

```php
$monitoringAgent = new MonitoringAgent($client, [
    'name' => 'production_monitor',
    'check_interval' => 60,           // Polling interval in seconds
    'thresholds' => [                 // Metric thresholds
        'cpu_usage' => 80,
        'memory_usage' => 90,
        'disk_usage' => 85,
    ],
    'max_history' => 1000,            // Maximum history entries
    'logger' => $logger,              // PSR-3 logger instance
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'monitoring_agent'` | Unique name for the agent |
| `check_interval` | int | `60` | Polling interval in seconds for continuous monitoring |
| `thresholds` | array | `[]` | Metric name => threshold value mappings |
| `max_history` | int | `1000` | Maximum number of historical metric entries to keep |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

## Working with Metrics

### Creating Metrics

Metrics represent measurable values that can be monitored and analyzed:

```php
use ClaudeAgents\Monitoring\Metric;

// Basic metric
$metric = new Metric('cpu_usage', 75.5);

// Metric with metadata
$metric = new Metric('response_time', 250, [
    'endpoint' => '/api/users',
    'method' => 'GET',
    'unit' => 'ms',
]);
```

### Metric Properties

```php
$metric = new Metric('cpu_usage', 85.5, ['host' => 'web-01']);

echo $metric->getName();        // 'cpu_usage'
echo $metric->getValue();       // 85.5
var_dump($metric->getMetadata()); // ['host' => 'web-01']
echo $metric->getTimestamp();   // Unix timestamp (float)
```

### Threshold Checking

```php
$metric = new Metric('cpu_usage', 85);

// Check if exceeds threshold
if ($metric->exceedsThreshold(80, '>')) {
    echo "CPU usage is high!";
}

// Available operators: '>', '>=', '<', '<=', '==', '!='
$metric->exceedsThreshold(90, '>=');  // Greater than or equal
$metric->exceedsThreshold(50, '<');   // Less than
$metric->exceedsThreshold(85, '==');  // Equal to
```

## Monitoring Methods

### Single Check with run()

Process metrics and generate alerts in a single check:

```php
$task = <<<METRICS
cpu_usage: 85.5
memory_usage: 72.3
disk_usage: 90.0
METRICS;

$result = $monitoringAgent->run($task);

if ($result->isSuccess()) {
    echo $result->getAnswer();
    
    $metadata = $result->getMetadata();
    echo "Metrics analyzed: {$metadata['metrics_analyzed']}\n";
    echo "Alerts generated: {$metadata['alerts_generated']}\n";
    
    foreach ($metadata['alerts'] as $alert) {
        echo "[{$alert['severity']}] {$alert['title']}\n";
    }
}
```

### Continuous Monitoring with watch()

Monitor a data source continuously:

```php
use ClaudeAgents\Contracts\MonitorableInterface;

class SystemMonitor implements MonitorableInterface
{
    public function getMetrics(): array
    {
        return [
            new Metric('cpu_usage', $this->getCpuUsage()),
            new Metric('memory_usage', $this->getMemoryUsage()),
        ];
    }
    
    public function getName(): string
    {
        return 'system';
    }
    
    private function getCpuUsage(): float
    {
        // Get actual CPU usage
        return 45.5;
    }
    
    private function getMemoryUsage(): float
    {
        // Get actual memory usage
        return 62.3;
    }
}

// Start monitoring
$monitor = new SystemMonitor();
$monitoringAgent->watch($monitor, function (Alert $alert) {
    echo "Alert: {$alert->getTitle()}\n";
    echo "Severity: {$alert->getSeverity()}\n";
    
    // Send notification, log, etc.
});

// Stop monitoring
$monitoringAgent->stop();
```

## Alert Types

The MonitoringAgent generates two types of alerts:

### 1. Threshold-Based Alerts

Generated when metrics exceed configured thresholds:

```php
$monitoringAgent = new MonitoringAgent($client, [
    'thresholds' => [
        'cpu_usage' => 80,
        'memory_usage' => 90,
    ],
]);

// This will generate threshold alerts
$result = $monitoringAgent->run("cpu_usage: 95\nmemory_usage: 92");
```

### 2. LLM-Based Anomaly Detection

Uses Claude AI to detect unusual patterns in historical data:

```php
// Requires historical data (5+ data points)
// Anomalies are detected automatically when patterns deviate from history

$task = "cpu_usage: 45\nmemory_usage: 50";

// First few checks build history
for ($i = 0; $i < 10; $i++) {
    $monitoringAgent->run($task);
    sleep(60);
}

// Now LLM can detect anomalies
// e.g., sudden spike to cpu_usage: 95 would trigger anomaly alert
```

## Monitorable Interface

Create custom data sources by implementing the `MonitorableInterface`:

```php
use ClaudeAgents\Contracts\MonitorableInterface;
use ClaudeAgents\Monitoring\Metric;

class DatabaseMonitor implements MonitorableInterface
{
    public function getMetrics(): array
    {
        return [
            new Metric('connections', $this->getConnectionCount(), [
                'max' => 100,
                'host' => 'db-primary',
            ]),
            new Metric('query_time', $this->getAverageQueryTime(), [
                'unit' => 'ms',
                'host' => 'db-primary',
            ]),
            new Metric('slow_queries', $this->getSlowQueryCount(), [
                'threshold' => '1s',
                'host' => 'db-primary',
            ]),
        ];
    }
    
    public function getName(): string
    {
        return 'database-primary';
    }
    
    private function getConnectionCount(): int
    {
        // Get actual connection count
        return 45;
    }
    
    private function getAverageQueryTime(): float
    {
        // Get average query time in ms
        return 25.5;
    }
    
    private function getSlowQueryCount(): int
    {
        // Get count of slow queries
        return 2;
    }
}

// Use the monitor
$dbMonitor = new DatabaseMonitor();
$monitoringAgent->watch($dbMonitor, function (Alert $alert) {
    // Handle alerts
});
```

## Metric Format

When using `run()`, metrics should be formatted as:

```
metric_name: value
```

Examples:

```
cpu_usage: 75.5
memory_usage: 82
disk_space: 90.0
response_time: 250
error_count: 5
```

The parser automatically:
- Converts numeric strings to numbers
- Preserves string values for non-numeric data
- Skips invalid lines

## Working with Alerts

Alerts are generated as `Alert` objects:

```php
use ClaudeAgents\Monitoring\Alert;

// Alert properties
$alert->getId();          // Unique alert ID
$alert->getTitle();       // Alert title
$alert->getMessage();     // Alert message
$alert->getSeverity();    // Severity level
$alert->getMetric();      // Associated metric (if any)
$alert->getContext();     // Additional context
$alert->getTimestamp();   // When alert was created

// Severity check
if ($alert->isCritical()) {
    // Handle critical alert
}

// Convert to array
$data = $alert->toArray();
```

Alert severity levels:

```php
Alert::SEVERITY_INFO      // Informational
Alert::SEVERITY_WARNING   // Warning condition
Alert::SEVERITY_ERROR     // Error condition
Alert::SEVERITY_CRITICAL  // Critical condition
```

## Integration Examples

### Integration with AlertAgent

Combine MonitoringAgent with AlertAgent for comprehensive monitoring and alerting:

```php
use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Agents\AlertAgent;

$monitoringAgent = new MonitoringAgent($client, [
    'thresholds' => ['cpu_usage' => 80],
]);

$alertAgent = new AlertAgent($client);
$alertAgent->registerChannel('slack', function ($alert, $message) {
    // Send to Slack
});

// Monitor and alert
$result = $monitoringAgent->run("cpu_usage: 95");

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    foreach ($metadata['alerts'] as $alertData) {
        $alert = new Alert(
            $alertData['title'],
            $alertData['message'],
            $alertData['severity']
        );
        
        $alertAgent->processAlert($alert);
    }
}
```

### Multi-Source Monitoring

Monitor multiple data sources simultaneously:

```php
$sources = [
    new DatabaseMonitor('db-primary'),
    new ApplicationMonitor('web-app'),
    new InfrastructureMonitor('k8s-cluster'),
];

foreach ($sources as $source) {
    $metrics = $source->getMetrics();
    $metricLines = array_map(
        fn($m) => "{$m->getName()}: {$m->getValue()}",
        $metrics
    );
    
    $task = implode("\n", $metricLines);
    $result = $monitoringAgent->run($task);
    
    if ($result->isSuccess()) {
        $metadata = $result->getMetadata();
        echo "{$source->getName()}: {$metadata['alerts_generated']} alerts\n";
    }
}
```

### Background Monitoring Service

Create a long-running monitoring service:

```php
class MonitoringService
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
    
    public function run(): void
    {
        foreach ($this->sources as $source) {
            $this->agent->watch($source, function (Alert $alert) {
                $this->handleAlert($alert);
            });
        }
    }
    
    private function handleAlert(Alert $alert): void
    {
        // Log alert
        error_log("[{$alert->getSeverity()}] {$alert->getTitle()}");
        
        // Send notifications based on severity
        if ($alert->isCritical()) {
            $this->sendCriticalNotification($alert);
        }
    }
}
```

## Best Practices

### 1. Configure Appropriate Thresholds

Set thresholds based on your system's normal operating range:

```php
$monitoringAgent = new MonitoringAgent($client, [
    'thresholds' => [
        // Set based on historical data and capacity
        'cpu_usage' => 80,      // 80% CPU
        'memory_usage' => 85,   // 85% memory
        'disk_usage' => 90,     // 90% disk
        'response_time' => 500, // 500ms response
        'error_rate' => 1.0,    // 1% error rate
    ],
]);
```

### 2. Use Metadata for Context

Include relevant metadata with metrics:

```php
$metric = new Metric('query_time', 350, [
    'query_type' => 'SELECT',
    'table' => 'users',
    'endpoint' => '/api/users',
    'host' => 'db-replica-2',
]);
```

### 3. Implement Proper Error Handling

```php
try {
    $result = $monitoringAgent->run($task);
    
    if ($result->isSuccess()) {
        // Process result
    } else {
        error_log("Monitoring failed: {$result->getError()}");
    }
} catch (\Throwable $e) {
    error_log("Monitoring exception: {$e->getMessage()}");
}
```

### 4. Monitor Multiple Metrics

Include comprehensive metrics for better insights:

```php
$task = <<<METRICS
cpu_usage: 75
memory_usage: 80
disk_usage: 65
network_latency: 45
active_connections: 150
request_rate: 1200
error_rate: 0.5
response_time_p95: 250
METRICS;
```

### 5. Use Historical Data for Anomaly Detection

Build sufficient history before relying on anomaly detection:

```php
// Build baseline (run periodically)
for ($i = 0; $i < 20; $i++) {
    $monitoringAgent->run($metricsTask);
    sleep(60);
}

// Now anomaly detection will be effective
```

## Anomaly Detection

The MonitoringAgent uses Claude AI to detect anomalies when:

1. **Sufficient History**: At least 5 historical data points exist
2. **Pattern Analysis**: Claude analyzes current value against historical pattern
3. **Deviation Detection**: Identifies sudden spikes, drops, or unusual deviations

Example anomaly detection prompt:

```
Analyze this metric for anomalies:

Metric: cpu_usage
Current Value: 95
Historical Values (recent): [45, 47, 43, 46, 44]

Is the current value anomalous compared to the historical pattern?
```

Claude will identify the spike from ~45 to 95 as an anomaly.

## Performance Considerations

### Threshold vs. LLM Detection

- **Threshold Alerts**: Instant, no API calls, predictable
- **LLM Anomaly Detection**: Requires API call, more intelligent, detects patterns

### Optimization Tips

1. **Use Thresholds First**: Configure thresholds for known limits
2. **Limit LLM Calls**: Anomaly detection only runs when sufficient history exists
3. **Adjust Check Interval**: Balance between responsiveness and API usage
4. **Batch Metrics**: Analyze multiple metrics in one API call

```php
// Efficient: Single API call for multiple metrics
$task = "cpu: 75\nmemory: 80\ndisk: 65";
$monitoringAgent->run($task);

// Inefficient: Multiple API calls
$monitoringAgent->run("cpu: 75");
$monitoringAgent->run("memory: 80");
$monitoringAgent->run("disk: 65");
```

## API Reference

### MonitoringAgent Methods

#### `__construct(ClaudePhp $client, array $options = [])`
Create a new MonitoringAgent instance.

#### `run(string $task): AgentResult`
Analyze metrics and generate alerts. Returns result with:
- `answer`: Formatted analysis report
- `metadata['metrics_analyzed']`: Number of metrics analyzed
- `metadata['alerts_generated']`: Number of alerts generated
- `metadata['alerts']`: Array of alert data

#### `watch(MonitorableInterface $source, callable $onAlert): void`
Continuously monitor a data source. Calls `$onAlert` callback for each alert.

#### `stop(): void`
Stop continuous monitoring.

#### `getName(): string`
Get the agent name.

#### `getAlerts(): array`
Get all generated alerts.

### Metric Methods

#### `getName(): string`
Get the metric name.

#### `getValue(): mixed`
Get the metric value.

#### `getMetadata(): array`
Get metric metadata.

#### `getTimestamp(): float`
Get the metric timestamp (Unix timestamp).

#### `exceedsThreshold(float $threshold, string $operator = '>'): bool`
Check if metric exceeds threshold with given operator.

#### `toArray(): array`
Convert metric to array representation.

### Alert Methods

See [AlertAgent documentation](AlertAgent.md) for complete Alert API reference.

## Troubleshooting

### No Alerts Generated

1. **Check Thresholds**: Ensure thresholds are configured
   ```php
   $monitoringAgent = new MonitoringAgent($client, [
       'thresholds' => ['cpu_usage' => 80],
   ]);
   ```

2. **Verify Metric Format**: Ensure metrics follow `name: value` format
   ```php
   // Correct
   "cpu_usage: 85"
   
   // Incorrect
   "cpu_usage=85"
   "85"
   ```

3. **Check Metric Values**: Ensure values are numeric for threshold checks

### LLM Anomaly Detection Not Working

1. **Build History First**: Need at least 5 data points
2. **Check API Key**: Ensure Claude API key is valid
3. **Review Logs**: Check logger output for errors

### High API Usage

1. **Increase Check Interval**: Reduce monitoring frequency
2. **Use Thresholds**: Rely on thresholds instead of LLM when possible
3. **Batch Metrics**: Combine multiple metrics in single calls

## See Also

- [AlertAgent Documentation](AlertAgent.md) - Alert processing and notifications
- [MonitoringAgent Tutorial](tutorials/MonitoringAgent_Tutorial.md) - Step-by-step guide
- [Basic Example](../examples/monitoring_agent.php) - Simple monitoring example
- [Advanced Example](../examples/advanced_monitoring_agent.php) - Multi-source monitoring

