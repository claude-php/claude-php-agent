#!/usr/bin/env php
<?php
/**
 * Advanced Streaming Example
 *
 * Demonstrates advanced streaming features:
 * - Multiple concurrent handlers
 * - Stream statistics and performance monitoring
 * - Error handling
 * - Custom handlers
 * - File and log output
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Streaming\StreamingLoop;
use ClaudeAgents\Streaming\StreamBuffer;
use ClaudeAgents\Streaming\Handlers\ConsoleHandler;
use ClaudeAgents\Streaming\Handlers\FileHandler;
use ClaudeAgents\Streaming\Handlers\CallbackHandler;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║          Advanced Streaming Example                          ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

// Create temp file for logging
$logFile = sys_get_temp_dir() . '/stream_log_' . time() . '.txt';
echo "📝 Logging to: {$logFile}\n\n";

// Create tools
$searchTool = Tool::create('web_search')
    ->description('Search the web for information')
    ->stringParam('query', 'Search query')
    ->handler(function($input) {
        sleep(1); // Simulate API call
        $query = $input['query'] ?? '';
        return "Found 3 results for '{$query}':\n" .
               "1. Wikipedia article about {$query}\n" .
               "2. News article about recent {$query} developments\n" .
               "3. Academic paper on {$query}";
    });

$calculatorTool = Tool::create('calculator')
    ->description('Performs mathematical calculations')
    ->stringParam('expression', 'Math expression to evaluate')
    ->handler(function($input) {
        $expr = $input['expression'] ?? '0';
        try {
            // Safe evaluation (in production, use a proper math library)
            $result = @eval('return ' . $expr . ';');
            return $result !== false ? (string)$result : 'Error evaluating expression';
        } catch (\Throwable $e) {
            return 'Error: ' . $e->getMessage();
        }
    });

// Create agent configuration
$config = new AgentConfig([
    'model' => 'claude-sonnet-4-5',
    'max_iterations' => 5,
    'max_tokens' => 2048,
]);

// Create agent
$agent = Agent::create($client)
    ->withConfig($config)
    ->withTool($searchTool)
    ->withTool($calculatorTool)
    ->maxIterations(5);

// Create streaming buffer for statistics
$buffer = new StreamBuffer();

// Setup streaming loop with multiple handlers
$streamingLoop = new StreamingLoop();

// 1. Console handler with styling
$streamingLoop->addHandler(new ConsoleHandler(
    newline: false,
    prefix: '🤖 '
));

// 2. File handler with timestamps
$streamingLoop->addHandler(new FileHandler(
    $logFile,
    append: true,
    includeTimestamps: true,
    includeEventTypes: true
));

// 3. Custom callback handler for statistics
$streamingLoop->addHandler(new CallbackHandler(function($event) use ($buffer) {
    if ($event->isText()) {
        $buffer->addText($event->getText());
    }
    
    if ($event->isError()) {
        echo "\n❌ ERROR: " . $event->getText() . "\n";
    }
}));

// 4. Progress tracking callback
$chunkCount = 0;
$streamingLoop->onStream(function($event) use (&$chunkCount) {
    if ($event->isText()) {
        $chunkCount++;
        if ($chunkCount % 10 === 0) {
            // Show progress every 10 chunks (without newline to avoid breaking output)
            // We'll show stats at the end instead
        }
    }
});

// 5. Iteration callback
$streamingLoop->onIteration(function($iteration, $response, $context) {
    echo "\n\n" . str_repeat('─', 60) . "\n";
    echo "📊 Iteration {$iteration} complete\n";
    
    $usage = $context->getTokenUsage();
    echo "   Tokens: {$usage['input']} input, {$usage['output']} output\n";
});

// 6. Tool execution callback
$streamingLoop->onToolExecution(function($tool, $input, $result) {
    echo "\n\n🔧 Tool executed: {$tool}\n";
    echo "   Input: " . json_encode($input) . "\n";
    echo "   Result: " . substr($result->getContent(), 0, 100);
    if (strlen($result->getContent()) > 100) {
        echo "...";
    }
    echo "\n\n";
});

// Set loop strategy
$agent->withLoopStrategy($streamingLoop);

// Example task
$task = "Search for information about quantum computing, then calculate how many qubits " .
        "are needed for 1000 possible states (2^n = 1000, solve for n).";

echo "📋 Task: {$task}\n\n";
echo "🚀 Starting streaming agent...\n";
echo str_repeat('═', 60) . "\n";

// Run agent with streaming
$startTime = microtime(true);
$result = $agent->run($task);
$endTime = microtime(true);

// Show final results
echo "\n" . str_repeat('═', 60) . "\n";
echo "✅ Agent completed!\n\n";

echo "📈 Final Statistics:\n";
echo "   Status: " . ($result->isSuccess() ? '✓ Success' : '✗ Failed') . "\n";
echo "   Total time: " . round($endTime - $startTime, 2) . " seconds\n";
echo "   Iterations: " . $result->getMetadata()['iterations'] . "\n";

$usage = $result->getMetadata()['token_usage'];
echo "   Total tokens: " . $usage['total'] . " (input: {$usage['input']}, output: {$usage['output']})\n";

// Stream statistics
$stats = $buffer->getStatistics();
echo "\n📊 Stream Statistics:\n";
echo "   Total chunks: {$stats['total_chunks']}\n";
echo "   Total bytes: {$stats['total_bytes']}\n";
echo "   Duration: {$stats['duration_seconds']} seconds\n";
echo "   Speed: {$stats['bytes_per_second']} bytes/sec\n";
echo "   Avg chunk size: {$stats['average_chunk_size']} bytes\n";

echo "\n📝 Full conversation logged to: {$logFile}\n";

// Show final answer
echo "\n" . str_repeat('═', 60) . "\n";
echo "💬 Final Answer:\n";
echo str_repeat('─', 60) . "\n";
echo $result->getAnswer() . "\n";
echo str_repeat('═', 60) . "\n";

// Optionally show log file contents
if (isset($argv[1]) && $argv[1] === '--show-log') {
    echo "\n📄 Log file contents:\n";
    echo str_repeat('─', 60) . "\n";
    echo file_get_contents($logFile);
    echo str_repeat('─', 60) . "\n";
}

echo "\n💡 Tip: Run with --show-log to see the detailed log\n";

