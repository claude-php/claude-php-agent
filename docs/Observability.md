# Observability

Comprehensive observability system for monitoring, tracing, and analyzing Claude agent execution.

## Table of Contents

- [Overview](#overview)
- [Components](#components)
- [Quick Start](#quick-start)
- [Tracing](#tracing)
- [Metrics](#metrics)
- [Cost Tracking](#cost-tracking)
- [Health Checks](#health-checks)
- [Exporters](#exporters)
- [Integration](#integration)
- [Best Practices](#best-practices)

## Overview

The observability system provides production-ready monitoring and debugging capabilities including:

- **Distributed Tracing**: Track execution flow with parent-child span relationships
- **Metrics Collection**: Aggregate performance metrics with histograms and percentiles
- **Cost Tracking**: Monitor API costs with budgets and alerts
- **Health Checks**: System health monitoring and status reporting
- **Multiple Export Formats**: OpenTelemetry, Prometheus, JSON, CSV
- **Structured Logging**: Correlation between logs and traces

## Components

### Core Classes

| Class | Purpose |
|-------|---------|
| `Tracer` | Distributed tracing with span management |
| `Span` | Individual trace spans with events and attributes |
| `Metrics` | Basic metrics collection |
| `MetricsAggregator` | Advanced metrics with histograms and percentiles |
| `CostEstimator` | Token cost calculation |
| `CostTracker` | Cost tracking with budgets |
| `HealthCheck` | System health monitoring |
| `ObservabilityObserver` | Automatic observability via Observer pattern |
| `ObservabilityLogger` | Structured logging with trace context |

### Exporters

| Exporter | Format | Use Case |
|----------|--------|----------|
| `JsonExporter` | JSON | File-based export, archiving |
| `PrometheusExporter` | Prometheus | Monitoring dashboards |
| `OpenTelemetryExporter` | OTLP | Jaeger, Zipkin, DataDog |

## Quick Start

### Basic Usage

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Progress\AgentUpdate;
use ClaudeAgents\Observability\Tracer;
use ClaudeAgents\Observability\Metrics;
use ClaudeAgents\Observability\CostEstimator;

// Initialize observability
$tracer = new Tracer();
$metrics = new Metrics();
$estimator = new CostEstimator();

// Start tracing
$tracer->startTrace();
$span = $tracer->startSpan('agent.execution');

// Run your agent
$agent = Agent::create($client)
    ->withSystemPrompt('You are helpful')
    ->onIteration(function ($i, $response, $context) use ($metrics) {
        if (isset($response->usage)) {
            $metrics->recordRequest(
                success: true,
                tokensInput: $response->usage->inputTokens ?? 0,
                tokensOutput: $response->usage->outputTokens ?? 0,
                duration: 123.45
            );
        }
    });

$result = $agent->run('Hello!');

// End tracing
$tracer->endSpan($span);
$tracer->endTrace();

// Get results
$traceData = $tracer->toArray();
$metricsSummary = $metrics->getSummary();
$cost = $estimator->estimateCost(
    'claude-sonnet-4-5',
    $metricsSummary['total_tokens']['input'],
    $metricsSummary['total_tokens']['output']
);

echo "Total Duration: {$traceData['total_duration']}ms\n";
echo "Total Cost: " . CostEstimator::formatCost($cost) . "\n";
```

### Unified Progress Stream (Recommended)

If you want a single hook for observability (start/end, iterations, tool executions, streaming deltas), use `Agent::onUpdate()`:

```php
$agent = Agent::create($client)
    ->onUpdate(function (AgentUpdate $update) use ($tracer, $metrics): void {
        $type = $update->getType();
        $data = $update->getData();

        // Example: record a high-level success/failure metric at the end
        if ($type === 'agent.completed') {
            $usage = $data['token_usage'] ?? ['input' => 0, 'output' => 0];
            $metrics->recordRequest(
                success: true,
                tokensInput: (int) ($usage['input'] ?? 0),
                tokensOutput: (int) ($usage['output'] ?? 0),
                duration: 0
            );
        }

        if ($type === 'agent.failed') {
            $usage = $data['token_usage'] ?? ['input' => 0, 'output' => 0];
            $metrics->recordRequest(
                success: false,
                tokensInput: (int) ($usage['input'] ?? 0),
                tokensOutput: (int) ($usage['output'] ?? 0),
                duration: 0,
                error: (string) ($data['error'] ?? 'unknown_error')
            );
        }
    });
```

### Automatic Observability

Use `ObservabilityObserver` for automatic tracking:

```php
use ClaudeAgents\Observability\ObservabilityObserver;

$observer = new ObservabilityObserver();

// Observer automatically tracks:
// - Agent start/complete/error
// - Iteration lifecycle
// - Tool execution
// - LLM requests
// - Chain execution

// Get collected data
$tracer = $observer->getTracer();
$metrics = $observer->getMetrics();
$totalCost = $observer->getSessionCost();
```

## Tracing

### Creating Spans

```php
$tracer = new Tracer();

// Start a trace
$traceId = $tracer->startTrace();

// Create a root span
$rootSpan = $tracer->startSpan('operation', [
    'user_id' => 123,
    'operation_type' => 'query',
]);

// Create child span
$childSpan = $tracer->startSpan('sub-operation', [
    'query' => 'SELECT * FROM users',
], $rootSpan);

// Add events
$childSpan->addEvent('cache.miss', ['key' => 'user:123']);

// Set status
$childSpan->setStatus('OK');

// End spans
$tracer->endSpan($childSpan);
$tracer->endSpan($rootSpan);

// End trace
$tracer->endTrace();
```

### Span Attributes

```php
$span = $tracer->startSpan('http.request');

// Set attributes
$span->setAttribute('http.method', 'POST');
$span->setAttribute('http.url', 'https://api.example.com');
$span->setAttribute('http.status_code', 200);

// Or set multiple at once
$span->setAttributes([
    'user.id' => 123,
    'user.role' => 'admin',
]);
```

### Span Events

Track significant moments within a span:

```php
$span = $tracer->startSpan('database.query');

$span->addEvent('query.started', ['query' => 'SELECT ...']);
$span->addEvent('query.completed', ['rows_returned' => 42]);
$span->addEvent('cache.updated', ['cache_key' => 'result:123']);

$tracer->endSpan($span);
```

### Span Status

```php
try {
    $span = $tracer->startSpan('operation');
    
    // ... do work ...
    
    $span->setStatus('OK');
} catch (\Exception $e) {
    $span->setStatus('ERROR', $e->getMessage());
} finally {
    $tracer->endSpan($span);
}
```

### Span Hierarchy

Build a tree of related spans:

```php
$tracer = new Tracer();
$tracer->startTrace();

$root = $tracer->startSpan('agent.run');
  $iter1 = $tracer->startSpan('iteration.1', [], $root);
    $tool1 = $tracer->startSpan('tool.calculator', [], $iter1);
    $tracer->endSpan($tool1);
  $tracer->endSpan($iter1);
$tracer->endSpan($root);

// Build tree structure
$tree = $tracer->buildSpanTree();
// Returns: [
//   ['span' => $root, 'children' => [
//     ['span' => $iter1, 'children' => [
//       ['span' => $tool1, 'children' => []]
//     ]]
//   ]]
// ]
```

## Metrics

### Basic Metrics

```php
use ClaudeAgents\Observability\Metrics;

$metrics = new Metrics();

// Record requests
$metrics->recordRequest(
    success: true,
    tokensInput: 100,
    tokensOutput: 50,
    duration: 1234.5,
    error: null
);

// Get summary
$summary = $metrics->getSummary();
echo "Success Rate: {$summary['success_rate']}\n";
echo "Total Tokens: {$summary['total_tokens']['total']}\n";
echo "Avg Duration: {$summary['average_duration_ms']}ms\n";
```

### Advanced Metrics with Aggregation

```php
use ClaudeAgents\Observability\MetricsAggregator;

$aggregator = new MetricsAggregator();

// Record durations
$aggregator->recordDuration(123.45);
$aggregator->recordDuration(234.56);
$aggregator->recordDuration(345.67);

// Get statistics
$stats = $aggregator->getDurationStats();
echo "Mean: {$stats['mean']}ms\n";
echo "Median: {$stats['median']}ms\n";
echo "Std Dev: {$stats['stddev']}ms\n";

// Get percentiles
$percentiles = $aggregator->getDurationPercentiles([50, 95, 99]);
echo "p50: {$percentiles['p50']}ms\n";
echo "p95: {$percentiles['p95']}ms\n";
echo "p99: {$percentiles['p99']}ms\n";

// Get histogram
$histogram = $aggregator->getDurationHistogram([10, 50, 100, 500]);
// Returns bucket counts for: <=10ms, <=50ms, <=100ms, <=500ms, >500ms
```

### Counters and Gauges

```php
// Increment counters
$aggregator->incrementCounter('api.requests');
$aggregator->incrementCounter('api.errors', 5);

// Set gauge values
$aggregator->setGauge('memory_usage_mb', 512.5);
$aggregator->setGauge('active_connections', 42);

// Get values
$requests = $aggregator->getCounter('api.requests');
$memory = $aggregator->getGauge('memory_usage_mb');
```

### Request Rate

```php
$aggregator = new MetricsAggregator(timeWindow: 60); // 60 second window

$aggregator->recordRequest(true);
$aggregator->recordRequest(true);
$aggregator->recordRequest(false);

$rate = $aggregator->getRequestRate(); // requests per second
$successRate = $aggregator->getSuccessRate(); // percentage
```

## Cost Tracking

### Basic Cost Estimation

```php
use ClaudeAgents\Observability\CostEstimator;

$estimator = new CostEstimator();

// Estimate cost
$cost = $estimator->estimateCost('claude-sonnet-4-5', 1000, 500);
echo CostEstimator::formatCost($cost); // "$0.0105"

// Get pricing
$pricing = $estimator->getPricing('claude-sonnet-4-5');
// Returns: ['input' => 3.0, 'output' => 15.0] (per 1M tokens)

// Supported models
$models = $estimator->getSupportedModels();
```

### Advanced Cost Tracking

```php
use ClaudeAgents\Observability\CostTracker;

$tracker = new CostTracker();

// Set budget
$tracker->setBudget(10.0); // $10 budget

// Set alert thresholds (50%, 75%, 90%, 100%)
$tracker->setAlertThresholds([0.5, 0.75, 0.9, 1.0]);

// Add alert callback
$tracker->onAlert(function ($totalCost, $budget, $threshold) {
    echo "Alert: " . ($threshold * 100) . "% of budget used!\n";
    echo "Total cost: $" . number_format($totalCost, 4) . "\n";
});

// Record costs
$tracker->record('claude-sonnet-4-5', 1000, 500, 'agent');
$tracker->record('claude-haiku', 500, 250, 'tool');

// Get summary
$summary = $tracker->getSummary();
echo "Total Cost: {$summary['total_cost_formatted']}\n";
echo "Remaining: $" . number_format($summary['budget']['remaining'], 4) . "\n";
echo "Usage: {$summary['budget']['usage_percent']}%\n";

// Get cost by model
$costsByModel = $tracker->getCostsByModel();
foreach ($costsByModel as $model => $cost) {
    echo "{$model}: " . CostEstimator::formatCost($cost) . "\n";
}

// Get cost by category
$costsByCategory = $tracker->getCostsByCategory();
// ['agent' => 0.0105, 'tool' => 0.00031]

// Export to CSV
file_put_contents('costs.csv', $tracker->toCsv());
```

## Health Checks

### Basic Health Checks

```php
use ClaudeAgents\Observability\HealthCheck;

$health = new HealthCheck();

// Register a check
$health->registerCheck('database', function () {
    try {
        // Check database connection
        $pdo = new PDO('...');
        return [
            'status' => HealthCheck::STATUS_HEALTHY,
            'message' => 'Database connected',
        ];
    } catch (\Exception $e) {
        return [
            'status' => HealthCheck::STATUS_UNHEALTHY,
            'message' => "Database error: {$e->getMessage()}",
        ];
    }
});

// Run checks
$result = $health->check();
echo "Overall: {$result['status']}\n";

foreach ($result['checks'] as $name => $check) {
    echo "{$name}: {$check['status']} - {$check['message']}\n";
}

// Quick status check
if ($health->isHealthy()) {
    echo "All systems operational\n";
}
```

### Default Health Checks

```php
// Create with default checks (PHP memory, disk space, PHP version)
$health = HealthCheck::createDefault();

$result = $health->check();
// Includes: php_memory, disk_space, php_version
```

### Health Check Status

Three status levels:

- `HealthCheck::STATUS_HEALTHY` - All systems operational
- `HealthCheck::STATUS_DEGRADED` - Partial functionality
- `HealthCheck::STATUS_UNHEALTHY` - Critical issues

```php
$health->registerCheck('api', function () {
    $latency = checkApiLatency();
    
    if ($latency < 100) {
        return [
            'status' => HealthCheck::STATUS_HEALTHY,
            'message' => "API latency: {$latency}ms",
        ];
    } elseif ($latency < 500) {
        return [
            'status' => HealthCheck::STATUS_DEGRADED,
            'message' => "API slow: {$latency}ms",
        ];
    } else {
        return [
            'status' => HealthCheck::STATUS_UNHEALTHY,
            'message' => "API timeout: {$latency}ms",
        ];
    }
});
```

### Caching

Health checks are cached to avoid overhead:

```php
$health = new HealthCheck(cacheTtl: 30); // Cache for 30 seconds

$health->check(); // Runs checks
$health->check(); // Uses cache
$health->check(useCache: false); // Forces re-check

// Clear cache manually
$health->clearCache();
$health->clearCache('specific_check');
```

## Exporters

### JSON Exporter

```php
use ClaudeAgents\Observability\Exporters\JsonExporter;

$exporter = new JsonExporter('/path/to/output.json', prettyPrint: true);

$data = $aggregator->getSummary();
$exporter->export($data);
```

### Prometheus Exporter

```php
use ClaudeAgents\Observability\Exporters\PrometheusExporter;

$exporter = new PrometheusExporter(
    outputPath: '/var/lib/prometheus/metrics.prom',
    prefix: 'claude_agent'
);

$exporter->export($aggregator->getSummary());
```

Output format:
```
# TYPE claude_agent_requests_per_second gauge
claude_agent_requests_per_second 2.5 1234567890000
# TYPE claude_agent_duration_ms histogram
claude_agent_duration_ms_bucket{le="50"} 10 1234567890000
claude_agent_duration_ms_bucket{le="100"} 25 1234567890000
claude_agent_duration_ms_sum 2500 1234567890000
claude_agent_duration_ms_count 30 1234567890000
```

### OpenTelemetry Exporter

```php
use ClaudeAgents\Observability\Exporters\OpenTelemetryExporter;

// Export to Jaeger, Zipkin, DataDog, etc.
$exporter = new OpenTelemetryExporter(
    endpoint: 'http://localhost:4318/v1/traces',
    headers: ['Authorization' => 'Bearer YOUR_API_KEY'],
    timeout: 10
);

$otlpData = $tracer->toOpenTelemetry();
if ($exporter->export($otlpData)) {
    echo "Trace exported successfully!\n";
}
```

#### Running Jaeger

```bash
# Start Jaeger all-in-one
docker run -d \
  -p 16686:16686 \
  -p 4318:4318 \
  jaegertracing/all-in-one:latest

# View UI: http://localhost:16686
```

## Integration

### With Agent

```php
$agent = Agent::create($client)
    ->onIteration(function ($iteration, $response, $context) use ($tracer, $metrics) {
        $span = $tracer->startSpan("iteration.{$iteration}");
        
        if (isset($response->usage)) {
            $metrics->recordRequest(
                success: true,
                tokensInput: $response->usage->inputTokens ?? 0,
                tokensOutput: $response->usage->outputTokens ?? 0,
                duration: $span->getDuration()
            );
        }
        
        $tracer->endSpan($span);
    })
    ->onToolExecution(function ($tool, $input, $result) use ($tracer) {
        $span = $tracer->startSpan("tool.{$tool}");
        $span->setAttribute('tool.input', json_encode($input));
        $tracer->endSpan($span);
    });
```

### With Chains

```php
use ClaudeAgents\Chains\SequentialChain;

$chain = SequentialChain::create($client, 'research');

// Track chain execution
$chainSpan = $tracer->startSpan('chain.execute');
$result = $chain->run(['query' => 'machine learning']);
$tracer->endSpan($chainSpan);
```

### Structured Logging

```php
use ClaudeAgents\Observability\ObservabilityLogger;

$logger = new ObservabilityLogger($psrLogger, $tracer);

// Logs automatically include trace context
$logger->info('Agent started', ['task' => 'summarize']);
// Output includes: trace_id, span_id, timestamp, memory_usage

// Log events
$logger->logEvent('agent.completed', [
    'iterations' => 5,
    'cost' => 0.0105,
]);

// Log exceptions with context
try {
    // ...
} catch (\Exception $e) {
    $logger->logException($e, 'Agent execution failed');
}
```

## Best Practices

### 1. Always Use Traces for Production

```php
$tracer = new Tracer();
$traceId = $tracer->startTrace();

try {
    // Your agent code
} finally {
    $tracer->endTrace();
    
    // Export trace
    $exporter->export($tracer->toOpenTelemetry());
}
```

### 2. Set Budgets for Cost Control

```php
$tracker = new CostTracker();
$tracker->setBudget(5.0); // $5 limit
$tracker->onAlert(function ($cost, $budget, $threshold) {
    if ($threshold >= 1.0) {
        // Stop execution when budget exceeded
        throw new \RuntimeException('Budget exceeded!');
    }
});
```

### 3. Use Health Checks for Readiness

```php
$health = HealthCheck::createDefault();
$health->registerCheck('api_key', function () {
    return [
        'status' => getenv('ANTHROPIC_API_KEY') 
            ? HealthCheck::STATUS_HEALTHY 
            : HealthCheck::STATUS_UNHEALTHY,
        'message' => 'API key check',
    ];
});

if (!$health->isHealthy()) {
    die("System not ready\n");
}
```

### 4. Export Metrics Regularly

```php
// Set up periodic export (e.g., with a cron job)
$promExporter = new PrometheusExporter('/metrics/agent.prom');
$promExporter->export($aggregator->getSummary());

// Or use node_exporter textfile collector
$promExporter = new PrometheusExporter(
    '/var/lib/node_exporter/textfile_collector/claude_agent.prom'
);
```

### 5. Monitor Key Percentiles

```php
$percentiles = $aggregator->getDurationPercentiles([50, 90, 95, 99]);

if ($percentiles['p99'] > 5000) {
    // Alert: 99th percentile above 5 seconds
    alert('High latency detected');
}
```

### 6. Use Observer for Automatic Tracking

```php
// Instead of manual instrumentation everywhere
$observer = new ObservabilityObserver();

// Just attach to agent events
// Observer handles all the tracking automatically
```

### 7. Correlate Logs and Traces

```php
$logger = new ObservabilityLogger($psrLogger, $tracer);

// All logs include trace_id and span_id
// Easy to correlate in log aggregation tools
```

## Examples

See the `examples/` directory:

- `observability_basic.php` - Basic tracing, metrics, and cost tracking
- `observability_advanced.php` - Advanced features with exporters
- `observability_opentelemetry.php` - OpenTelemetry integration

## API Reference

See the PHPDoc comments in each class for detailed API documentation:

- `src/Observability/Tracer.php`
- `src/Observability/Span.php`
- `src/Observability/Metrics.php`
- `src/Observability/MetricsAggregator.php`
- `src/Observability/CostEstimator.php`
- `src/Observability/CostTracker.php`
- `src/Observability/HealthCheck.php`
- `src/Observability/ObservabilityObserver.php`
- `src/Observability/ObservabilityLogger.php`
- `src/Observability/Exporters/`

