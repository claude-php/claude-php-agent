#!/usr/bin/env php
<?php
/**
 * Production Agent Example
 *
 * Demonstrates production-ready patterns including error handling,
 * retry logic, logging, and monitoring.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Config\RetryConfig;
use ClaudeAgents\Support\RetryHandler;
use ClaudeAgents\Support\TokenTracker;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Simple console logger for demonstration
class ConsoleLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        echo "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
    }
}

// Load environment
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? throw new RuntimeException('ANTHROPIC_API_KEY not set');
$client = new ClaudePhp(apiKey: $apiKey);

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                   Production Agent Example                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create logger and token tracker
$logger = new ConsoleLogger();
$tokenTracker = new TokenTracker();

// Production configuration
$config = AgentConfig::fromArray([
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 4096,
    'max_iterations' => 10,
    'timeout' => 30.0,
    'retry' => [
        'max_attempts' => 3,
        'delay_ms' => 1000,
        'multiplier' => 2.0,
    ],
]);

// Create tools with proper error handling
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations safely')
    ->stringParam('expression', 'Math expression (supports +, -, *, /, parentheses)')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        
        // Input validation
        if (empty($expr)) {
            return "Error: Empty expression";
        }
        
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return "Error: Invalid characters in expression";
        }
        
        // Prevent division by zero
        if (preg_match('/\/\s*0(?![0-9.])/', $expr)) {
            return "Error: Division by zero";
        }
        
        try {
            $result = eval("return {$expr};");
            return (string) $result;
        } catch (Throwable $e) {
            return "Error: Calculation failed - " . $e->getMessage();
        }
    });

$weatherTool = Tool::create('get_weather')
    ->description('Get current weather for a location (simulated)')
    ->stringParam('city', 'City name')
    ->handler(function (array $input): string {
        // Simulate occasional failures for retry demonstration
        if (random_int(1, 10) === 1) {
            throw new RuntimeException("Weather service temporarily unavailable");
        }
        
        $conditions = ['sunny', 'cloudy', 'rainy', 'partly cloudy'];
        return json_encode([
            'city' => $input['city'],
            'temperature' => random_int(50, 85) . '°F',
            'conditions' => $conditions[array_rand($conditions)],
        ]);
    });

// Create the agent with production settings
$agent = Agent::create($client)
    ->withConfig($config)
    ->withTools([$calculator, $weatherTool])
    ->withLogger($logger)
    ->withSystemPrompt('You are a helpful assistant. Use tools when needed and handle errors gracefully.')
    ->onIteration(function (int $iteration, $response, $context) use ($tokenTracker) {
        // Track tokens
        if (isset($response->usage)) {
            $tokenTracker->record(
                $response->usage->input_tokens ?? 0,
                $response->usage->output_tokens ?? 0
            );
        }
    })
    ->onToolExecution(function (string $tool, array $input, $result) use ($logger) {
        $status = $result->isError() ? '❌' : '✅';
        $logger->info("{$status} Tool executed: {$tool}", [
            'input' => $input,
            'error' => $result->isError(),
        ]);
    })
    ->onError(function (Throwable $e, int $attempt) use ($logger) {
        $logger->error("Agent error on attempt {$attempt}", [
            'message' => $e->getMessage(),
        ]);
    });

// Run multiple tasks
$tasks = [
    "What is 25 * 17 + 100?",
    "What's the weather in Tokyo and New York?",
];

foreach ($tasks as $i => $task) {
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Task " . ($i + 1) . ": {$task}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $startTime = microtime(true);
    $result = $agent->run($task);
    $duration = round(microtime(true) - $startTime, 2);
    
    if ($result->isSuccess()) {
        echo "\n✅ Success:\n{$result->getAnswer()}\n";
    } else {
        echo "\n❌ Failed: {$result->getError()}\n";
    }
    
    echo "\n📊 Task Stats:\n";
    echo "  • Duration: {$duration}s\n";
    echo "  • Iterations: {$result->getIterations()}\n";
    
    $usage = $result->getTokenUsage();
    echo "  • Tokens: {$usage['total']} ({$usage['input']} in, {$usage['output']} out)\n";
}

// Final summary
echo "\n" . str_repeat("═", 80) . "\n";
echo "Session Summary\n";
echo str_repeat("═", 80) . "\n\n";

$summary = $tokenTracker->getSummary();
echo "📈 Token Usage:\n";
echo "  • Total requests: {$summary['request_count']}\n";
echo "  • Total tokens: {$summary['total_tokens']}\n";
echo "  • Input tokens: {$summary['input_tokens']}\n";
echo "  • Output tokens: {$summary['output_tokens']}\n";
echo "  • Average per request: {$summary['average_per_request']}\n";

$estimatedCost = $tokenTracker->estimateCost();
echo "\n💰 Estimated Cost: $" . number_format($estimatedCost, 4) . "\n";

echo "\n" . str_repeat("═", 80) . "\n";
echo "Production agent example completed!\n";

