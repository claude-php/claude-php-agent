#!/usr/bin/env php
<?php
/**
 * ReactAgent Basic Example
 *
 * Demonstrates the ReactAgent - a simplified wrapper around the base Agent
 * that implements the ReAct (Reason-Act-Observe) pattern for problem-solving.
 * 
 * The ReAct pattern alternates between:
 * - Reasoning: The agent thinks about what to do
 * - Acting: The agent uses tools to gather information or perform actions
 * - Observing: The agent reviews the results and decides next steps
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Progress\AgentUpdate;
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Simple console logger
class ConsoleLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('H:i:s');
        $emoji = match ($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📝',
        };
        echo "[{$timestamp}] {$emoji} [{$level}] {$message}\n";
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
echo "║                        ReactAgent Basic Example                           ║\n";
echo "║                      (Reason-Act-Observe Pattern)                         ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create tools for the agent
echo "🔧 Creating tools...\n\n";

// Calculator tool
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations. Supports +, -, *, /, parentheses.')
    ->stringParam('expression', 'The mathematical expression to evaluate')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        
        // Validate expression (only allow safe characters)
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return "Error: Invalid expression characters";
        }
        
        try {
            $result = eval("return {$expr};");
            return (string) $result;
        } catch (Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    });

// Weather tool (simulated)
$weather = Tool::create('get_weather')
    ->description('Get current weather information for a location')
    ->stringParam('location', 'City name or location')
    ->handler(function (array $input): string {
        $location = $input['location'];
        
        // Simulate weather data
        $conditions = ['sunny', 'cloudy', 'rainy', 'partly cloudy'];
        $temps = range(15, 30);
        
        $condition = $conditions[array_rand($conditions)];
        $temp = $temps[array_rand($temps)];
        
        return json_encode([
            'location' => $location,
            'temperature' => $temp,
            'condition' => $condition,
            'humidity' => rand(40, 80),
            'wind_speed' => rand(5, 25),
        ]);
    });

// Web search tool (simulated)
$search = Tool::create('web_search')
    ->description('Search the web for information')
    ->stringParam('query', 'Search query')
    ->handler(function (array $input): string {
        $query = $input['query'];
        
        // Simulate search results
        $results = [
            "1. PHP is a popular server-side scripting language - php.net",
            "2. PHP 8.3 introduces new features and improvements - php.net/releases",
            "3. Learn PHP programming - various tutorials and documentation",
        ];
        
        return implode("\n", $results);
    });

// Database query tool (simulated)
$database = Tool::create('query_database')
    ->description('Query a database for information')
    ->stringParam('query', 'SQL-like query description')
    ->handler(function (array $input): string {
        $query = strtolower($input['query']);
        
        // Simulate database results
        if (str_contains($query, 'user') || str_contains($query, 'customer')) {
            return json_encode([
                ['id' => 1, 'name' => 'Alice Johnson', 'email' => 'alice@example.com', 'status' => 'active'],
                ['id' => 2, 'name' => 'Bob Smith', 'email' => 'bob@example.com', 'status' => 'active'],
                ['id' => 3, 'name' => 'Carol White', 'email' => 'carol@example.com', 'status' => 'inactive'],
            ]);
        } elseif (str_contains($query, 'order') || str_contains($query, 'sale')) {
            return json_encode([
                ['order_id' => 101, 'customer' => 'Alice Johnson', 'total' => 150.00, 'status' => 'completed'],
                ['order_id' => 102, 'customer' => 'Bob Smith', 'total' => 275.50, 'status' => 'pending'],
                ['order_id' => 103, 'customer' => 'Carol White', 'total' => 89.99, 'status' => 'completed'],
            ]);
        }
        
        return json_encode(['message' => 'No results found']);
    });

echo "✅ Tools created: calculate, get_weather, web_search, query_database\n\n";

// Create the ReactAgent
$logger = new ConsoleLogger();

$agent = new ReactAgent($client, [
    'name' => 'demo_react_agent',
    'system' => 'You are a helpful assistant that can use tools to help answer questions. ' .
                'Use the ReAct pattern: reason about what you need to do, act using tools, ' .
                'and observe the results before deciding on next steps.',
    'max_iterations' => 10,
    'logger' => $logger,
    'tools' => [$calculator, $weather, $search, $database],
]);

// Add callbacks to track execution
$iterationCount = 0;
$agent->onIteration(function ($iteration, $response, $context) use (&$iterationCount) {
    $iterationCount = $iteration;
    echo "\n" . str_repeat("─", 80) . "\n";
    echo "🔄 Iteration {$iteration}\n";
    echo str_repeat("─", 80) . "\n";
});

$agent->onToolExecution(function ($tool, $input, ToolResult $result) {
    echo "🔧 Tool Used: {$tool}\n";
    echo "   Input: " . json_encode($input) . "\n";
    $resultStr = $result->getContent();
    $displayResult = strlen($resultStr) > 100 ? substr($resultStr, 0, 100) . '...' : $resultStr;
    echo "   Result: {$displayResult}\n";
});

// Unified progress updates (single callback for iteration/tool/start/end events)
$agent->onUpdate(function (AgentUpdate $update): void {
    if ($update->getType() === 'agent.start') {
        echo "\n🚀 Agent started\n";
    }
    if ($update->getType() === 'llm.iteration') {
        $data = $update->getData();
        $text = (string) ($data['text'] ?? '');
        $preview = strlen($text) > 80 ? substr($text, 0, 80) . '...' : $text;
        echo "🧠 LLM iteration {$data['iteration']}: {$preview}\n";
    }
});

// Example 1: Simple calculation
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Mathematical Calculation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$result1 = $agent->run('What is (125 * 8) + (450 / 15)?');

if ($result1->isSuccess()) {
    echo "\n✅ Success!\n";
    echo "📝 Answer: {$result1->getAnswer()}\n";
    echo "🔄 Iterations: {$result1->getIterations()}\n";
    
    $usage = $result1->getTokenUsage();
    echo "🎯 Tokens: {$usage['input']} input + {$usage['output']} output = {$usage['total']} total\n";
} else {
    echo "\n❌ Failed: {$result1->getError()}\n";
}

sleep(1);

// Example 2: Weather query
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Weather Information\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$result2 = $agent->run('What is the weather like in San Francisco?');

if ($result2->isSuccess()) {
    echo "\n✅ Success!\n";
    echo "📝 Answer: {$result2->getAnswer()}\n";
    echo "🔄 Iterations: {$result2->getIterations()}\n";
} else {
    echo "\n❌ Failed: {$result2->getError()}\n";
}

sleep(1);

// Example 3: Multi-step reasoning with multiple tools
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Complex Multi-Step Query\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$result3 = $agent->run(
    'First, query the database to find all active users. ' .
    'Then calculate what 15% commission would be on total sales of $2,500. ' .
    'Finally, tell me the weather in New York.'
);

if ($result3->isSuccess()) {
    echo "\n✅ Success!\n";
    echo "📝 Answer: {$result3->getAnswer()}\n";
    echo "🔄 Iterations: {$result3->getIterations()}\n";
    
    $toolCalls = $result3->getToolCalls();
    echo "\n📊 Tools used in this task:\n";
    foreach ($toolCalls as $call) {
        echo "   - {$call['tool']}\n";
    }
} else {
    echo "\n❌ Failed: {$result3->getError()}\n";
}

sleep(1);

// Example 4: Search and analysis
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Research Task\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$result4 = $agent->run(
    'Search for information about PHP and summarize what you find in 2-3 sentences.'
);

if ($result4->isSuccess()) {
    echo "\n✅ Success!\n";
    echo "📝 Answer: {$result4->getAnswer()}\n";
    echo "🔄 Iterations: {$result4->getIterations()}\n";
} else {
    echo "\n❌ Failed: {$result4->getError()}\n";
}

// Summary
echo "\n" . str_repeat("═", 80) . "\n";
echo "📊 Summary\n";
echo str_repeat("═", 80) . "\n\n";

echo "The ReactAgent successfully demonstrated:\n";
echo "  ✓ Mathematical calculations using tools\n";
echo "  ✓ Information retrieval (weather)\n";
echo "  ✓ Multi-step reasoning with multiple tools\n";
echo "  ✓ Research and summarization\n\n";

echo "Key Benefits of ReactAgent:\n";
echo "  • Simple, intuitive API\n";
echo "  • Built-in ReAct loop for complex reasoning\n";
echo "  • Automatic tool orchestration\n";
echo "  • Iteration tracking and callbacks\n";
echo "  • Token usage monitoring\n\n";

echo str_repeat("═", 80) . "\n";
echo "✨ ReactAgent example completed!\n";
echo str_repeat("═", 80) . "\n\n";

echo "💡 Production Tip: Use Design Patterns\n\n";
echo "For production code, consider using:\n";
echo "  • Factory Pattern: Consistent agent creation\n";
echo "  • Builder Pattern: Type-safe configuration\n";
echo "  • Event System: Decoupled monitoring\n\n";
echo "See: examples/design_patterns_demo.php and docs/DesignPatterns.md\n\n";
echo str_repeat("═", 80) . "\n";

