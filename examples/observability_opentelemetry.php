<?php

/**
 * OpenTelemetry Integration Example
 *
 * Demonstrates how to export traces to OpenTelemetry-compatible backends
 * like Jaeger, Zipkin, or DataDog.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Observability\Tracer;
use ClaudeAgents\Observability\Exporters\OpenTelemetryExporter;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Initialize tracer
$tracer = new Tracer();

// Configure OpenTelemetry exporter
// By default, exports to http://localhost:4318/v1/traces (OTLP/HTTP)
// You can run Jaeger with: docker run -d -p 16686:16686 -p 4318:4318 jaegertracing/all-in-one:latest
$exporter = new OpenTelemetryExporter(
    endpoint: getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'http://localhost:4318/v1/traces',
    headers: [
        'Authorization' => 'Bearer ' . (getenv('OTEL_API_KEY') ?: ''),
    ],
    timeout: 10
);

echo "🔍 OpenTelemetry Tracing Example\n";
echo str_repeat('=', 50) . "\n\n";

// Start a trace
$traceId = $tracer->startTrace();
echo "Started Trace: {$traceId}\n\n";

// Initialize agent
$client = new ClaudePhp(getenv('ANTHROPIC_API_KEY'));

$agent = Agent::create($client)
    ->withSystemPrompt('You are a helpful assistant')
    ->withTool(new Tool(
        name: 'database_query',
        description: 'Query a database',
        parameters: ['query' => ['type' => 'string']],
        handler: function (array $input) use ($tracer): array {
            // Create a span for the database operation
            $span = $tracer->startSpan('db.query', [
                'db.system' => 'postgresql',
                'db.statement' => $input['query'],
            ]);

            // Simulate database query
            usleep(random_int(10000, 30000));

            $span->addEvent('query.executed', ['rows_affected' => 5]);
            $span->setStatus('OK');
            $tracer->endSpan($span);

            return ['result' => 'Query executed successfully'];
        }
    ));

// Wrap agent execution in a span
$rootSpan = $tracer->startSpan('agent.run', [
    'agent.type' => 'claude',
    'agent.model' => 'claude-sonnet-4-5',
]);

$agent->onIteration(function ($iteration, $response, $context) use ($tracer, $rootSpan) {
    // Create iteration span as child of root
    $iterSpan = $tracer->startSpan("iteration.{$iteration}", [
        'iteration.number' => $iteration,
    ], $rootSpan);

    // Add token usage event if available
    if (isset($response->usage)) {
        $iterSpan->addEvent('tokens.usage', [
            'input_tokens' => $response->usage->inputTokens ?? 0,
            'output_tokens' => $response->usage->outputTokens ?? 0,
        ]);
    }

    $iterSpan->setStatus('OK');
    $tracer->endSpan($iterSpan);
});

// Execute agent
echo "🤖 Running agent with distributed tracing...\n\n";

try {
    $result = $agent->run('Query the database for all users created today');

    $rootSpan->setStatus('OK');
    $rootSpan->setAttribute('result.success', true);
    $rootSpan->setAttribute('result.iterations', $result->getIterations());

    echo "✅ Agent completed successfully\n";
    echo "   Answer: {$result->getAnswer()}\n";
    echo "   Iterations: {$result->getIterations()}\n\n";
} catch (\Exception $e) {
    $rootSpan->setStatus('ERROR', $e->getMessage());
    $rootSpan->addEvent('error', [
        'exception.type' => get_class($e),
        'exception.message' => $e->getMessage(),
    ]);

    echo "❌ Error: {$e->getMessage()}\n\n";
}

$tracer->endSpan($rootSpan);
$tracer->endTrace();

// Display trace summary
echo "📊 Trace Summary:\n";
$traceData = $tracer->toArray();
echo "  Trace ID: {$traceData['trace_id']}\n";
echo "  Total Spans: {$traceData['span_count']}\n";
echo "  Total Duration: " . number_format($traceData['total_duration'], 2) . " ms\n\n";

// Display span tree
echo "  Span Hierarchy:\n";
$tree = $tracer->buildSpanTree();
displaySpanTree($tree, 4);
echo "\n";

// Export to OpenTelemetry
echo "📤 Exporting to OpenTelemetry...\n";

$otlpData = $tracer->toOpenTelemetry();
$success = $exporter->export($otlpData);

if ($success) {
    echo "✅ Successfully exported trace to {$exporter->getEndpoint()}\n";
    echo "   View trace: http://localhost:16686/trace/{$traceId}\n";
} else {
    echo "⚠️  Failed to export trace (is the OTLP endpoint running?)\n";
    echo "   Start Jaeger: docker run -d -p 16686:16686 -p 4318:4318 jaegertracing/all-in-one:latest\n";
}

echo "\n✨ OpenTelemetry tracing demo complete!\n";

/**
 * Helper function to display span tree with OpenTelemetry details
 */
function displaySpanTree(array $tree, int $indent = 0): void
{
    foreach ($tree as $node) {
        $span = $node['span'];
        $prefix = str_repeat(' ', $indent) . '└─ ';

        echo $prefix . $span->getName();
        echo ' (' . number_format($span->getDuration(), 2) . 'ms)';
        echo ' [' . $span->getStatus() . ']';
        echo ' [span:' . substr($span->getSpanId(), 0, 8) . ']';
        echo "\n";

        // Show key attributes
        $attributes = $span->getAttributes();
        if (!empty($attributes)) {
            $attrIndent = str_repeat(' ', $indent + 2);
            foreach (array_slice($attributes, 0, 3) as $key => $value) {
                if (is_scalar($value)) {
                    echo $attrIndent . "  {$key}: {$value}\n";
                }
            }
        }

        // Show events
        $events = $span->getEvents();
        if (!empty($events)) {
            $eventIndent = str_repeat(' ', $indent + 2);
            foreach ($events as $event) {
                echo $eventIndent . "  📌 {$event['name']}\n";
            }
        }

        if (!empty($node['children'])) {
            displaySpanTree($node['children'], $indent + 2);
        }
    }
}

