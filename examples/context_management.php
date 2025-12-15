<?php

/**
 * Context Management Example
 * 
 * Demonstrates how to use the Context Management system to monitor
 * and control conversation context size during agent execution.
 * 
 * This example shows:
 * - Enabling automatic context management
 * - Monitoring context usage
 * - Manual context compaction strategies
 * - Using ContextEditor utilities
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Context\ContextEditor;
use ClaudeAgents\Context\ContextManager;
use ClaudeAgents\Context\TokenCounter;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "Error: ANTHROPIC_API_KEY environment variable not set\n";
    exit(1);
}

$client = new ClaudePhp(apiKey: $apiKey);

echo "=== Context Management Examples ===\n\n";

// ============================================================================
// Example 1: Basic Token Estimation
// ============================================================================
echo "Example 1: Token Estimation\n";
echo str_repeat("-", 50) . "\n";

$text = "This is a sample message to estimate token count.";
$tokens = TokenCounter::estimateTokens($text);
echo "Text: '{$text}'\n";
echo "Estimated tokens: {$tokens}\n\n";

$messages = [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
    ['role' => 'user', 'content' => 'What is 2 + 2?'],
    ['role' => 'assistant', 'content' => 'The answer is 4.'],
];

$conversationTokens = TokenCounter::estimateConversationTokens($messages);
echo "Conversation tokens: {$conversationTokens}\n";

$stats = ContextEditor::getStats($messages);
echo "Message breakdown:\n";
echo "  Total: {$stats['total_messages']}\n";
echo "  User: {$stats['user_messages']}\n";
echo "  Assistant: {$stats['assistant_messages']}\n";
echo "  System: {$stats['system_messages']}\n\n";

// ============================================================================
// Example 2: Context Editor Utilities
// ============================================================================
echo "Example 2: Context Editor Utilities\n";
echo str_repeat("-", 50) . "\n";

// Create a conversation with tool results
$messagesWithTools = [
    ['role' => 'user', 'content' => 'Calculate 5 * 10'],
    [
        'role' => 'assistant',
        'content' => [
            ['type' => 'text', 'text' => 'Let me calculate that.'],
            [
                'type' => 'tool_use',
                'id' => 'tool_1',
                'name' => 'calculator',
                'input' => ['operation' => 'multiply', 'a' => 5, 'b' => 10],
            ],
        ],
    ],
    [
        'role' => 'user',
        'content' => [
            [
                'type' => 'tool_result',
                'tool_use_id' => 'tool_1',
                'content' => '50',
            ],
        ],
    ],
    ['role' => 'assistant', 'content' => 'The result is 50.'],
];

echo "Original messages: " . count($messagesWithTools) . "\n";

// Clear tool results
$cleaned = ContextEditor::clearToolResults($messagesWithTools);
echo "After clearing tool results: " . count($cleaned) . " messages\n";

// Verify tool results are removed
$hasToolResults = false;
foreach ($cleaned as $msg) {
    if (is_array($msg['content'] ?? null)) {
        foreach ($msg['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'tool_result') {
                $hasToolResults = true;
            }
        }
    }
}
echo "Tool results present: " . ($hasToolResults ? 'Yes' : 'No') . "\n\n";

// ============================================================================
// Example 3: Keeping Recent Messages
// ============================================================================
echo "Example 3: Keeping Recent Messages\n";
echo str_repeat("-", 50) . "\n";

$longConversation = [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
];

for ($i = 1; $i <= 10; $i++) {
    $longConversation[] = ['role' => 'user', 'content' => "Message {$i}"];
    $longConversation[] = ['role' => 'assistant', 'content' => "Response {$i}"];
}

echo "Original conversation: " . count($longConversation) . " messages\n";

$recent = ContextEditor::keepRecent($longConversation, keepCount: 5);
echo "After keeping recent 5: " . count($recent) . " messages\n";
echo "System message preserved: " . 
    (($recent[0]['role'] ?? '') === 'system' ? 'Yes' : 'No') . "\n";
echo "Last message content: {$recent[count($recent) - 1]['content']}\n\n";

// ============================================================================
// Example 4: Context Manager
// ============================================================================
echo "Example 4: Context Manager\n";
echo str_repeat("-", 50) . "\n";

$manager = new ContextManager(
    maxContextTokens: 1000,
    options: [
        'compact_threshold' => 0.8,
        'clear_tool_results' => true,
    ]
);

// Create messages that will exceed limit
$largeMessages = [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
];

for ($i = 1; $i <= 20; $i++) {
    $largeMessages[] = [
        'role' => 'user',
        'content' => str_repeat("This is a long message number {$i}. ", 20),
    ];
}

echo "Original messages: " . count($largeMessages) . "\n";
echo "Fits in context: " . 
    ($manager->fitsInContext($largeMessages) ? 'Yes' : 'No') . "\n";

$usage = $manager->getUsagePercentage($largeMessages);
echo "Context usage: " . round($usage * 100, 1) . "%\n";

$compacted = $manager->compactMessages($largeMessages);
echo "Compacted messages: " . count($compacted) . "\n";
echo "Compacted fits: " . 
    ($manager->fitsInContext($compacted) ? 'Yes' : 'No') . "\n\n";

// ============================================================================
// Example 5: Agent with Context Management
// ============================================================================
echo "Example 5: Agent with Context Management\n";
echo str_repeat("-", 50) . "\n";

// Create a simple tool
$calculator = Tool::create(
    name: 'calculator',
    description: 'Performs basic arithmetic operations',
    parameters: [
        'operation' => [
            'type' => 'string',
            'description' => 'The operation to perform',
            'enum' => ['add', 'subtract', 'multiply', 'divide'],
        ],
        'a' => ['type' => 'number', 'description' => 'First number'],
        'b' => ['type' => 'number', 'description' => 'Second number'],
    ],
    handler: function (array $input): string {
        $a = $input['a'];
        $b = $input['b'];
        
        return match ($input['operation']) {
            'add' => (string)($a + $b),
            'subtract' => (string)($a - $b),
            'multiply' => (string)($a * $b),
            'divide' => $b != 0 ? (string)($a / $b) : 'Error: Division by zero',
            default => 'Unknown operation',
        };
    }
);

echo "Creating agent with context management...\n";

$contextUsageHistory = [];

$agent = Agent::create($client)
    ->withName('context-aware-agent')
    ->withTool($calculator)
    ->withContextManagement(
        maxContextTokens: 100000,
        options: [
            'compact_threshold' => 0.8,
            'clear_tool_results' => true,
        ]
    )
    ->maxIterations(5)
    ->maxTokens(1000)
    ->onIteration(function ($iteration, $response, $context) use (&$contextUsageHistory) {
        $usage = $context->getContextUsage();
        $contextUsageHistory[] = $usage;
        
        echo "  Iteration {$iteration}: ";
        echo "Context usage " . round($usage * 100, 1) . "%, ";
        echo "Messages: " . count($context->getMessages()) . "\n";
    });

$task = "Calculate the following: 15 * 8, then add 50 to the result, then divide by 2. " .
        "Show your work step by step.";

echo "Task: {$task}\n\n";

$result = $agent->run($task);

echo "\nResult:\n";
echo "Success: " . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Answer: {$result->getAnswer()}\n";
echo "Iterations: {$result->getIterations()}\n";

$usage = $result->getMetadata()['token_usage'] ?? [];
echo "Token usage: {$usage['input']} input, {$usage['output']} output\n";

if (!empty($contextUsageHistory)) {
    echo "\nContext usage over time:\n";
    foreach ($contextUsageHistory as $i => $usage) {
        $bar = str_repeat('=', (int)($usage * 50));
        echo sprintf("  Iter %d: [%-50s] %.1f%%\n", $i + 1, $bar, $usage * 100);
    }
}

// ============================================================================
// Example 6: Custom Compaction Strategy
// ============================================================================
echo "\n" . str_repeat("=", 50) . "\n";
echo "Example 6: Custom Compaction Strategy\n";
echo str_repeat("-", 50) . "\n";

function customCompactStrategy(array $messages, int $maxTokens): array
{
    echo "Running custom compaction...\n";
    
    // Step 1: Clear tool results
    $messages = ContextEditor::clearToolResults($messages);
    $tokens = TokenCounter::estimateConversationTokens($messages);
    echo "  After clearing tool results: {$tokens} tokens\n";
    
    if ($tokens <= $maxTokens) {
        return $messages;
    }
    
    // Step 2: Summarize early messages
    $messages = ContextEditor::summarizeEarly($messages, keepCount: 3);
    $tokens = TokenCounter::estimateConversationTokens($messages);
    echo "  After summarizing early: {$tokens} tokens\n";
    
    if ($tokens <= $maxTokens) {
        return $messages;
    }
    
    // Step 3: Keep only recent messages
    $messages = ContextEditor::keepRecent($messages, keepCount: 5);
    $tokens = TokenCounter::estimateConversationTokens($messages);
    echo "  After keeping recent: {$tokens} tokens\n";
    
    return $messages;
}

$testMessages = [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
];

for ($i = 1; $i <= 15; $i++) {
    $testMessages[] = [
        'role' => 'user',
        'content' => str_repeat("Question {$i} ", 30),
    ];
    $testMessages[] = [
        'role' => 'assistant',
        'content' => str_repeat("Answer {$i} ", 30),
    ];
}

echo "Original: " . count($testMessages) . " messages, " .
    TokenCounter::estimateConversationTokens($testMessages) . " tokens\n";

$compacted = customCompactStrategy($testMessages, 500);

echo "Final: " . count($compacted) . " messages, " .
    TokenCounter::estimateConversationTokens($compacted) . " tokens\n";

echo "\n=== Examples Complete ===\n";

