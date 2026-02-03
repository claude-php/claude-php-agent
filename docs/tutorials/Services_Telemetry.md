# TelemetryService Tutorial

Learn how to collect and monitor metrics from your AI agents using the TelemetryService.

## Table of Contents

- [Overview](#overview)
- [Basic Usage](#basic-usage)
- [Metric Types](#metric-types)
- [Agent Metrics](#agent-metrics)
- [Custom Metrics](#custom-metrics)
- [Monitoring](#monitoring)
- [Best Practices](#best-practices)

## Overview

The TelemetryService provides comprehensive metrics collection for monitoring agent performance, resource usage, and business metrics.

**Features:**
- Three metric types: Counters, Gauges, Histograms
- Integration with existing Metrics class
- Automatic agent request tracking
- Metric attributes/tags
- OpenTelemetry ready

## Basic Usage

### Setup

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Telemetry\TelemetryServiceFactory;

$manager = ServiceManager::getInstance();
$manager->registerFactory(new TelemetryServiceFactory());

$telemetry = $manager->get(ServiceType::TELEMETRY);
```

### Enable Telemetry

```php
// config/services.php
return [
    'telemetry' => [
        'enabled' => true,
        'otlp' => [
            'endpoint' => 'http://localhost:4318', // OpenTelemetry endpoint
        ],
    ],
];
```

## Metric Types

### Counters

Cumulative metrics that only increase:

```php
// Basic counter
$telemetry->recordCounter('api.requests');

// Counter with value
$telemetry->recordCounter('api.requests', 5);

// Counter with attributes
$telemetry->recordCounter('api.requests', 1, [
    'endpoint' => '/users',
    'method' => 'POST',
    'status' => 200,
]);
```

**Use Cases:**
- Request counts
- Error counts
- Event occurrences
- Operations completed

### Gauges

Metrics that can go up or down (current value):

```php
// Memory usage
$telemetry->recordGauge(
    'memory.usage.bytes',
    memory_get_usage(true)
);

// Active connections
$telemetry->recordGauge('connections.active', 42);

// Queue size
$telemetry->recordGauge('queue.size', $queueLength, [
    'queue' => 'high_priority',
]);
```

**Use Cases:**
- Memory usage
- CPU usage
- Active connections
- Queue lengths
- Temperature values

### Histograms

Track distribution of values:

```php
// Request duration
$telemetry->recordHistogram('request.duration.ms', $duration);

// Response size
$telemetry->recordHistogram('response.size.bytes', $responseSize);

// Token usage
$telemetry->recordHistogram('llm.tokens.total', $tokens, [
    'model' => 'claude-opus-4-5',
]);
```

**Use Cases:**
- Latencies
- Response sizes
- Processing times
- Token counts

## Agent Metrics

### Recording Agent Requests

```php
// Simple request tracking
$telemetry->recordAgentRequest(
    success: true,
    tokensInput: 100,
    tokensOutput: 50,
    duration: 1250.5
);

// Failed request
$telemetry->recordAgentRequest(
    success: false,
    tokensInput: 0,
    tokensOutput: 0,
    duration: 500.0,
    error: 'Rate limit exceeded'
);
```

### Complete Agent Integration

```php
class MeteredAgent
{
    private TelemetryService $telemetry;
    
    public function run(string $input): string
    {
        $startTime = microtime(true);
        
        try {
            // Execute agent
            $result = $this->execute($input);
            
            // Calculate metrics
            $duration = (microtime(true) - $startTime) * 1000;
            $tokensInput = $this->countTokens($input);
            $tokensOutput = $this->countTokens($result);
            
            // Record success
            $this->telemetry->recordAgentRequest(
                success: true,
                tokensInput: $tokensInput,
                tokensOutput: $tokensOutput,
                duration: $duration
            );
            
            // Additional custom metrics
            $this->telemetry->recordHistogram('agent.iterations', $this->iterations);
            
            return $result;
        } catch (\Exception $e) {
            // Record failure
            $duration = (microtime(true) - $startTime) * 1000;
            $this->telemetry->recordAgentRequest(
                success: false,
                tokensInput: 0,
                tokensOutput: 0,
                duration: $duration,
                error: $e->getMessage()
            );
            
            throw $e;
        }
    }
}
```

### Summary Reports

```php
// Get summary
$summary = $telemetry->getSummary();

echo "Total Requests: {$summary['total_requests']}\n";
echo "Success Rate: " . ($summary['success_rate'] * 100) . "%\n";
echo "Total Tokens: {$summary['total_tokens']['total']}\n";
echo "  Input: {$summary['total_tokens']['input']}\n";
echo "  Output: {$summary['total_tokens']['output']}\n";
echo "Average Duration: " . round($summary['average_duration_ms'], 2) . "ms\n";

if (!empty($summary['error_counts'])) {
    echo "\nErrors:\n";
    foreach ($summary['error_counts'] as $type => $count) {
        echo "  {$type}: {$count}\n";
    }
}
```

## Custom Metrics

### Business Metrics

```php
class BusinessMetrics
{
    private TelemetryService $telemetry;
    
    public function recordUserSignup(): void
    {
        $this->telemetry->recordCounter('users.signups');
    }
    
    public function recordPurchase(float $amount, string $product): void
    {
        $this->telemetry->recordCounter('sales.transactions');
        $this->telemetry->recordHistogram('sales.amount', $amount, [
            'product' => $product,
        ]);
    }
    
    public function recordFeatureUsage(string $feature): void
    {
        $this->telemetry->recordCounter('features.usage', 1, [
            'feature' => $feature,
        ]);
    }
}
```

### Performance Metrics

```php
class PerformanceMonitor
{
    private TelemetryService $telemetry;
    
    public function measureOperation(string $name, callable $operation): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        try {
            $result = $operation();
            
            // Record duration
            $duration = (microtime(true) - $startTime) * 1000;
            $this->telemetry->recordHistogram(
                "operation.{$name}.duration.ms",
                $duration
            );
            
            // Record memory delta
            $memoryDelta = memory_get_usage(true) - $startMemory;
            $this->telemetry->recordHistogram(
                "operation.{$name}.memory.bytes",
                $memoryDelta
            );
            
            // Record success
            $this->telemetry->recordCounter("operation.{$name}.success");
            
            return $result;
        } catch (\Exception $e) {
            // Record failure
            $this->telemetry->recordCounter("operation.{$name}.failure");
            throw $e;
        }
    }
}

// Usage
$monitor = new PerformanceMonitor($telemetry);

$result = $monitor->measureOperation('data_processing', function() {
    // Expensive operation
    return processData();
});
```

### System Metrics

```php
class SystemMetrics
{
    private TelemetryService $telemetry;
    
    public function recordSystemMetrics(): void
    {
        // Memory
        $this->telemetry->recordGauge(
            'system.memory.usage.bytes',
            memory_get_usage(true)
        );
        
        // Peak memory
        $this->telemetry->recordGauge(
            'system.memory.peak.bytes',
            memory_get_peak_usage(true)
        );
        
        // Load average (Linux)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $this->telemetry->recordGauge('system.load.1min', $load[0]);
            $this->telemetry->recordGauge('system.load.5min', $load[1]);
            $this->telemetry->recordGauge('system.load.15min', $load[2]);
        }
    }
}
```

## Monitoring

### Real-Time Monitoring

```php
class RealtimeMonitor
{
    private TelemetryService $telemetry;
    
    public function displayMetrics(): void
    {
        while (true) {
            // Clear screen
            system('clear');
            
            echo "=== Agent Metrics Dashboard ===\n\n";
            
            $summary = $this->telemetry->getSummary();
            
            echo "Requests:\n";
            echo "  Total: {$summary['total_requests']}\n";
            echo "  Success: {$summary['successful_requests']}\n";
            echo "  Failed: {$summary['failed_requests']}\n";
            echo "  Success Rate: " . ($summary['success_rate'] * 100) . "%\n\n";
            
            echo "Tokens:\n";
            echo "  Total: {$summary['total_tokens']['total']}\n";
            echo "  Input: {$summary['total_tokens']['input']}\n";
            echo "  Output: {$summary['total_tokens']['output']}\n\n";
            
            echo "Performance:\n";
            echo "  Avg Duration: " . round($summary['average_duration_ms'], 2) . "ms\n";
            
            $allMetrics = $this->telemetry->getAllMetrics();
            
            echo "\nCounters:\n";
            foreach ($allMetrics['counters'] as $name => $value) {
                echo "  {$name}: {$value}\n";
            }
            
            sleep(5); // Refresh every 5 seconds
        }
    }
}
```

### Alerting on Metrics

```php
class MetricAlerting
{
    private TelemetryService $telemetry;
    
    public function checkThresholds(): array
    {
        $alerts = [];
        $summary = $this->telemetry->getSummary();
        
        // Alert on low success rate
        if ($summary['success_rate'] < 0.95) {
            $alerts[] = [
                'level' => 'warning',
                'message' => 'Success rate below 95%',
                'value' => $summary['success_rate'],
            ];
        }
        
        // Alert on high error rate
        if ($summary['failed_requests'] > 100) {
            $alerts[] = [
                'level' => 'critical',
                'message' => 'Too many failed requests',
                'value' => $summary['failed_requests'],
            ];
        }
        
        // Alert on high average duration
        if ($summary['average_duration_ms'] > 5000) {
            $alerts[] = [
                'level' => 'warning',
                'message' => 'Average duration above 5s',
                'value' => $summary['average_duration_ms'],
            ];
        }
        
        return $alerts;
    }
}
```

## Best Practices

### 1. Use Consistent Naming

```php
// ✅ Good - Consistent naming convention
$telemetry->recordCounter('agent.requests.total');
$telemetry->recordCounter('agent.requests.success');
$telemetry->recordCounter('agent.requests.failed');
$telemetry->recordHistogram('agent.duration.ms', $duration);
$telemetry->recordHistogram('agent.tokens.input', $tokens);

// ❌ Bad - Inconsistent naming
$telemetry->recordCounter('requests');
$telemetry->recordCounter('successful_req');
$telemetry->recordHistogram('time', $duration);
```

### 2. Add Meaningful Attributes

```php
// ✅ Good - Rich attributes
$telemetry->recordCounter('api.requests', 1, [
    'endpoint' => '/users',
    'method' => 'GET',
    'status' => 200,
    'user_tier' => 'premium',
]);

// ❌ Bad - No context
$telemetry->recordCounter('requests', 1);
```

### 3. Monitor Key Performance Indicators

```php
class KPIMonitoring
{
    private TelemetryService $telemetry;
    
    public function recordKPIs(array $kpis): void
    {
        // Response time
        $this->telemetry->recordHistogram('kpi.response_time.ms', $kpis['response_time']);
        
        // Throughput
        $this->telemetry->recordGauge('kpi.throughput.rps', $kpis['requests_per_second']);
        
        // Error rate
        $this->telemetry->recordGauge('kpi.error_rate', $kpis['error_rate']);
        
        // Availability
        $this->telemetry->recordGauge('kpi.availability', $kpis['uptime_percentage']);
    }
}
```

### 4. Reset Periodically

```php
// Reset metrics daily
function resetDailyMetrics(): void
{
    $telemetry = ServiceManager::getInstance()->get(ServiceType::TELEMETRY);
    
    // Archive current metrics
    $summary = $telemetry->getSummary();
    file_put_contents(
        "metrics-" . date('Y-m-d') . ".json",
        json_encode($summary, JSON_PRETTY_PRINT)
    );
    
    // Reset for new day
    $telemetry->reset();
}
```

### 5. Flush Before Shutdown

```php
// Ensure metrics are exported
register_shutdown_function(function() {
    $telemetry = ServiceManager::getInstance()->get(ServiceType::TELEMETRY);
    $telemetry->flush();
});
```

## Summary

You've learned:

✅ Three metric types (counters, gauges, histograms)  
✅ Recording agent performance metrics  
✅ Custom business metrics  
✅ Real-time monitoring  
✅ Alerting on thresholds  
✅ Best practices for production  

Combine with [TracingService](Services_Tracing.md) for complete observability!
