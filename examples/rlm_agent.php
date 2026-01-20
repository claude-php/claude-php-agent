#!/usr/bin/env php
<?php
/**
 * RLM (Recursive Language Model) Agent Example
 *
 * Demonstrates the RLMAgent - an implementation based on the MIT CSAIL research
 * paper (arXiv:2512.24601v1) that enables processing inputs far beyond context
 * window limits.
 *
 * Key Concepts:
 * - Input is stored as an external variable, not in the LLM context
 * - Agent uses tools to peek, slice, and search the input
 * - Recursive self-invocation for decomposing complex tasks
 * - Variable storage for intermediate results
 *
 * This allows processing inputs up to 2 orders of magnitude beyond
 * the model's context window limits.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\RLMAgent;
use ClaudeAgents\Progress\AgentUpdate;
use ClaudeAgents\Tools\Tool;
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
echo "║                    RLM (Recursive Language Model) Agent                   ║\n";
echo "║              Processing Large Inputs Beyond Context Limits                ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "📚 Based on MIT CSAIL research: arXiv:2512.24601v1\n\n";

// Create a sample large input (simulating a log file)
function generateSampleLogFile(int $lines = 500): string
{
    $logLevels = ['INFO', 'DEBUG', 'WARNING', 'ERROR', 'CRITICAL'];
    $components = ['auth', 'database', 'api', 'cache', 'scheduler', 'worker'];
    $messages = [
        'INFO' => ['Request processed successfully', 'User logged in', 'Cache hit', 'Task completed'],
        'DEBUG' => ['Variable dump', 'Query executed', 'Method called', 'State changed'],
        'WARNING' => ['Slow query detected', 'Memory usage high', 'Deprecated method used', 'Rate limit approaching'],
        'ERROR' => ['Database connection failed', 'Invalid request format', 'Authentication failed', 'Timeout occurred'],
        'CRITICAL' => ['System overload', 'Disk space critical', 'Service unavailable', 'Fatal exception'],
    ];

    $log = [];
    $baseTime = strtotime('2024-01-15 00:00:00');

    for ($i = 0; $i < $lines; $i++) {
        $timestamp = date('Y-m-d H:i:s', $baseTime + ($i * 2));
        $level = $logLevels[array_rand($logLevels)];
        $component = $components[array_rand($components)];
        $message = $messages[$level][array_rand($messages[$level])];

        // Add some extra context for certain messages
        $extra = '';
        if ($level === 'ERROR' && rand(0, 1)) {
            $extra = ' - Request ID: ' . substr(md5((string)$i), 0, 8);
        }
        if ($level === 'WARNING' && str_contains($message, 'Slow query')) {
            $extra = ' - Duration: ' . rand(500, 5000) . 'ms';
        }

        $log[] = "[{$timestamp}] [{$level}] [{$component}] {$message}{$extra}";
    }

    return implode("\n", $log);
}

// Generate sample data
echo "🔧 Generating sample log file...\n";
$logContent = generateSampleLogFile(500);
$lineCount = count(explode("\n", $logContent));
$charCount = strlen($logContent);
echo "   Generated: {$lineCount} lines, {$charCount} characters\n\n";

// Create the RLM Agent
$logger = new ConsoleLogger();

$agent = new RLMAgent($client, [
    'name' => 'log_analyzer',
    'system' => 'You are a log file analyzer. Use the provided tools to examine the log ' .
                'content without loading it all into context. Be thorough in your analysis.',
    'max_iterations' => 15,
    'max_recursion_depth' => 5,
    'logger' => $logger,
]);

// Add callbacks to track execution
$agent->onIteration(function ($iteration, $response, $context) {
    echo "\n" . str_repeat("─", 80) . "\n";
    echo "🔄 Iteration {$iteration}\n";
    echo str_repeat("─", 80) . "\n";
});

$agent->onToolExecution(function ($tool, $input, $result) {
    echo "🔧 Tool: {$tool}\n";
    if (!empty($input)) {
        $inputStr = json_encode($input);
        $displayInput = strlen($inputStr) > 80 ? substr($inputStr, 0, 80) . '...' : $inputStr;
        echo "   Input: {$displayInput}\n";
    }
    $resultStr = $result->getContent();
    $displayResult = strlen($resultStr) > 100 ? substr($resultStr, 0, 100) . '...' : $resultStr;
    echo "   Result: {$displayResult}\n";
});

$agent->onRecursion(function ($depth, $task, $result) {
    if ($result === null) {
        echo "🔁 Entering recursion depth {$depth}: " . substr($task, 0, 60) . "...\n";
    } else {
        echo "🔙 Exiting recursion depth {$depth}\n";
    }
});

$agent->onUpdate(function (AgentUpdate $update): void {
    if ($update->getType() === 'agent.start') {
        echo "\n🚀 RLM Agent started\n";
    }
});

// Example 1: Basic log analysis
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Get Input Information\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Task: Describe the structure of the log file\n";

$result1 = $agent->runWithInput(
    'Describe the structure and content of this log file. What information is available?',
    $logContent
);

if ($result1->isSuccess()) {
    echo "\n✅ Success!\n";
    echo "📝 Answer: {$result1->getAnswer()}\n";
    echo "🔄 Iterations: {$result1->getIterations()}\n";

    $metadata = $result1->getMetadata();
    if (isset($metadata['rlm'])) {
        echo "📊 RLM Stats:\n";
        echo "   - Input: {$metadata['rlm']['input_chars']} chars, {$metadata['rlm']['input_lines']} lines\n";
        echo "   - Max recursion used: {$metadata['rlm']['recursion_depth']}\n";
    }
} else {
    echo "\n❌ Failed: {$result1->getError()}\n";
}

sleep(1);

// Example 2: Search for errors
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Find All Errors\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Task: Find and count all ERROR level log entries\n";

$result2 = $agent->runWithInput(
    'Find all ERROR and CRITICAL level entries in the log. Count them and summarize the types of errors found.',
    $logContent
);

if ($result2->isSuccess()) {
    echo "\n✅ Success!\n";
    echo "📝 Answer: {$result2->getAnswer()}\n";
    echo "🔄 Iterations: {$result2->getIterations()}\n";
} else {
    echo "\n❌ Failed: {$result2->getError()}\n";
}

sleep(1);

// Example 3: Component analysis
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Analyze Specific Component\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Task: Analyze the 'database' component entries\n";

$result3 = $agent->runWithInput(
    'Search for all log entries from the database component. What patterns do you see? Are there any issues?',
    $logContent
);

if ($result3->isSuccess()) {
    echo "\n✅ Success!\n";
    echo "📝 Answer: {$result3->getAnswer()}\n";
    echo "🔄 Iterations: {$result3->getIterations()}\n";
} else {
    echo "\n❌ Failed: {$result3->getError()}\n";
}

sleep(1);

// Example 4: Using variables for intermediate results
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Complex Analysis with Variables\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Task: Multi-step analysis storing intermediate results\n";

$result4 = $agent->runWithInput(
    'Perform a comprehensive analysis: ' .
    '1) First, use get_input_info to understand the log structure. ' .
    '2) Search for CRITICAL entries and store the count using set_variable. ' .
    '3) Search for slow queries (entries mentioning "Duration") and note patterns. ' .
    '4) Provide a final summary with recommendations.',
    $logContent
);

if ($result4->isSuccess()) {
    echo "\n✅ Success!\n";
    echo "📝 Answer: {$result4->getAnswer()}\n";
    echo "🔄 Iterations: {$result4->getIterations()}\n";

    $metadata = $result4->getMetadata();
    if (isset($metadata['rlm']['variables'])) {
        echo "📦 Variables stored: " . implode(', ', $metadata['rlm']['variables']) . "\n";
    }
} else {
    echo "\n❌ Failed: {$result4->getError()}\n";
}

// Example 5: Working with very large input (demonstration)
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Large Input Demonstration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Generate a larger log file
echo "🔧 Generating larger log file (2000 lines)...\n";
$largeLogContent = generateSampleLogFile(2000);
$largeLineCount = count(explode("\n", $largeLogContent));
$largeCharCount = strlen($largeLogContent);
echo "   Generated: {$largeLineCount} lines, {$largeCharCount} characters (~" . round($largeCharCount / 4) . " estimated tokens)\n\n";

echo "Task: Find all authentication failures in a large log\n";

$result5 = $agent->runWithInput(
    'This is a large log file. Search for entries related to authentication. ' .
    'Specifically look for "Authentication failed" errors and report how many you find ' .
    'and any patterns you observe.',
    $largeLogContent
);

if ($result5->isSuccess()) {
    echo "\n✅ Success!\n";
    echo "📝 Answer: {$result5->getAnswer()}\n";
    echo "🔄 Iterations: {$result5->getIterations()}\n";

    $metadata = $result5->getMetadata();
    if (isset($metadata['rlm'])) {
        echo "📊 RLM processed {$metadata['rlm']['input_chars']} chars without loading into context!\n";
    }
} else {
    echo "\n❌ Failed: {$result5->getError()}\n";
}

// Summary
echo "\n" . str_repeat("═", 80) . "\n";
echo "📊 RLM Agent Summary\n";
echo str_repeat("═", 80) . "\n\n";

echo "The RLM Agent demonstrated:\n";
echo "  ✓ Processing large inputs without loading into context window\n";
echo "  ✓ Using peek_input to examine portions of data\n";
echo "  ✓ Using search_input to find patterns via regex\n";
echo "  ✓ Using slice_input to extract line ranges\n";
echo "  ✓ Using set_variable to store intermediate results\n";
echo "  ✓ Recursive decomposition for complex tasks\n\n";

echo "Key Benefits:\n";
echo "  • Process inputs 10-100x larger than context window\n";
echo "  • Efficient token usage (only examine what's needed)\n";
echo "  • Recursive decomposition for complex analysis\n";
echo "  • Variable storage for multi-step workflows\n\n";

echo "Built-in Tools:\n";
echo "  • peek_input(start, length) - View substring by position\n";
echo "  • slice_input(start_line, end_line) - Extract line range\n";
echo "  • search_input(pattern, context_lines) - Regex search\n";
echo "  • get_input_info() - Get input metadata\n";
echo "  • set_variable(name, value) - Store intermediate results\n";
echo "  • get_variable(name) - Retrieve stored values\n";
echo "  • recursive_call(task, input_source) - Process sub-tasks\n\n";

echo str_repeat("═", 80) . "\n";
echo "✨ RLM Agent example completed!\n";
echo str_repeat("═", 80) . "\n\n";

echo "📚 Reference: Zhang, Kraska, Khattab. 'Recursive Language Models.' arXiv:2512.24601v1 (2026)\n\n";
