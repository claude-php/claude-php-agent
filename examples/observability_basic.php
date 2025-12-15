<?php

/**
 * Basic Observability Example
 *
 * Demonstrates basic usage of tracing, metrics, and cost estimation
 * in agent execution.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Observability\Tracer;
use ClaudeAgents\Observability\Metrics;
use ClaudeAgents\Observability\CostEstimator;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Initialize observability components
$tracer = new Tracer();
$metrics = new Metrics();
$estimator = new CostEstimator();

// Start a trace
$tracer->startTrace();

// Initialize Claude client and agent
$client = new ClaudePhp(getenv('ANTHROPIC_API_KEY'));

$agent = Agent::create($client)
    ->withSystemPrompt('You are a helpful assistant')
    ->withTool(new Tool(
        name: 'calculator',
        description: 'Perform basic math operations',
        parameters: [
            'operation' => ['type' => 'string', 'enum' => ['add', 'subtract', 'multiply', 'divide']],
            'a' => ['type' => 'number'],
            'b' => ['type' => 'number'],
        ],
        handler: function (array $input): array {
            $result = match ($input['operation']) {
                'add' => $input['a'] + $input['b'],
                'subtract' => $input['a'] - $input['b'],
                'multiply' => $input['a'] * $input['b'],
                'divide' => $input['a'] / $input['b'],
            };

            return ['result' => $result];
        }
    ));

// Set up callbacks to track execution
$agent->onIteration(function ($iteration, $response, $context) use ($tracer, $metrics) {
    // Start a span for this iteration
    $span = $tracer->startSpan("iteration.{$iteration}");

    // Record metrics if we have token usage
    if (isset($response->usage)) {
        $metrics->recordRequest(
            success: true,
            tokensInput: $response->usage->inputTokens ?? 0,
            tokensOutput: $response->usage->outputTokens ?? 0,
            duration: $span->getDuration()
        );
    }

    $tracer->endSpan($span);
});

// Execute the agent
echo "🚀 Running agent with observability...\n\n";

$span = $tracer->startSpan('agent.execution');

try {
    $result = $agent->run('What is 157 multiplied by 23?');

    $span->setStatus('OK');
    echo "✅ Result: {$result->getAnswer()}\n\n";
} catch (\Exception $e) {
    $span->setStatus('ERROR', $e->getMessage());
    echo "❌ Error: {$e->getMessage()}\n\n";
}

$tracer->endSpan($span);

// End the trace
$tracer->endTrace();

// Display observability data
echo "📊 Observability Summary\n";
echo str_repeat('=', 50) . "\n\n";

// Trace information
$traceData = $tracer->toArray();
echo "🔍 Tracing:\n";
echo "  Trace ID: {$traceData['trace_id']}\n";
echo "  Total Spans: {$traceData['span_count']}\n";
echo "  Total Duration: " . number_format($traceData['total_duration'], 2) . " ms\n\n";

// Display span tree
echo "  Span Tree:\n";
$tree = $tracer->buildSpanTree();
displaySpanTree($tree, 4);
echo "\n";

// Metrics summary
$metricsSummary = $metrics->getSummary();
echo "📈 Metrics:\n";
echo "  Total Requests: {$metricsSummary['total_requests']}\n";
echo "  Success Rate: " . number_format($metricsSummary['success_rate'] * 100, 1) . "%\n";
echo "  Total Tokens:\n";
echo "    Input: {$metricsSummary['total_tokens']['input']}\n";
echo "    Output: {$metricsSummary['total_tokens']['output']}\n";
echo "    Total: {$metricsSummary['total_tokens']['total']}\n";
echo "  Average Duration: " . number_format($metricsSummary['average_duration_ms'], 2) . " ms\n\n";

// Cost estimation
if ($metricsSummary['total_tokens']['total'] > 0) {
    $cost = $estimator->estimateCost(
        'claude-sonnet-4-5',
        $metricsSummary['total_tokens']['input'],
        $metricsSummary['total_tokens']['output']
    );

    echo "💰 Cost Estimation:\n";
    echo "  Model: claude-sonnet-4-5\n";
    echo "  Estimated Cost: " . CostEstimator::formatCost($cost) . "\n";
}

/**
 * Helper function to display span tree
 */
function displaySpanTree(array $tree, int $indent = 0): void
{
    foreach ($tree as $node) {
        $span = $node['span'];
        $prefix = str_repeat(' ', $indent) . '└─ ';

        echo $prefix . $span->getName();
        echo ' (' . number_format($span->getDuration(), 2) . 'ms)';
        echo ' [' . $span->getStatus() . "]\n";

        if (!empty($node['children'])) {
            displaySpanTree($node['children'], $indent + 2);
        }
    }
}

