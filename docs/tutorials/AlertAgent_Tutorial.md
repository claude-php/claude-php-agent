# AlertAgent Tutorial: Building an Intelligent Alert System

## Introduction

This tutorial will guide you through building a production-ready alert system using the AlertAgent. We'll start with basic concepts and progress to advanced patterns used in real-world applications.

By the end of this tutorial, you'll be able to:

- Create and process alerts with different severity levels
- Set up multi-channel notifications
- Implement metric-based threshold alerting
- Use LLM enhancement for better alert messages
- Handle alert aggregation and deduplication
- Build a complete monitoring system

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and error handling

## Table of Contents

1. [Getting Started](#getting-started)
2. [Creating Your First Alert](#creating-your-first-alert)
3. [Working with Metrics](#working-with-metrics)
4. [Setting Up Notification Channels](#setting-up-notification-channels)
5. [Message Enhancement with LLM](#message-enhancement-with-llm)
6. [Alert Aggregation and Deduplication](#alert-aggregation-and-deduplication)
7. [Building a Complete Monitoring System](#building-a-complete-monitoring-system)
8. [Production Best Practices](#production-best-practices)

## Getting Started

### Installation

First, ensure you have the claude-php-agent package installed:

```bash
composer require your-org/claude-php-agent
```

### Basic Setup

Create a simple script to test the AlertAgent:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Alert;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create the alert agent
$alertAgent = new AlertAgent($client, [
    'name' => 'tutorial_agent',
]);

echo "Alert agent ready!\n";
```

## Creating Your First Alert

Let's create a basic alert to understand the fundamentals.

### Step 1: Understanding Alert Severity

Alerts have four severity levels:

```php
Alert::SEVERITY_INFO      // 🔵 Informational
Alert::SEVERITY_WARNING   // 🟡 Warning - needs attention
Alert::SEVERITY_ERROR     // 🟠 Error - something broke
Alert::SEVERITY_CRITICAL  // 🔴 Critical - immediate action required
```

### Step 2: Creating a Simple Alert

```php
use ClaudeAgents\Monitoring\Alert;

// Create an informational alert
$alert = new Alert(
    title: 'System Update Available',
    message: 'A new version of the application is available',
    severity: Alert::SEVERITY_INFO
);

echo "Created alert: {$alert->getTitle()}\n";
echo "Severity: {$alert->getSeverity()}\n";
echo "ID: {$alert->getId()}\n";
```

### Step 3: Processing Your First Alert

To process an alert, you need at least one notification channel:

```php
// Register a simple console channel
$alertAgent->registerChannel('console', function (Alert $alert, string $message) {
    echo "\n=== ALERT ===\n";
    echo "Title: {$alert->getTitle()}\n";
    echo "Severity: {$alert->getSeverity()}\n";
    echo "Message: {$message}\n";
    echo "=============\n";
});

// Process the alert
$alertAgent->processAlert($alert);
```

**Output:**

```
=== ALERT ===
Title: System Update Available
Severity: info
Message: [LLM-enhanced message here]
=============
```

## Working with Metrics

Metrics allow you to track measurable values and trigger alerts based on thresholds.

### Creating Metrics

```php
use ClaudeAgents\Monitoring\Metric;

// Create a CPU usage metric
$cpuMetric = new Metric(
    name: 'cpu_usage',
    value: 87.5,
    metadata: [
        'host' => 'web-server-01',
        'region' => 'us-east-1',
        'timestamp' => time()
    ]
);

echo "Metric: {$cpuMetric->getName()}\n";
echo "Value: {$cpuMetric->getValue()}\n";
```

### Threshold Checking

Metrics can check if they exceed thresholds:

```php
// Check if CPU usage is too high
if ($cpuMetric->exceedsThreshold(85, '>')) {
    $alert = new Alert(
        title: 'High CPU Usage',
        message: "CPU usage at {$cpuMetric->getValue()}% exceeds threshold of 85%",
        severity: Alert::SEVERITY_WARNING,
        metric: $cpuMetric,
        context: ['threshold' => 85]
    );

    $alertAgent->processAlert($alert);
}
```

### Threshold Operators

```php
$metric = new Metric('value', 75);

$metric->exceedsThreshold(80, '>');   // Greater than
$metric->exceedsThreshold(75, '>=');  // Greater than or equal
$metric->exceedsThreshold(80, '<');   // Less than
$metric->exceedsThreshold(75, '<=');  // Less than or equal
$metric->exceedsThreshold(75, '==');  // Equal to
$metric->exceedsThreshold(80, '!=');  // Not equal to
```

### Practical Example: Monitoring Multiple Metrics

```php
class MetricsMonitor
{
    private array $thresholds = [
        'cpu_usage' => ['value' => 85, 'operator' => '>'],
        'memory_usage' => ['value' => 90, 'operator' => '>'],
        'disk_usage' => ['value' => 95, 'operator' => '>'],
        'response_time' => ['value' => 1000, 'operator' => '>'],
    ];

    public function __construct(private AlertAgent $alertAgent)
    {
    }

    public function checkMetric(string $name, float $value, array $metadata = []): void
    {
        if (!isset($this->thresholds[$name])) {
            return;
        }

        $metric = new Metric($name, $value, $metadata);
        $threshold = $this->thresholds[$name];

        if ($metric->exceedsThreshold($threshold['value'], $threshold['operator'])) {
            $severity = $this->calculateSeverity($name, $value, $threshold['value']);

            $alert = new Alert(
                title: "High " . ucwords(str_replace('_', ' ', $name)),
                message: "{$name} at {$value} exceeds threshold of {$threshold['value']}",
                severity: $severity,
                metric: $metric,
                context: ['threshold' => $threshold['value']]
            );

            $this->alertAgent->processAlert($alert);
        }
    }

    private function calculateSeverity(string $name, float $value, float $threshold): string
    {
        $ratio = $value / $threshold;

        if ($ratio > 1.5) return Alert::SEVERITY_CRITICAL;
        if ($ratio > 1.2) return Alert::SEVERITY_ERROR;
        return Alert::SEVERITY_WARNING;
    }
}

// Usage
$monitor = new MetricsMonitor($alertAgent);
$monitor->checkMetric('cpu_usage', 92.5, ['host' => 'web-01']);
$monitor->checkMetric('memory_usage', 88.0, ['host' => 'web-01']);
```

## Setting Up Notification Channels

Channels define where alerts are sent. Let's set up several common channels.

### Console Channel

```php
$alertAgent->registerChannel('console', function (Alert $alert, string $message) {
    $emoji = match($alert->getSeverity()) {
        Alert::SEVERITY_CRITICAL => '🔴',
        Alert::SEVERITY_ERROR => '🟠',
        Alert::SEVERITY_WARNING => '🟡',
        Alert::SEVERITY_INFO => '🔵',
    };

    echo "{$emoji} [{$alert->getSeverity()}] {$alert->getTitle()}\n";
    echo "   {$message}\n\n";
});
```

### File Logging Channel

```php
$alertAgent->registerChannel('file', function (Alert $alert, string $message) {
    $logFile = '/var/log/alerts.log';
    $entry = sprintf(
        "[%s] [%s] %s - %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($alert->getSeverity()),
        $alert->getTitle(),
        $message
    );

    file_put_contents($logFile, $entry, FILE_APPEND);
});
```

### Email Channel

```php
$alertAgent->registerChannel('email', function (Alert $alert, string $message) {
    // Only send critical and error alerts via email
    if (!in_array($alert->getSeverity(), [Alert::SEVERITY_CRITICAL, Alert::SEVERITY_ERROR])) {
        return;
    }

    $to = 'ops-team@company.com';
    $subject = "[{$alert->getSeverity()}] {$alert->getTitle()}";
    $body = $message;

    if ($alert->getMetric()) {
        $body .= "\n\nMetric: {$alert->getMetric()->getName()} = {$alert->getMetric()->getValue()}";
    }

    mail($to, $subject, $body);
});
```

### Slack Channel

```php
$alertAgent->registerChannel('slack', function (Alert $alert, string $message) {
    $webhookUrl = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';

    $color = match($alert->getSeverity()) {
        Alert::SEVERITY_CRITICAL, Alert::SEVERITY_ERROR => 'danger',
        Alert::SEVERITY_WARNING => 'warning',
        default => 'good',
    };

    $payload = [
        'attachments' => [
            [
                'color' => $color,
                'title' => $alert->getTitle(),
                'text' => $message,
                'footer' => 'Alert System',
                'ts' => (int)$alert->getTimestamp(),
                'fields' => [
                    [
                        'title' => 'Severity',
                        'value' => strtoupper($alert->getSeverity()),
                        'short' => true,
                    ],
                ],
            ],
        ],
    ];

    if ($alert->getMetric()) {
        $payload['attachments'][0]['fields'][] = [
            'title' => 'Metric',
            'value' => "{$alert->getMetric()->getName()}: {$alert->getMetric()->getValue()}",
            'short' => true,
        ];
    }

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
});
```

### Webhook Channel

```php
$alertAgent->registerChannel('webhook', function (Alert $alert, string $message) {
    $webhookUrl = 'https://api.company.com/alerts';

    $payload = [
        'alert_id' => $alert->getId(),
        'title' => $alert->getTitle(),
        'message' => $message,
        'severity' => $alert->getSeverity(),
        'timestamp' => $alert->getTimestamp(),
        'metric' => $alert->getMetric()?->toArray(),
        'context' => $alert->getContext(),
    ];

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer YOUR_API_TOKEN',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
});
```

### PagerDuty Channel (Critical Only)

```php
$alertAgent->registerChannel('pagerduty', function (Alert $alert, string $message) {
    // Only page for critical alerts
    if (!$alert->isCritical()) {
        return;
    }

    $apiUrl = 'https://events.pagerduty.com/v2/enqueue';
    $routingKey = 'YOUR_PAGERDUTY_ROUTING_KEY';

    $payload = [
        'routing_key' => $routingKey,
        'event_action' => 'trigger',
        'payload' => [
            'summary' => $alert->getTitle(),
            'severity' => $alert->getSeverity(),
            'source' => 'alert-agent',
            'custom_details' => [
                'message' => $message,
                'context' => $alert->getContext(),
            ],
        ],
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
});
```

## Message Enhancement with LLM

One of the most powerful features is LLM-enhanced messages that provide clear, actionable information.

### How It Works

When you process an alert without a template, the AlertAgent uses Claude to enhance the message:

```php
$alert = new Alert(
    title: 'Database Connection Pool Exhausted',
    message: 'All 100 connections are in use, requests timing out',
    severity: Alert::SEVERITY_ERROR,
    context: [
        'pool_size' => 100,
        'active_connections' => 100,
        'queued_requests' => 47,
    ]
);

$alertAgent->processAlert($alert);
```

Claude will enhance this to something like:

```
Database Connection Pool Critical Issue

What happened: All 100 database connections in the connection pool
are currently in use, causing new requests to time out.

Why it matters: Users are experiencing failed requests and service
degradation. 47 requests are currently queued.

Recommended action:
1. Check for long-running queries or transactions
2. Consider increasing the connection pool size
3. Review recent deployment changes that may have introduced connection leaks
4. Monitor error logs for specific timeout patterns
```

### Custom Templates

For high-volume scenarios, define templates to avoid API calls:

```php
// Critical alert template
$alertAgent->setTemplate(
    Alert::SEVERITY_CRITICAL,
    <<<TEMPLATE
🚨 CRITICAL ALERT - IMMEDIATE ACTION REQUIRED

Title: {title}
Time: {timestamp}
Severity: {severity}

{message}

Metric: {metric_name} = {metric_value}

This requires immediate attention from the on-call engineer.
TEMPLATE
);

// Warning alert template
$alertAgent->setTemplate(
    Alert::SEVERITY_WARNING,
    <<<TEMPLATE
⚠️  Warning: {title}

{message}

Detected at: {timestamp}
Metric: {metric_name} = {metric_value}

Please investigate when convenient.
TEMPLATE
);

// Info template
$alertAgent->setTemplate(
    Alert::SEVERITY_INFO,
    "ℹ️  {title}\n\n{message}\n\nTime: {timestamp}"
);
```

### Choosing Between LLM and Templates

**Use LLM Enhancement when:**

- Alerts are infrequent
- Context varies significantly
- You want detailed, actionable guidance
- Alert quality is more important than speed

**Use Templates when:**

- High alert volume
- Predictable alert patterns
- Speed is critical
- Cost optimization is important

## Alert Aggregation and Deduplication

### Deduplication

Prevents sending the same alert multiple times within 60 seconds:

```php
// First alert is sent
$alert1 = new Alert('High CPU', 'CPU at 91%', Alert::SEVERITY_WARNING);
$alertAgent->processAlert($alert1);

// Duplicate within 60 seconds - skipped
$alert2 = new Alert('High CPU', 'CPU at 92%', Alert::SEVERITY_WARNING);
$alertAgent->processAlert($alert2); // Automatically skipped
```

### Aggregation

Similar alerts within the aggregation window are combined:

```php
// Configure aggregation window
$alertAgent = new AlertAgent($client, [
    'aggregation_window' => 300, // 5 minutes
]);

$metric = new Metric('error_rate', 5.0);

// Send multiple similar alerts
for ($i = 0; $i < 5; $i++) {
    $alert = new Alert(
        "API Error Rate High",
        "Error rate at {$metric->getValue()}%",
        Alert::SEVERITY_WARNING,
        $metric
    );
    $alertAgent->processAlert($alert);
    sleep(10);
}

// After 3 similar alerts, one aggregated alert is sent instead:
// "Multiple alerts for error_rate: Aggregated 5 similar alerts in the last 300s"
```

### Alert History

Access historical alerts for analysis:

```php
// Get recent alerts
$history = $alertAgent->getSentAlerts(20);

foreach ($history as $entry) {
    $alert = $entry['alert'];
    $timestamp = $entry['timestamp'];
    $message = $entry['enhanced_message'];

    echo date('Y-m-d H:i:s', (int)$timestamp);
    echo " [{$alert->getSeverity()}] {$alert->getTitle()}\n";
}

// Calculate statistics
$stats = [];
foreach ($history as $entry) {
    $severity = $entry['alert']->getSeverity();
    $stats[$severity] = ($stats[$severity] ?? 0) + 1;
}

print_r($stats);
// Output: ['warning' => 12, 'error' => 5, 'critical' => 2]
```

## Building a Complete Monitoring System

Let's build a production-ready monitoring system that ties everything together.

### Step 1: Create the Monitor Class

```php
<?php

use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;

class SystemMonitor
{
    private array $thresholds;
    private array $checkHistory = [];

    public function __construct(
        private AlertAgent $alertAgent,
        array $thresholds = []
    ) {
        $this->thresholds = array_merge([
            'cpu_usage' => ['warning' => 80, 'critical' => 95],
            'memory_usage' => ['warning' => 85, 'critical' => 95],
            'disk_usage' => ['warning' => 90, 'critical' => 98],
            'response_time' => ['warning' => 1000, 'critical' => 5000],
            'error_rate' => ['warning' => 1.0, 'critical' => 5.0],
        ], $thresholds);
    }

    public function checkAll(): void
    {
        $this->checkCpu();
        $this->checkMemory();
        $this->checkDisk();
        $this->checkResponseTime();
        $this->checkErrorRate();
    }

    private function checkCpu(): void
    {
        $usage = $this->getCpuUsage();
        $this->evaluateMetric('cpu_usage', $usage, '%');
    }

    private function checkMemory(): void
    {
        $usage = $this->getMemoryUsage();
        $this->evaluateMetric('memory_usage', $usage, '%');
    }

    private function checkDisk(): void
    {
        $usage = $this->getDiskUsage();
        $this->evaluateMetric('disk_usage', $usage, '%');
    }

    private function checkResponseTime(): void
    {
        $time = $this->getAvgResponseTime();
        $this->evaluateMetric('response_time', $time, 'ms');
    }

    private function checkErrorRate(): void
    {
        $rate = $this->getErrorRate();
        $this->evaluateMetric('error_rate', $rate, '%');
    }

    private function evaluateMetric(string $name, float $value, string $unit): void
    {
        $thresholds = $this->thresholds[$name];
        $metric = new Metric($name, $value, [
            'unit' => $unit,
            'host' => gethostname(),
            'timestamp' => time(),
        ]);

        $severity = null;
        $thresholdValue = null;

        if ($value >= $thresholds['critical']) {
            $severity = Alert::SEVERITY_CRITICAL;
            $thresholdValue = $thresholds['critical'];
        } elseif ($value >= $thresholds['warning']) {
            $severity = Alert::SEVERITY_WARNING;
            $thresholdValue = $thresholds['warning'];
        }

        if ($severity) {
            $alert = new Alert(
                title: "High " . ucwords(str_replace('_', ' ', $name)),
                message: "{$name} at {$value}{$unit} exceeds {$severity} threshold of {$thresholdValue}{$unit}",
                severity: $severity,
                metric: $metric,
                context: [
                    'threshold' => $thresholdValue,
                    'current' => $value,
                    'unit' => $unit,
                ]
            );

            $this->alertAgent->processAlert($alert);
        }

        $this->checkHistory[$name][] = [
            'value' => $value,
            'timestamp' => time(),
        ];
    }

    // System metric collection methods
    private function getCpuUsage(): float
    {
        // On Linux
        $load = sys_getloadavg();
        return $load[0] * 100 / max(1, shell_exec('nproc'));
    }

    private function getMemoryUsage(): float
    {
        $free = shell_exec('free');
        $free = (string)trim($free);
        $freeArr = explode("\n", $free);
        $mem = explode(" ", $freeArr[1]);
        $mem = array_filter($mem);
        $mem = array_values($mem);
        $usedMem = $mem[2];
        $totalMem = $mem[1];
        return ($usedMem / $totalMem) * 100;
    }

    private function getDiskUsage(): float
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        return (($total - $free) / $total) * 100;
    }

    private function getAvgResponseTime(): float
    {
        // Implement based on your application
        // This is a placeholder
        return rand(100, 500);
    }

    private function getErrorRate(): float
    {
        // Implement based on your application logs
        // This is a placeholder
        return rand(0, 3) * 0.5;
    }

    public function getHistory(string $metric, int $limit = 100): array
    {
        return array_slice($this->checkHistory[$metric] ?? [], -$limit);
    }
}
```

### Step 2: Set Up the Alert System

```php
<?php

require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\AlertAgent;
use ClaudePhp\ClaudePhp;

// Initialize
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$alertAgent = new AlertAgent($client, [
    'name' => 'production_monitor',
    'aggregation_window' => 300,
]);

// Set up channels
$alertAgent->registerChannel('log', function ($alert, $message) {
    error_log("[ALERT] [{$alert->getSeverity()}] {$alert->getTitle()}: {$message}");
});

$alertAgent->registerChannel('slack', function ($alert, $message) {
    // Your Slack integration
    sendToSlack($alert, $message);
});

$alertAgent->registerChannel('email', function ($alert, $message) {
    if ($alert->isCritical()) {
        mail('ops@company.com', $alert->getTitle(), $message);
    }
});

// Create monitor
$monitor = new SystemMonitor($alertAgent);

// Run monitoring loop
while (true) {
    $monitor->checkAll();
    sleep(60); // Check every minute
}
```

### Step 3: Add Natural Language Alerts

```php
// In your application code, you can use natural language:
try {
    // Some risky operation
    processPayment($order);
} catch (Exception $e) {
    $alertAgent->run(
        "Critical error processing payment for order #{$order->id}. " .
        "Error: {$e->getMessage()}. Customer payment failed and needs immediate attention."
    );
    throw $e;
}
```

## Production Best Practices

### 1. Configure Appropriate Thresholds

```php
$monitor = new SystemMonitor($alertAgent, [
    'cpu_usage' => ['warning' => 75, 'critical' => 90],
    'memory_usage' => ['warning' => 80, 'critical' => 95],
    // Adjust based on your system
]);
```

### 2. Use Severity Levels Wisely

```php
// Critical: System down, data loss, security breach
$alert = new Alert('Database Offline', '...', Alert::SEVERITY_CRITICAL);

// Error: Feature broken, transactions failing
$alert = new Alert('Payment API Failed', '...', Alert::SEVERITY_ERROR);

// Warning: Performance degraded, approaching limits
$alert = new Alert('High Memory', '...', Alert::SEVERITY_WARNING);

// Info: Normal operational events
$alert = new Alert('Deployment Complete', '...', Alert::SEVERITY_INFO);
```

### 3. Provide Rich Context

```php
$alert = new Alert(
    'Payment Processing Failed',
    'Payment gateway returned error',
    Alert::SEVERITY_ERROR,
    context: [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'amount' => $order->total,
        'gateway' => 'stripe',
        'error_code' => $error->code,
        'error_message' => $error->message,
    ]
);
```

### 4. Implement Circuit Breakers

```php
class CircuitBreakerMonitor
{
    private int $errorCount = 0;
    private bool $circuitOpen = false;

    public function recordError(): void
    {
        $this->errorCount++;

        if ($this->errorCount >= 5 && !$this->circuitOpen) {
            $this->circuitOpen = true;

            $alert = new Alert(
                'Circuit Breaker Opened',
                "Service degraded after {$this->errorCount} consecutive errors",
                Alert::SEVERITY_ERROR,
                context: ['error_count' => $this->errorCount]
            );

            $this->alertAgent->processAlert($alert);
        }
    }
}
```

### 5. Log All Alerts

```php
$alertAgent->registerChannel('audit_log', function ($alert, $message) {
    $logEntry = [
        'timestamp' => date('c'),
        'alert_id' => $alert->getId(),
        'title' => $alert->getTitle(),
        'severity' => $alert->getSeverity(),
        'message' => $message,
        'metric' => $alert->getMetric()?->toArray(),
        'context' => $alert->getContext(),
    ];

    file_put_contents(
        '/var/log/alerts.jsonl',
        json_encode($logEntry) . "\n",
        FILE_APPEND
    );
});
```

### 6. Monitor Alert System Health

```php
// Track alert metrics
$alertMetrics = [
    'alerts_sent' => 0,
    'alerts_by_severity' => [],
    'channel_failures' => [],
];

$alertAgent->registerChannel('metrics', function ($alert) use (&$alertMetrics) {
    $alertMetrics['alerts_sent']++;
    $severity = $alert->getSeverity();
    $alertMetrics['alerts_by_severity'][$severity] =
        ($alertMetrics['alerts_by_severity'][$severity] ?? 0) + 1;
});
```

### 7. Test Your Alert System

```php
// Send a test alert
function sendTestAlert(AlertAgent $agent): void
{
    $alert = new Alert(
        'Test Alert - Please Ignore',
        'This is a test of the alert system',
        Alert::SEVERITY_INFO,
        context: ['test' => true]
    );

    $agent->processAlert($alert);
}

// Run daily at 9 AM
sendTestAlert($alertAgent);
```

## Conclusion

You now have a complete understanding of the AlertAgent system! You've learned:

✅ How to create and process alerts  
✅ Working with metrics and thresholds  
✅ Setting up multiple notification channels  
✅ Using LLM enhancement and templates  
✅ Implementing aggregation and deduplication  
✅ Building a complete monitoring system  
✅ Production best practices

## Next Steps

- Review the [AlertAgent API Documentation](../AlertAgent.md)
- Check out the [examples directory](../../examples/) for more code
- Explore integration with your existing monitoring tools
- Implement custom channels for your specific needs

## Additional Resources

- [AlertAgent.md](../AlertAgent.md) - Complete API reference
- [alert_agent.php](../../examples/alert_agent.php) - Basic example
- [advanced_alert_agent.php](../../examples/advanced_alert_agent.php) - Advanced patterns
- [Claude API Documentation](https://docs.anthropic.com/)

Happy alerting! 🚨
