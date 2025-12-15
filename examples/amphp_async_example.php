<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Async\BatchProcessor;
use ClaudeAgents\Async\ParallelToolExecutor;
use ClaudeAgents\Async\Promise;
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;
use ClaudePhp\ClaudePhp;

/**
 * AMPHP Async Example
 * 
 * This example demonstrates the AMPHP-powered async capabilities:
 * - Concurrent batch processing of multiple agent tasks
 * - Parallel tool execution
 * - Promise-based async workflows
 */

// Initialize Claude client
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    die("Please set ANTHROPIC_API_KEY environment variable\n");
}

$client = ClaudePhp::make($apiKey);

echo "=== AMPHP Async Capabilities Demo ===\n\n";

// ============================================================================
// Example 1: Batch Processing with Concurrency
// ============================================================================

echo "1. Batch Processing Multiple Tasks Concurrently\n";
echo str_repeat("-", 50) . "\n";

// Create an agent
$agent = Agent::create(
    client: $client,
    systemPrompt: "You are a helpful assistant that provides concise answers."
);

// Create batch processor
$batchProcessor = BatchProcessor::create($agent);

// Add multiple tasks
$tasks = [
    'task1' => 'What is the capital of France?',
    'task2' => 'What is 25 + 17?',
    'task3' => 'Name three colors in the rainbow.',
    'task4' => 'What is the largest planet in our solar system?',
    'task5' => 'What is the speed of light in km/s?',
];

$batchProcessor->addMany($tasks);

echo "Processing " . count($tasks) . " tasks with concurrency of 3...\n\n";

$startTime = microtime(true);
$results = $batchProcessor->run(concurrency: 3);
$duration = microtime(true) - $startTime;

// Display results
foreach ($results as $id => $result) {
    echo "[{$id}] ";
    if ($result->isSuccess()) {
        echo "✓ " . substr($result->getContent(), 0, 60) . "...\n";
    } else {
        echo "✗ Error: " . $result->getError() . "\n";
    }
}

echo "\n";
echo "Completed in: " . round($duration, 2) . " seconds\n";

// Show statistics
$stats = $batchProcessor->getStats();
echo "\nBatch Statistics:\n";
echo "- Total tasks: {$stats['total_tasks']}\n";
echo "- Successful: {$stats['successful']}\n";
echo "- Failed: {$stats['failed']}\n";
echo "- Success rate: " . round($stats['success_rate'] * 100, 1) . "%\n";
echo "- Total tokens: {$stats['total_tokens']['total']}\n";

echo "\n\n";

// ============================================================================
// Example 2: Parallel Tool Execution
// ============================================================================

echo "2. Parallel Tool Execution\n";
echo str_repeat("-", 50) . "\n";

// Define some example tools
$weatherTool = Tool::define(
    name: 'get_weather',
    description: 'Get weather information for a city',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'city' => ['type' => 'string', 'description' => 'City name'],
        ],
        'required' => ['city'],
    ],
    handler: function (array $input): ToolResult {
        // Simulate API call delay
        usleep(500000); // 500ms
        $city = $input['city'] ?? 'Unknown';
        return ToolResult::success([
            'city' => $city,
            'temperature' => rand(15, 30),
            'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
        ]);
    }
);

$timeTool = Tool::define(
    name: 'get_time',
    description: 'Get current time in a timezone',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'timezone' => ['type' => 'string', 'description' => 'Timezone'],
        ],
        'required' => ['timezone'],
    ],
    handler: function (array $input): ToolResult {
        usleep(300000); // 300ms
        $tz = $input['timezone'] ?? 'UTC';
        return ToolResult::success([
            'timezone' => $tz,
            'time' => date('H:i:s'),
        ]);
    }
);

$calculatorTool = Tool::define(
    name: 'calculate',
    description: 'Perform calculation',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'expression' => ['type' => 'string', 'description' => 'Math expression'],
        ],
        'required' => ['expression'],
    ],
    handler: function (array $input): ToolResult {
        usleep(200000); // 200ms
        $expr = $input['expression'] ?? '0';
        // Simple eval (don't use in production!)
        try {
            $result = eval("return {$expr};");
            return ToolResult::success(['result' => $result]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }
);

$tools = [$weatherTool, $timeTool, $calculatorTool];

// Execute multiple tools in parallel
$executor = new ParallelToolExecutor($tools);

$toolCalls = [
    ['tool' => 'get_weather', 'input' => ['city' => 'London']],
    ['tool' => 'get_weather', 'input' => ['city' => 'Paris']],
    ['tool' => 'get_time', 'input' => ['timezone' => 'America/New_York']],
    ['tool' => 'calculate', 'input' => ['expression' => '42 * 8']],
    ['tool' => 'calculate', 'input' => ['expression' => '100 / 4']],
];

echo "Executing " . count($toolCalls) . " tool calls in parallel...\n\n";

$startTime = microtime(true);
$toolResults = $executor->execute($toolCalls);
$duration = microtime(true) - $startTime;

foreach ($toolResults as $result) {
    echo "Tool: {$result['tool']}\n";
    echo "Result: " . json_encode($result['result']->getData()) . "\n\n";
}

echo "Completed in: " . round($duration, 2) . " seconds\n";
echo "(Would take ~1.7s sequentially, but concurrent execution is faster!)\n";

echo "\n\n";

// ============================================================================
// Example 3: Promise-Based Async Workflow
// ============================================================================

echo "3. Promise-Based Async Workflow\n";
echo str_repeat("-", 50) . "\n";

// Execute tools asynchronously and get promises
$promises = $executor->executeAsync([
    ['tool' => 'get_weather', 'input' => ['city' => 'Tokyo']],
    ['tool' => 'get_time', 'input' => ['timezone' => 'Asia/Tokyo']],
]);

echo "Created " . count($promises) . " promises for async execution\n";

// Wait for all promises
$asyncResults = Promise::all($promises);

echo "All promises resolved!\n\n";

foreach ($asyncResults as $i => $result) {
    echo "Promise {$i}: " . json_encode($result['result']->getData()) . "\n";
}

echo "\n\n";

// ============================================================================
// Example 4: Batched Execution with Concurrency Limit
// ============================================================================

echo "4. Batched Tool Execution (Concurrency: 2)\n";
echo str_repeat("-", 50) . "\n";

$largeBatch = [
    ['tool' => 'calculate', 'input' => ['expression' => '10 + 5']],
    ['tool' => 'calculate', 'input' => ['expression' => '20 * 3']],
    ['tool' => 'calculate', 'input' => ['expression' => '100 - 25']],
    ['tool' => 'calculate', 'input' => ['expression' => '15 / 3']],
    ['tool' => 'calculate', 'input' => ['expression' => '8 * 8']],
    ['tool' => 'calculate', 'input' => ['expression' => '50 + 50']],
];

echo "Executing " . count($largeBatch) . " calculations with concurrency limit of 2...\n\n";

$startTime = microtime(true);
$batchedResults = $executor->executeBatched($largeBatch, concurrency: 2);
$duration = microtime(true) - $startTime;

foreach ($batchedResults as $result) {
    $expr = $result['input']['expression'];
    $value = $result['result']->getData()['result'] ?? 'error';
    echo "{$expr} = {$value}\n";
}

echo "\nCompleted in: " . round($duration, 2) . " seconds\n";

echo "\n\n";

// ============================================================================
// Example 5: Async Batch Processing with Promises
// ============================================================================

echo "5. Async Batch Processing with Promises\n";
echo str_repeat("-", 50) . "\n";

$asyncBatch = BatchProcessor::create($agent);
$asyncBatch->addMany([
    'q1' => 'What is 2+2?',
    'q2' => 'Name a fruit.',
    'q3' => 'What color is the sky?',
]);

echo "Starting async batch processing...\n";

// Get promises for all tasks
$taskPromises = $asyncBatch->runAsync();

echo "Received " . count($taskPromises) . " promises\n";
echo "Waiting for all to complete...\n\n";

// Wait for completion
$asyncTaskResults = Promise::all($taskPromises);

foreach ($asyncTaskResults as $result) {
    if ($result->isSuccess()) {
        echo "✓ " . substr($result->getContent(), 0, 50) . "...\n";
    }
}

echo "\n=== Demo Complete ===\n";

