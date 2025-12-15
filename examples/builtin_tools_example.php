#!/usr/bin/env php
<?php
/**
 * Built-in Tools Example
 *
 * Demonstrates the use of built-in tools (Calculator, DateTime, HTTP)
 * with the BuiltInToolRegistry for easy setup.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Tools\BuiltIn\BuiltInToolRegistry;
use ClaudeAgents\Tools\BuiltIn\CalculatorTool;
use ClaudeAgents\Tools\BuiltIn\DateTimeTool;
use ClaudeAgents\Tools\BuiltIn\HTTPTool;
use ClaudePhp\ClaudePhp;

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
echo "║                      Built-in Tools Example                                ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// EXAMPLE 1: Using Individual Tools
// ============================================================================

echo "=== Example 1: Individual Tools ===\n\n";

// Create individual tools with custom configuration
$calculator = CalculatorTool::create([
    'allow_functions' => true,
    'max_precision' => 5,
]);

$datetime = DateTimeTool::create([
    'default_timezone' => 'America/New_York',
    'default_format' => 'F j, Y g:i A T',
]);

$http = HTTPTool::create([
    'timeout' => 10,
    'follow_redirects' => true,
]);

// Test calculator
echo "📊 Testing Calculator Tool:\n";
$result = $calculator->execute(['expression' => '(25 * 17) + (100 / 4)']);
if ($result->isSuccess()) {
    $data = json_decode($result->getContent(), true);
    echo "   Expression: {$data['expression']}\n";
    echo "   Result: {$data['result']}\n";
}

// Test datetime
echo "\n📅 Testing DateTime Tool:\n";
$result = $datetime->execute([
    'operation' => 'now',
    'timezone' => 'Europe/London',
]);
if ($result->isSuccess()) {
    $data = json_decode($result->getContent(), true);
    echo "   Current time in London: {$data['datetime']}\n";
}

// ============================================================================
// EXAMPLE 2: Using BuiltInToolRegistry
// ============================================================================

echo "\n\n=== Example 2: BuiltInToolRegistry ===\n\n";

// Option A: All tools with default configuration
$registry1 = BuiltInToolRegistry::createWithAll();
echo "Registry 1 has {$registry1->count()} tools: " . implode(', ', $registry1->names()) . "\n";

// Option B: All tools with custom configuration
$registry2 = BuiltInToolRegistry::createWithAll([
    'calculator' => ['allow_functions' => true],
    'datetime' => ['default_timezone' => 'UTC'],
    'http' => ['timeout' => 30, 'allowed_domains' => ['api.github.com', 'jsonplaceholder.typicode.com']],
]);
echo "Registry 2 has {$registry2->count()} tools with custom config\n";

// Option C: Selective tools
$registry3 = BuiltInToolRegistry::withTools(['calculator', 'datetime']);
echo "Registry 3 has {$registry3->count()} tools: " . implode(', ', $registry3->names()) . "\n";

// Option D: Single tool registries
$calcRegistry = BuiltInToolRegistry::withCalculator();
$dateRegistry = BuiltInToolRegistry::withDateTime();
$httpRegistry = BuiltInToolRegistry::withHTTP();
echo "Single-tool registries created\n";

// ============================================================================
// EXAMPLE 3: Agent with Built-in Tools
// ============================================================================

echo "\n\n=== Example 3: Agent with Built-in Tools ===\n\n";

// Create registry with all tools
$registry = BuiltInToolRegistry::createWithAll([
    'calculator' => ['allow_functions' => false],
    'datetime' => ['default_timezone' => 'UTC'],
]);

// Create agent with all built-in tools
$agent = Agent::create($client)
    ->withTools($registry->all())
    ->withSystemPrompt('You are a helpful assistant with access to calculator and datetime tools. Use them to help answer questions accurately.')
    ->maxIterations(10)
    ->onToolExecution(function (string $tool, array $input, $result) {
        echo "  🔧 {$tool}\n";
        echo "     Input: " . json_encode($input, JSON_PRETTY_PRINT) . "\n";
        if ($result->isSuccess()) {
            echo "     Output: {$result->getContent()}\n";
        } else {
            echo "     Error: {$result->getContent()}\n";
        }
    });

// Test tasks
$tasks = [
    "What is 1234 multiplied by 5678?",
    "What is the current date and time?",
    "Calculate 25% of 840, then tell me what the date will be 30 days from now",
];

foreach ($tasks as $i => $task) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Task " . ($i + 1) . ": {$task}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $result = $agent->run($task);
    
    if ($result->isSuccess()) {
        echo "\n✅ Answer:\n{$result->getAnswer()}\n";
        echo "\n📊 Stats: {$result->getIterations()} iterations, ";
        $usage = $result->getTokenUsage();
        echo "{$usage['total']} tokens\n";
    } else {
        echo "\n❌ Error: {$result->getError()}\n";
    }
    
    echo "\n";
}

// ============================================================================
// EXAMPLE 4: Advanced DateTime Operations
// ============================================================================

echo "\n=== Example 4: Advanced DateTime Operations ===\n\n";

$dateTimeTool = DateTimeTool::create();

// Current time in different timezones
echo "📍 Current time in different locations:\n";
$timezones = ['UTC', 'America/New_York', 'Europe/London', 'Asia/Tokyo'];
foreach ($timezones as $tz) {
    $result = $dateTimeTool->execute([
        'operation' => 'now',
        'timezone' => $tz,
        'format' => 'Y-m-d H:i:s T',
    ]);
    if ($result->isSuccess()) {
        $data = json_decode($result->getContent(), true);
        echo "   {$tz}: {$data['datetime']}\n";
    }
}

// Date arithmetic
echo "\n📆 Date arithmetic:\n";
$result = $dateTimeTool->execute([
    'operation' => 'add',
    'date' => '2024-01-01',
    'interval' => '+3 months',
    'format' => 'F j, Y',
]);
if ($result->isSuccess()) {
    $data = json_decode($result->getContent(), true);
    echo "   3 months after Jan 1, 2024: {$data['result']}\n";
}

// Date difference
echo "\n📊 Date difference:\n";
$result = $dateTimeTool->execute([
    'operation' => 'diff',
    'date' => '2024-01-01',
    'date2' => '2024-12-31',
]);
if ($result->isSuccess()) {
    $data = json_decode($result->getContent(), true);
    echo "   Days between Jan 1 and Dec 31, 2024: {$data['total_days']} days\n";
    echo "   Breakdown: {$data['months']} months, {$data['days']} days\n";
}

// ============================================================================
// EXAMPLE 5: Calculator with Complex Expressions
// ============================================================================

echo "\n\n=== Example 5: Calculator Operations ===\n\n";

$calc = CalculatorTool::create(['allow_functions' => false]);

$expressions = [
    '(100 + 50) * 2',
    '1234.56 + 789.44',
    '(25 * 4) / (10 - 5)',
    '100 * (1 + 0.15)',
];

echo "🧮 Testing calculator expressions:\n";
foreach ($expressions as $expr) {
    $result = $calc->execute(['expression' => $expr]);
    if ($result->isSuccess()) {
        $data = json_decode($result->getContent(), true);
        echo "   {$expr} = {$data['result']}\n";
    }
}

// ============================================================================
// EXAMPLE 6: HTTP Tool (Optional - requires internet)
// ============================================================================

echo "\n\n=== Example 6: HTTP Tool (Optional) ===\n\n";

// Uncomment to test HTTP tool
/*
$httpTool = HTTPTool::create([
    'timeout' => 10,
    'allowed_domains' => ['jsonplaceholder.typicode.com'],
]);

echo "🌐 Testing HTTP request:\n";
$result = $httpTool->execute([
    'url' => 'https://jsonplaceholder.typicode.com/posts/1',
    'method' => 'GET',
]);

if ($result->isSuccess()) {
    $data = json_decode($result->getContent(), true);
    echo "   Status: {$data['status_code']}\n";
    echo "   Content-Type: {$data['content_type']}\n";
    echo "   Time: {$data['time_seconds']}s\n";
    echo "   Body preview: " . substr($data['body'], 0, 100) . "...\n";
}
*/
echo "HTTP tool test commented out (uncomment to test with internet connection)\n";

// ============================================================================
// EXAMPLE 7: Error Handling
// ============================================================================

echo "\n\n=== Example 7: Error Handling ===\n\n";

// Test invalid calculator expression
echo "🧪 Testing error handling:\n";
$result = $calculator->execute(['expression' => 'invalid expression!']);
if ($result->isError()) {
    echo "   Calculator error (expected): {$result->getContent()}\n";
}

// Test invalid datetime operation
$result = $datetime->execute([
    'operation' => 'invalid',
]);
if ($result->isError()) {
    echo "   DateTime error (expected): {$result->getContent()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "Built-in tools example completed!\n";
echo "\nKey Takeaways:\n";
echo "  • Use BuiltInToolRegistry for easy tool setup\n";
echo "  • Configure tools with custom options as needed\n";
echo "  • Tools handle errors gracefully with ToolResult\n";
echo "  • Combine multiple tools for powerful agent capabilities\n";

