# Context Management

The Context Management system provides tools to monitor and manage conversation context to prevent exceeding token limits during agent execution.

## Overview

The Context system consists of three main components:

1. **TokenCounter** - Estimates token usage for messages and tools
2. **ContextEditor** - Utilities for manipulating message history
3. **ContextManager** - Automatic context window management

## Quick Start

### Enable Context Management on an Agent

```php
use ClaudeAgents\Agent;

$agent = Agent::create($client)
    ->withContextManagement(
        maxContextTokens: 100000,
        options: [
            'compact_threshold' => 0.8,  // Compact at 80% usage
            'auto_compact' => true,       // Auto-compact when threshold exceeded
            'clear_tool_results' => true, // Remove tool results during compaction
        ]
    )
    ->withTool($calculator)
    ->run('Solve complex math problems');
```

## TokenCounter

Estimates token usage for text, messages, and conversations.

### Basic Usage

```php
use ClaudeAgents\Context\TokenCounter;

// Estimate tokens for text
$tokens = TokenCounter::estimateTokens("Hello, world!");
echo "Estimated tokens: {$tokens}";

// Estimate tokens for a message
$message = [
    'role' => 'user',
    'content' => 'What is the weather?',
];
$tokens = TokenCounter::estimateMessageTokens($message);

// Estimate tokens for a conversation
$messages = [
    ['role' => 'user', 'content' => 'Hello'],
    ['role' => 'assistant', 'content' => 'Hi there!'],
];
$total = TokenCounter::estimateConversationTokens($messages);
```

### With Tools

```php
$messages = [/* your messages */];
$tools = [/* tool definitions */];

$totalTokens = TokenCounter::estimateTotal($messages, $tools);
echo "Total estimated tokens: {$totalTokens}";
```

## ContextEditor

Provides utilities for editing and analyzing message history.

### Clear Tool Results

Remove tool result blocks to reduce context size:

```php
use ClaudeAgents\Context\ContextEditor;

$messages = [
    [
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => 'Calculate'],
            ['type' => 'tool_result', 'tool_use_id' => '123', 'content' => 'Result'],
        ],
    ],
];

$cleaned = ContextEditor::clearToolResults($messages);
// Tool results removed, only text remains
```

### Keep Recent Messages

Keep only the most recent messages:

```php
// Keep last 10 messages plus system prompt
$recent = ContextEditor::keepRecent($messages, keepCount: 10);
```

### Summarize Early Messages

Compact early messages with a summary:

```php
// Summarize old messages, keep recent 5 unchanged
$summarized = ContextEditor::summarizeEarly($messages, keepCount: 5);
```

### Extract Text Only

Remove non-text content blocks:

```php
$textOnly = ContextEditor::extractTextOnly($messages);
```

### Get Conversation Statistics

Analyze message history:

```php
$stats = ContextEditor::getStats($messages);

echo "Total messages: {$stats['total_messages']}\n";
echo "User messages: {$stats['user_messages']}\n";
echo "Assistant messages: {$stats['assistant_messages']}\n";
echo "System messages: {$stats['system_messages']}\n";
echo "Estimated tokens: {$stats['total_estimated_tokens']}\n";
```

### Remove Messages by Role

```php
// Remove all system messages
$filtered = ContextEditor::removeByRole($messages, 'system');
```

## ContextManager

Automatically manages context window to prevent exceeding limits.

### Basic Configuration

```php
use ClaudeAgents\Context\ContextManager;

$manager = new ContextManager(
    maxContextTokens: 100000,
    options: [
        'compact_threshold' => 0.8,  // Compact at 80% usage
        'auto_compact' => true,
        'clear_tool_results' => true,
    ]
);
```

### Check if Messages Fit

```php
$messages = [/* your messages */];
$tools = [/* tool definitions */];

if ($manager->fitsInContext($messages, $tools)) {
    echo "Messages fit within context window";
} else {
    echo "Messages exceed context window";
}
```

### Get Usage Percentage

```php
$usage = $manager->getUsagePercentage($messages, $tools);
echo "Context usage: " . ($usage * 100) . "%";
```

### Compact Messages

Automatically reduce message history to fit within context:

```php
$compacted = $manager->compactMessages($messages, $tools);

// Compacted messages will:
// 1. Remove tool results (if enabled)
// 2. Preserve system message
// 3. Keep most recent messages that fit
```

### Adjust Context Window

```php
// Change maximum tokens
$manager->setMaxContextTokens(200000);

// Get current maximum
$max = $manager->getMaxContextTokens();

// Get compact threshold
$threshold = $manager->getCompactThreshold();
```

## Integration with AgentContext

The `AgentContext` class automatically uses `ContextManager` when configured:

```php
use ClaudeAgents\AgentContext;
use ClaudeAgents\Context\ContextManager;

$contextManager = new ContextManager(100000);

$context = new AgentContext(
    client: $client,
    task: $task,
    tools: $tools,
    config: $config,
    memory: null,
    contextManager: $contextManager,
);

// Context automatically compacts when threshold exceeded
$context->addMessage(['role' => 'user', 'content' => 'New message']);

// Check context status
echo "Fits in context: " . ($context->fitsInContext() ? 'Yes' : 'No') . "\n";
echo "Context usage: " . ($context->getContextUsage() * 100) . "%\n";
```

## Advanced Usage

### Manual Compaction Strategy

```php
use ClaudeAgents\Context\ContextEditor;
use ClaudeAgents\Context\TokenCounter;

// Custom compaction logic
function customCompact(array $messages, int $maxTokens): array
{
    // 1. Clear tool results
    $messages = ContextEditor::clearToolResults($messages);
    
    // 2. Check if now fits
    $tokens = TokenCounter::estimateConversationTokens($messages);
    if ($tokens <= $maxTokens) {
        return $messages;
    }
    
    // 3. Summarize early messages
    $messages = ContextEditor::summarizeEarly($messages, keepCount: 3);
    
    // 4. If still too large, keep only recent
    $tokens = TokenCounter::estimateConversationTokens($messages);
    if ($tokens > $maxTokens) {
        $messages = ContextEditor::keepRecent($messages, keepCount: 5);
    }
    
    return $messages;
}
```

### Monitoring Context Usage

```php
use ClaudeAgents\Agent;

$agent = Agent::create($client)
    ->withContextManagement(100000)
    ->onIteration(function($iteration, $response, $context) {
        $usage = $context->getContextUsage();
        echo "Iteration {$iteration}: Context usage " . 
             round($usage * 100, 1) . "%\n";
        
        if ($usage > 0.9) {
            echo "WARNING: Context nearly full!\n";
        }
    })
    ->run($task);
```

### Custom Context Manager

```php
use ClaudeAgents\Context\ContextManager;

class AggressiveContextManager extends ContextManager
{
    public function compactMessages(array $messages, array $tools = []): array
    {
        // More aggressive compaction strategy
        // Only keep last 3 messages plus system
        
        $compacted = [];
        
        // Keep system message
        if (!empty($messages) && ($messages[0]['role'] ?? '') === 'system') {
            $compacted[] = $messages[0];
            $messages = array_slice($messages, 1);
        }
        
        // Keep only last 3 messages
        $recent = array_slice($messages, -3);
        $compacted = array_merge($compacted, $recent);
        
        return $compacted;
    }
}

// Use custom manager
$agent = Agent::create($client)
    ->withContextManager(new AggressiveContextManager(50000))
    ->run($task);
```

## Configuration Options

### ContextManager Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `compact_threshold` | float | 0.8 | Compact when usage exceeds this percentage (0.0-1.0) |
| `auto_compact` | bool | true | Automatically compact when threshold exceeded |
| `clear_tool_results` | bool | true | Remove tool results during compaction |
| `logger` | LoggerInterface | NullLogger | Logger for context operations |

## Best Practices

### 1. Set Appropriate Limits

```php
// For Claude 3.5 Sonnet (200K context)
$agent->withContextManagement(190000); // Leave buffer

// For extended conversations
$agent->withContextManagement(150000, [
    'compact_threshold' => 0.7, // Compact earlier
]);
```

### 2. Monitor Long-Running Agents

```php
$agent->onIteration(function($iter, $resp, $context) {
    if ($context->getContextUsage() > 0.8) {
        error_log("High context usage at iteration {$iter}");
    }
});
```

### 3. Preserve Important Context

```php
// Keep system message and recent context
$manager = new ContextManager(100000, [
    'clear_tool_results' => true,  // Remove verbose tool outputs
    'compact_threshold' => 0.75,   // Compact before it's critical
]);
```

### 4. Test with Large Conversations

```php
// Simulate long conversation
$messages = [];
for ($i = 0; $i < 100; $i++) {
    $messages[] = ['role' => 'user', 'content' => str_repeat('test ', 100)];
    $messages[] = ['role' => 'assistant', 'content' => str_repeat('response ', 100)];
}

$manager = new ContextManager(10000);
$compacted = $manager->compactMessages($messages);

echo "Original: " . count($messages) . " messages\n";
echo "Compacted: " . count($compacted) . " messages\n";
```

## Troubleshooting

### Context Still Exceeds Limit

If compaction isn't enough:

```php
// Use more aggressive threshold
$agent->withContextManagement(100000, [
    'compact_threshold' => 0.5,  // Compact at 50%
]);

// Or manually clear more context
$messages = ContextEditor::keepRecent($messages, 3);
```

### Tool Results Taking Too Much Space

```php
// Always clear tool results
$agent->withContextManagement(100000, [
    'clear_tool_results' => true,
]);
```

### Need to Preserve All Context

```php
// Disable auto-compaction and handle manually
$agent->withContextManagement(100000, [
    'auto_compact' => false,
]);

// Check context manually
if (!$context->fitsInContext()) {
    // Handle overflow - maybe save to external storage
    saveToDatabase($context->getMessages());
}
```

## See Also

- [Agent Configuration](AgentConfig.md)
- [Memory System](Memory.md)
- [Loop Strategies](Loops.md)

