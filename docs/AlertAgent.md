# AlertAgent Documentation

## Overview

The `AlertAgent` is an intelligent alert processing system that uses Claude AI to enhance alert messages and manage multi-channel notifications. It provides features like alert aggregation, deduplication, smart routing, and LLM-enhanced message formatting.

## Features

- 🤖 **LLM-Enhanced Messages**: Uses Claude to create clear, actionable alert notifications
- 📡 **Multi-Channel Support**: Route alerts to email, Slack, webhooks, logs, and custom channels
- 🔄 **Alert Aggregation**: Automatically combines similar alerts within a time window
- 🚫 **Deduplication**: Prevents sending duplicate alerts
- 📝 **Custom Templates**: Define message templates for different severity levels
- 📊 **Metric Integration**: Track and alert on specific metrics with threshold checking
- 📜 **Alert History**: Maintain history of sent alerts for auditing and analysis

## Installation

The AlertAgent is included in the `claude-php-agent` package. Ensure you have the package installed:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$alertAgent = new AlertAgent($client);

// Register a channel
$alertAgent->registerChannel('console', function (Alert $alert, string $message) {
    echo "[{$alert->getSeverity()}] {$alert->getTitle()}: {$message}\n";
});

// Create and process an alert
$alert = new Alert(
    title: 'High CPU Usage',
    message: 'CPU usage exceeded 90%',
    severity: Alert::SEVERITY_WARNING
);

$alertAgent->processAlert($alert);
```

## Configuration

The AlertAgent accepts configuration options in its constructor:

```php
$alertAgent = new AlertAgent($client, [
    'name' => 'my_alert_agent',          // Agent name
    'aggregation_window' => 300,          // Time window for aggregation (seconds)
    'max_history' => 1000,                // Maximum alerts to keep in history
    'logger' => $logger,                  // PSR-3 logger instance
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'alert_agent'` | Unique name for the agent |
| `aggregation_window` | int | `300` | Time window in seconds for aggregating similar alerts |
| `max_history` | int | `1000` | Maximum number of alerts to keep in history |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

## Alert Severity Levels

Alerts support four severity levels:

```php
Alert::SEVERITY_INFO      // Informational messages
Alert::SEVERITY_WARNING   // Warning conditions
Alert::SEVERITY_ERROR     // Error conditions
Alert::SEVERITY_CRITICAL  // Critical/emergency conditions
```

## Working with Alerts

### Creating Alerts

```php
use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;

// Simple alert
$alert = new Alert(
    title: 'Database Connection Lost',
    message: 'Unable to connect to primary database',
    severity: Alert::SEVERITY_CRITICAL
);

// Alert with metric
$metric = new Metric('cpu_usage', 92.5, [
    'host' => 'web-01',
    'region' => 'us-east'
]);

$alert = new Alert(
    title: 'High CPU Usage',
    message: 'CPU usage exceeded threshold',
    severity: Alert::SEVERITY_WARNING,
    metric: $metric,
    context: [
        'threshold' => 90,
        'duration' => '5 minutes'
    ]
);
```

### Processing Alerts

```php
// Direct processing
$alertAgent->processAlert($alert);

// Using natural language (run method)
$result = $alertAgent->run(
    'Critical: Payment service is down and transactions are failing'
);

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    echo "Alert processed: {$metadata['alert']['title']}\n";
}
```

## Channels

Channels define how and where alerts are sent. You can register multiple channels and each will receive all alerts.

### Registering Channels

```php
$alertAgent->registerChannel('email', function (Alert $alert, string $message) {
    mail('ops@example.com', $alert->getTitle(), $message);
});

$alertAgent->registerChannel('slack', function (Alert $alert, string $message) {
    // Send to Slack webhook
    $webhook = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
    $payload = json_encode([
        'text' => $message,
        'username' => 'Alert Bot',
    ]);
    
    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
});

$alertAgent->registerChannel('log', function (Alert $alert, string $message) {
    error_log("[{$alert->getSeverity()}] {$alert->getTitle()}: {$message}");
});
```

### Conditional Channel Routing

You can add logic to only send certain alerts to specific channels:

```php
$alertAgent->registerChannel('pagerduty', function (Alert $alert, string $message) {
    // Only page for critical alerts
    if ($alert->isCritical()) {
        // Send to PagerDuty
        sendToPagerDuty($alert, $message);
    }
});
```

## Message Templates

Define custom message templates for different severity levels:

```php
$alertAgent->setTemplate(
    Alert::SEVERITY_CRITICAL,
    "🚨 CRITICAL ALERT\n\n" .
    "Title: {title}\n" .
    "Message: {message}\n" .
    "Time: {timestamp}\n" .
    "Metric: {metric_name} = {metric_value}\n\n" .
    "IMMEDIATE ACTION REQUIRED"
);

$alertAgent->setTemplate(
    Alert::SEVERITY_WARNING,
    "⚠️ Warning: {title}\n" .
    "{message}\n" .
    "Detected at {timestamp}"
);
```

### Available Template Variables

- `{title}` - Alert title
- `{message}` - Alert message
- `{severity}` - Alert severity level
- `{timestamp}` - Alert timestamp (formatted)
- `{metric_name}` - Name of the associated metric
- `{metric_value}` - Value of the associated metric

## Metrics

Metrics represent measurable values that can trigger alerts.

### Creating Metrics

```php
use ClaudeAgents\Monitoring\Metric;

$metric = new Metric(
    name: 'response_time',
    value: 2500,
    metadata: [
        'endpoint' => '/api/users',
        'unit' => 'ms'
    ]
);
```

### Threshold Checking

```php
$metric = new Metric('cpu_usage', 85.5);

if ($metric->exceedsThreshold(80, '>')) {
    // Trigger alert
}

// Available operators: '>', '>=', '<', '<=', '==', '!='
$metric->exceedsThreshold(90, '>=');  // Greater than or equal
$metric->exceedsThreshold(50, '<');   // Less than
$metric->exceedsThreshold(100, '=='); // Equal to
```

## Alert Deduplication

The AlertAgent automatically deduplicates alerts within a 60-second window. Alerts are considered duplicates if they have:

- Same title
- Same metric name (if present)

```php
// These will be deduplicated
$alert1 = new Alert('High CPU', 'CPU at 91%', Alert::SEVERITY_WARNING);
$alertAgent->processAlert($alert1);

$alert2 = new Alert('High CPU', 'CPU at 92%', Alert::SEVERITY_WARNING);
$alertAgent->processAlert($alert2); // Skipped as duplicate
```

## Alert Aggregation

Similar alerts within the aggregation window are automatically combined:

```php
$metric = new Metric('error_rate', 5.0);

// Send multiple similar alerts
for ($i = 0; $i < 3; $i++) {
    $alert = new Alert(
        "Error Rate High",
        "Error rate at {$i}%",
        Alert::SEVERITY_WARNING,
        $metric
    );
    $alertAgent->processAlert($alert);
}

// After 2 similar alerts, an aggregated alert is sent instead
```

## Alert History

Access the history of sent alerts:

```php
// Get last 10 alerts
$history = $alertAgent->getSentAlerts(10);

foreach ($history as $entry) {
    $alert = $entry['alert'];
    $timestamp = $entry['timestamp'];
    $message = $entry['enhanced_message'];
    
    echo "[{$alert->getSeverity()}] {$alert->getTitle()}\n";
}

// Get full history (up to max_history limit)
$allAlerts = $alertAgent->getSentAlerts();
```

## Error Handling

The AlertAgent handles errors gracefully:

```php
// Channel failures don't stop other channels
$alertAgent->registerChannel('failing', function () {
    throw new Exception('This channel fails');
});

$alertAgent->registerChannel('working', function ($alert, $message) {
    echo "This still works!\n";
});

// Both channels are called, failure is logged
$alertAgent->processAlert($alert);
```

## Integration Examples

### Integration with Monitoring System

```php
class SystemMonitor
{
    public function __construct(
        private AlertAgent $alertAgent,
        private array $thresholds = []
    ) {}
    
    public function checkMetrics(): void
    {
        $cpuUsage = $this->getCpuUsage();
        $metric = new Metric('cpu_usage', $cpuUsage);
        
        if ($metric->exceedsThreshold($this->thresholds['cpu'] ?? 90, '>')) {
            $alert = new Alert(
                'High CPU Usage',
                "CPU usage at {$cpuUsage}%",
                Alert::SEVERITY_WARNING,
                $metric
            );
            
            $this->alertAgent->processAlert($alert);
        }
    }
    
    private function getCpuUsage(): float
    {
        // Get actual CPU usage
        return 85.5;
    }
}
```

### Integration with Exception Handler

```php
set_exception_handler(function (Throwable $e) use ($alertAgent) {
    $alert = new Alert(
        'Uncaught Exception',
        $e->getMessage(),
        Alert::SEVERITY_ERROR,
        context: [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    );
    
    $alertAgent->processAlert($alert);
});
```

### Integration with Laravel

```php
// In a service provider
public function boot()
{
    $client = new ClaudePhp(config('services.anthropic.key'));
    $alertAgent = new AlertAgent($client, [
        'logger' => Log::channel('alerts')
    ]);
    
    $alertAgent->registerChannel('mail', function (Alert $alert, string $message) {
        Mail::to(config('alerts.email'))
            ->send(new AlertNotification($alert, $message));
    });
    
    app()->instance(AlertAgent::class, $alertAgent);
}

// In your code
public function handle()
{
    $alertAgent = app(AlertAgent::class);
    
    if ($this->detectIssue()) {
        $alertAgent->run('Critical: Payment processing is failing');
    }
}
```

## Best Practices

1. **Use Appropriate Severity Levels**
   - CRITICAL: System down, data loss, security breach
   - ERROR: Feature broken, transaction failed
   - WARNING: Performance degraded, approaching limits
   - INFO: Normal operational messages

2. **Provide Context**
   ```php
   $alert = new Alert(
       'Database Slow',
       'Query time exceeded threshold',
       Alert::SEVERITY_WARNING,
       $metric,
       context: [
           'query' => 'SELECT * FROM users',
           'threshold' => 1000,
           'actual' => 2500,
           'table' => 'users'
       ]
   );
   ```

3. **Use Metrics for Measurable Values**
   ```php
   $metric = new Metric('query_time', 2500, ['unit' => 'ms']);
   $alert = new Alert('Slow Query', 'Query exceeded threshold', metric: $metric);
   ```

4. **Configure Aggregation Window**
   ```php
   // For high-volume systems, use longer windows
   $alertAgent = new AlertAgent($client, [
       'aggregation_window' => 600 // 10 minutes
   ]);
   ```

5. **Implement Channel-Specific Logic**
   ```php
   $alertAgent->registerChannel('pagerduty', function (Alert $alert, string $message) {
       // Only page for critical production alerts
       if ($alert->isCritical() && app()->environment('production')) {
           $this->sendToPagerDuty($alert);
       }
   });
   ```

## API Reference

### AlertAgent Methods

#### `__construct(ClaudePhp $client, array $options = [])`
Create a new AlertAgent instance.

#### `processAlert(Alert $alert): void`
Process and send an alert through all registered channels.

#### `registerChannel(string $name, callable $callback): void`
Register a notification channel.

#### `setTemplate(string $severity, string $template): void`
Set a message template for a severity level.

#### `getSentAlerts(int $limit = 100): array`
Get alert history with optional limit.

#### `run(string $task): AgentResult`
Process an alert from natural language description.

#### `getName(): string`
Get the agent name.

### Alert Methods

#### `getTitle(): string`
Get the alert title.

#### `getMessage(): string`
Get the alert message.

#### `getSeverity(): string`
Get the severity level.

#### `getMetric(): ?Metric`
Get the associated metric, if any.

#### `getContext(): array`
Get additional context data.

#### `getTimestamp(): float`
Get the alert timestamp.

#### `isCritical(): bool`
Check if alert is critical severity.

#### `toArray(): array`
Convert alert to array representation.

### Metric Methods

#### `getName(): string`
Get the metric name.

#### `getValue(): mixed`
Get the metric value.

#### `getMetadata(): array`
Get metric metadata.

#### `getTimestamp(): float`
Get the metric timestamp.

#### `exceedsThreshold(float $threshold, string $operator = '>'): bool`
Check if metric exceeds threshold.

#### `toArray(): array`
Convert metric to array representation.

## Troubleshooting

### Alerts Not Being Sent

1. Check that channels are registered:
   ```php
   $alertAgent->registerChannel('test', function ($alert, $message) {
       var_dump('Channel called');
   });
   ```

2. Check for deduplication:
   ```php
   // Ensure alerts are sufficiently different
   $alert1 = new Alert('Alert 1', 'Message');
   $alert2 = new Alert('Alert 2', 'Message'); // Different title
   ```

### LLM Enhancement Failures

If LLM enhancement fails, the original message is used:

```php
// Set templates as fallback
$alertAgent->setTemplate(
    Alert::SEVERITY_ERROR,
    'Error: {title} - {message}'
);
```

### Performance Issues

1. Increase aggregation window to reduce API calls:
   ```php
   new AlertAgent($client, ['aggregation_window' => 600]);
   ```

2. Use templates instead of LLM for high-volume alerts:
   ```php
   // Set templates for all severity levels
   foreach ([Alert::SEVERITY_INFO, Alert::SEVERITY_WARNING, ...] as $severity) {
       $alertAgent->setTemplate($severity, $template);
   }
   ```

## See Also

- [AlertAgent Tutorial](tutorials/AlertAgent_Tutorial.md)
- [Examples](../examples/alert_agent.php)
- [Advanced Examples](../examples/advanced_alert_agent.php)

