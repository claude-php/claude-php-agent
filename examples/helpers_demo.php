<?php

/**
 * Agent Helpers Demo
 * 
 * Demonstrates the shared helper utilities for building agents
 * including the ReAct loop, tool management, and debugging.
 * 
 * Run: php examples/helpers_demo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Helpers\AgentHelpers;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    die("Error: ANTHROPIC_API_KEY not set\n");
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Agent Helpers Demo                                          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Initialize Claude client
$client = new ClaudePhp(apiKey: $apiKey);

// Create a logger for debug output
// Option 1: Use the built-in console logger (simple)
$logger = AgentHelpers::createConsoleLogger('helpers_demo', 'debug');

// Option 2: Use Monolog (more features)
// $logger = new Logger('helpers_demo');
// $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Example 1: Create tools easily
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Creating Tools with Helper\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$calculatorTool = AgentHelpers::createTool(
    name: 'calculate',
    description: 'Perform mathematical calculations',
    parameters: [
        'expression' => [
            'type' => 'string',
            'description' => 'Mathematical expression to evaluate'
        ]
    ],
    required: ['expression']
);

echo "✅ Created calculator tool:\n";
echo json_encode($calculatorTool, JSON_PRETTY_PRINT) . "\n\n";

// Example 2: Run an agent loop with debugging
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Running Agent Loop with Debug Output\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Define a simple tool executor
$toolExecutor = function(string $name, array $input): string {
    if ($name === 'calculate') {
        $expression = $input['expression'];
        
        // Safety check
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expression)) {
            return "Error: Invalid expression";
        }
        
        try {
            $result = eval("return {$expression};");
            return (string)$result;
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
    
    return "Unknown tool: {$name}";
};

$messages = [
    ['role' => 'user', 'content' => 'Calculate (25 * 17) + (100 / 4) and then subtract 15']
];

$result = AgentHelpers::runAgentLoop(
    client: $client,
    messages: $messages,
    tools: [$calculatorTool],
    toolExecutor: $toolExecutor,
    config: [
        'max_iterations' => 10,
        'debug' => true,
        'logger' => $logger,
    ]
);

if ($result['success']) {
    echo "\n✅ Agent completed successfully!\n";
    echo "Iterations: {$result['iterations']}\n\n";
    
    $finalResponse = AgentHelpers::extractTextContent($result['response']);
    echo "Final Answer: {$finalResponse}\n\n";
} else {
    echo "\n❌ Agent failed: " . ($result['error'] ?? 'Unknown error') . "\n\n";
}

// Example 3: Conversation history management
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Managing Conversation History\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Simulate a long conversation
$longConversation = [
    ['role' => 'user', 'content' => 'Message 1'],
    ['role' => 'assistant', 'content' => 'Response 1'],
    ['role' => 'user', 'content' => 'Message 2'],
    ['role' => 'assistant', 'content' => 'Response 2'],
    ['role' => 'user', 'content' => 'Message 3'],
    ['role' => 'assistant', 'content' => 'Response 3'],
    ['role' => 'user', 'content' => 'Message 4'],
    ['role' => 'assistant', 'content' => 'Response 4'],
    ['role' => 'user', 'content' => 'Message 5'],
    ['role' => 'assistant', 'content' => 'Response 5'],
];

echo "Original conversation: " . count($longConversation) . " messages\n";
echo "Estimated tokens: " . AgentHelpers::estimateTokens($longConversation) . "\n\n";

$trimmed = AgentHelpers::manageConversationHistory($longConversation, maxMessages: 3);

echo "After trimming to 3 pairs: " . count($trimmed) . " messages\n";
echo "Estimated tokens: " . AgentHelpers::estimateTokens($trimmed) . "\n\n";

// Example 4: Colorized output
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Colorized Console Output\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo AgentHelpers::colorize("✅ Success message\n", 'green');
echo AgentHelpers::colorize("⚠️  Warning message\n", 'yellow');
echo AgentHelpers::colorize("❌ Error message\n", 'red');
echo AgentHelpers::colorize("ℹ️  Info message\n", 'blue');
echo AgentHelpers::colorize("🔧 Debug message\n", 'cyan');
echo "\n";

// Example 5: Retry with backoff
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Retry with Exponential Backoff\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$attemptCount = 0;

try {
    $result = AgentHelpers::retryWithBackoff(
        fn: function() use (&$attemptCount) {
            $attemptCount++;
            echo "Attempt {$attemptCount}...\n";
            
            // Simulate success on 3rd attempt
            if ($attemptCount < 3) {
                throw new Exception("Simulated failure");
            }
            
            return "Success!";
        },
        maxAttempts: 5,
        initialDelayMs: 100
    );
    
    echo "Result: {$result}\n\n";
} catch (Exception $e) {
    echo "Failed after all attempts: {$e->getMessage()}\n\n";
}

// Example 6: Tool result formatting
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 6: Tool Result Formatting\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Success result
$successResult = AgentHelpers::formatToolResult(
    toolUseId: 'tool_123',
    result: ['value' => 42, 'status' => 'ok'],
    isError: false
);

echo "Success result:\n";
echo json_encode($successResult, JSON_PRETTY_PRINT) . "\n\n";

// Error result
$errorResult = AgentHelpers::formatToolResult(
    toolUseId: 'tool_456',
    result: 'Database connection failed',
    isError: true
);

echo "Error result:\n";
echo json_encode($errorResult, JSON_PRETTY_PRINT) . "\n\n";

// Example 7: Check for keywords
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 7: Keyword Detection\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$text1 = "Everything looks good, the task completed successfully.";
$text2 = "There is an issue with the calculation, it appears to be incorrect.";

$issueKeywords = ['issue', 'problem', 'error', 'incorrect', 'wrong'];

echo "Text: \"{$text1}\"\n";
echo "Contains issues? " . (AgentHelpers::containsWords($text1, $issueKeywords) ? 'Yes' : 'No') . "\n\n";

echo "Text: \"{$text2}\"\n";
echo "Contains issues? " . (AgentHelpers::containsWords($text2, $issueKeywords) ? 'Yes' : 'No') . "\n\n";

// Example 8: Create Console Logger
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 8: Console Logger\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create a console logger
$consoleLogger = AgentHelpers::createConsoleLogger('demo', 'info');

echo "Logging at different levels:\n\n";

$consoleLogger->debug('This is a debug message (will not show - level is info)');
$consoleLogger->info('This is an info message');
$consoleLogger->warning('This is a warning message');
$consoleLogger->error('This is an error message');

echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Helpers Demo Complete\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

