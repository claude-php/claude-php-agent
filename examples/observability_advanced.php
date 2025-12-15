<?php

/**
 * Advanced Observability Example
 *
 * Demonstrates advanced observability features:
 * - ObservabilityObserver for automatic tracking
 * - MetricsAggregator with histograms and percentiles
 * - CostTracker with budgets and alerts
 * - HealthCheck system
 * - Export to various formats
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Observability\ObservabilityObserver;
use ClaudeAgents\Observability\MetricsAggregator;
use ClaudeAgents\Observability\CostTracker;
use ClaudeAgents\Observability\HealthCheck;
use ClaudeAgents\Observability\Exporters\JsonExporter;
use ClaudeAgents\Observability\Exporters\PrometheusExporter;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Initialize observability stack
$observer = new ObservabilityObserver();
$aggregator = new MetricsAggregator();
$costTracker = new CostTracker();
$healthCheck = HealthCheck::createDefault();

// Set up cost budget and alerts
$costTracker->setBudget(0.10); // 10 cents budget
$costTracker->setAlertThresholds([0.5, 0.75, 0.9, 1.0]);
$costTracker->onAlert(function ($totalCost, $budget, $threshold) {
    $percent = $threshold * 100;
    echo "⚠️  Budget Alert: {$percent}% threshold reached!\n";
    echo "   Total Cost: " . \ClaudeAgents\Observability\CostEstimator::formatCost($totalCost) . "\n";
    echo "   Budget: " . \ClaudeAgents\Observability\CostEstimator::formatCost($budget) . "\n\n";
});

// Add custom health checks
$healthCheck->registerCheck('agent_ready', function () {
    $apiKey = getenv('ANTHROPIC_API_KEY');
    if (empty($apiKey)) {
        return [
            'status' => HealthCheck::STATUS_UNHEALTHY,
            'message' => 'ANTHROPIC_API_KEY not set',
        ];
    }

    return [
        'status' => HealthCheck::STATUS_HEALTHY,
        'message' => 'API key configured',
    ];
});

// Check system health
echo "🏥 System Health Check\n";
echo str_repeat('=', 50) . "\n";

$health = $healthCheck->check();
echo "Overall Status: {$health['status']}\n\n";

foreach ($health['checks'] as $name => $check) {
    $icon = match ($check['status']) {
        'healthy' => '✅',
        'degraded' => '⚠️',
        'unhealthy' => '❌',
    };
    echo "{$icon} {$name}: {$check['message']}\n";
}

if (!$healthCheck->isHealthy()) {
    echo "\n⚠️  System health issues detected. Proceed with caution.\n";
}

echo "\n" . str_repeat('=', 50) . "\n\n";

// Initialize agent
$client = new ClaudePhp(getenv('ANTHROPIC_API_KEY'));

$agent = Agent::create($client)
    ->withSystemPrompt('You are a helpful research assistant')
    ->withTool(new Tool(
        name: 'search',
        description: 'Search for information',
        parameters: ['query' => ['type' => 'string']],
        handler: function (array $input) use ($aggregator, $costTracker): array {
            // Track tool execution
            $start = microtime(true);

            // Simulate search
            usleep(random_int(10000, 50000));
            $results = ["Result for: {$input['query']}"];

            // Record metrics
            $duration = (microtime(true) - $start) * 1000;
            $aggregator->recordDuration($duration);

            return ['results' => $results];
        }
    ));

// Set up iteration callback to track with observer
$agent->onIteration(function ($iteration, $response, $context) use ($observer, $aggregator, $costTracker) {
    // Simulate observer events
    $observer->update('iteration.start', ['iteration' => $iteration]);

    if (isset($response->usage)) {
        // Record in aggregator
        $aggregator->recordTokens($response->usage->inputTokens + $response->usage->outputTokens);

        // Track cost
        $costTracker->record(
            'claude-sonnet-4-5',
            $response->usage->inputTokens ?? 0,
            $response->usage->outputTokens ?? 0,
            'agent'
        );
    }

    $observer->update('iteration.complete', ['iteration' => $iteration]);
});

// Execute multiple agent runs to generate observability data
echo "🚀 Running agents...\n\n";

$tasks = [
    'What is machine learning?',
    'Explain quantum computing',
    'What is the Pythagorean theorem?',
];

foreach ($tasks as $i => $task) {
    echo "Task " . ($i + 1) . ": {$task}\n";

    $start = microtime(true);
    $aggregator->recordRequest(true);

    try {
        $result = $agent->run($task);
        $duration = (microtime(true) - $start) * 1000;

        $aggregator->recordDuration($duration);
        echo "✅ Completed in " . number_format($duration, 2) . "ms\n\n";
    } catch (\Exception $e) {
        $aggregator->recordRequest(false);
        echo "❌ Error: {$e->getMessage()}\n\n";
    }
}

// Display comprehensive metrics
echo str_repeat('=', 50) . "\n";
echo "📊 Comprehensive Metrics Report\n";
echo str_repeat('=', 50) . "\n\n";

// Aggregated metrics
$summary = $aggregator->getSummary();

echo "⏱️  Performance Metrics:\n";
echo "  Duration Stats:\n";
echo "    Count: {$summary['duration']['count']}\n";
echo "    Min: " . number_format($summary['duration']['min'], 2) . "ms\n";
echo "    Max: " . number_format($summary['duration']['max'], 2) . "ms\n";
echo "    Mean: " . number_format($summary['duration']['mean'], 2) . "ms\n";
echo "    Median: " . number_format($summary['duration']['median'], 2) . "ms\n";
echo "    Std Dev: " . number_format($summary['duration']['stddev'], 2) . "ms\n\n";

echo "  Percentiles:\n";
foreach ($summary['duration']['percentiles'] as $p => $value) {
    echo "    {$p}: " . number_format($value, 2) . "ms\n";
}
echo "\n";

echo "  Request Rate: " . number_format($summary['rate']['requests_per_second'], 2) . " req/s\n";
echo "  Success Rate: " . number_format($summary['rate']['success_rate_percent'], 1) . "%\n\n";

// Token usage
echo "🎫 Token Usage:\n";
echo "  Total Tokens: {$summary['tokens']['total']}\n";
echo "  Average per Request: " . number_format($summary['tokens']['mean'], 0) . "\n\n";

// Cost tracking
$costSummary = $costTracker->getSummary();

echo "💰 Cost Analysis:\n";
echo "  Total Cost: {$costSummary['total_cost_formatted']}\n";
echo "  Budget Status:\n";
echo "    Limit: $" . number_format($costSummary['budget']['limit'], 4) . "\n";
echo "    Remaining: $" . number_format($costSummary['budget']['remaining'], 4) . "\n";
echo "    Usage: " . number_format($costSummary['budget']['usage_percent'], 1) . "%\n";
echo "    Exceeded: " . ($costSummary['budget']['exceeded'] ? 'Yes ❌' : 'No ✅') . "\n\n";

echo "  By Model:\n";
foreach ($costSummary['by_model'] as $model => $cost) {
    echo "    {$model}: " . \ClaudeAgents\Observability\CostEstimator::formatCost($cost) . "\n";
}
echo "\n";

// Export data
echo "📤 Exporting Data...\n\n";

// Export to JSON
$jsonExporter = new JsonExporter('/tmp/observability_metrics.json');
if ($jsonExporter->export($summary)) {
    echo "✅ Exported to JSON: {$jsonExporter->getOutputPath()}\n";
}

// Export to Prometheus format
$promExporter = new PrometheusExporter('/tmp/observability_metrics.prom');
if ($promExporter->export($summary)) {
    echo "✅ Exported to Prometheus: {$promExporter->getOutputPath()}\n";
}

// Export cost data to CSV
$csvPath = '/tmp/observability_costs.csv';
file_put_contents($csvPath, $costTracker->toCsv());
echo "✅ Exported costs to CSV: {$csvPath}\n";

echo "\n✨ Observability demo complete!\n";

