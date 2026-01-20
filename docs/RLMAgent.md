# RLMAgent Documentation

## Overview

The `RLMAgent` (Recursive Language Model Agent) is an implementation based on the MIT CSAIL research paper ["Recursive Language Models" (arXiv:2512.24601v1)](https://arxiv.org/html/2512.24601v1). It enables processing of inputs that are **up to 2 orders of magnitude beyond** the model's context window limits.

The key insight is that long prompts should be treated as part of the external environment that the LLM can symbolically interact with, rather than feeding them directly into the neural network context.

## Features

- 🔄 **Recursive Processing**: Break down complex tasks into sub-tasks
- 📊 **REPL Environment**: Input stored as variable, not in context
- 🔍 **Smart Exploration**: Peek, slice, and search input without loading it all
- 💾 **Variable Storage**: Store intermediate results for multi-step analysis
- 📈 **Scales Beyond Context**: Process inputs 10-100x larger than context window
- 🎯 **Token Efficiency**: Only examine what's needed

## Installation

The RLMAgent is included in the `claude-php-agent` package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\RLMAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');

// Create the RLM Agent
$agent = new RLMAgent($client, [
    'name' => 'document_analyzer',
    'system' => 'You are a document analyzer.',
]);

// Process a large input without loading it into context
$result = $agent->runWithInput(
    'Summarize the key points from this document.',
    $largeDocument  // Can be much larger than context window!
);

if ($result->isSuccess()) {
    echo $result->getAnswer();
}
```

## How It Works

### The REPL Environment

When you call `runWithInput()`, the RLM agent:

1. **Stores the input** in a REPL context (not in the LLM context window)
2. **Informs the LLM** about the input metadata (size, lines, etc.)
3. **Provides tools** to examine the input programmatically
4. **Allows recursion** for decomposing complex tasks

```
┌─────────────────────────────────────────────────────────────────────┐
│                        RLM Architecture                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   User Task ──────► RLM Agent ──────► Claude API                   │
│                         │                  │                        │
│                         ▼                  ▼                        │
│   Large Input ──► REPL Context        Tools ◄──────────────────┐   │
│                      │                   │                      │   │
│                      │    ┌──────────────┼──────────────┐      │   │
│                      │    │              │              │      │   │
│                      ▼    ▼              ▼              ▼      │   │
│                   peek  slice        search       recursive    │   │
│                  _input _input       _input         _call ─────┘   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Built-in Tools

The RLM agent provides these tools automatically:

| Tool | Description | Parameters |
|------|-------------|------------|
| `peek_input` | View substring by character position | `start`, `length` |
| `slice_input` | Extract a range of lines | `start_line`, `end_line` |
| `search_input` | Regex search with context | `pattern`, `context_lines`, `max_results` |
| `get_input_info` | Get input metadata | none |
| `set_variable` | Store intermediate results | `name`, `value` |
| `get_variable` | Retrieve stored values | `name` |
| `recursive_call` | Process sub-task recursively | `task`, `input_source` |

## Configuration

```php
$agent = new RLMAgent($client, [
    'name' => 'my_rlm_agent',           // Agent identifier
    'model' => 'claude-sonnet-4-5',         // Claude model
    'max_tokens' => 4096,               // Max tokens per response
    'max_iterations' => 20,             // Maximum loop iterations
    'max_recursion_depth' => 10,        // Maximum recursion depth
    'tools' => [$customTool1, $tool2],  // Additional custom tools
    'system' => 'Custom system prompt', // System instructions
    'thinking' => [                     // Extended thinking
        'type' => 'enabled',
        'budget_tokens' => 10000,
    ],
    'logger' => $logger,                // PSR-3 logger
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'rlm_agent'` | Unique identifier |
| `model` | string | `'claude-sonnet-4-5'` | Claude model to use |
| `max_tokens` | int | `4096` | Max tokens per response |
| `max_iterations` | int | `20` | Maximum reasoning iterations |
| `max_recursion_depth` | int | `10` | Maximum recursion depth |
| `tools` | array | `[]` | Additional custom tools |
| `system` | string | `null` | Custom system prompt prefix |
| `thinking` | array | `null` | Extended thinking configuration |
| `logger` | LoggerInterface | `null` | PSR-3 logger |

## Advanced Usage

### Processing Large Documents

```php
$agent = new RLMAgent($client, [
    'name' => 'doc_analyzer',
    'max_iterations' => 25,  // More iterations for complex analysis
]);

// Load a very large document (e.g., 1MB of text)
$document = file_get_contents('large_document.txt');

$result = $agent->runWithInput(
    'Analyze this document. Find all mentions of "security" and ' .
    'summarize the security-related concerns.',
    $document
);
```

### Using Custom Tools

```php
use ClaudeAgents\Tools\Tool;

// Create a custom analysis tool
$sentimentTool = Tool::create('analyze_sentiment')
    ->description('Analyze sentiment of text')
    ->stringParam('text', 'Text to analyze')
    ->handler(function (array $input): string {
        // Your sentiment analysis logic
        return json_encode(['sentiment' => 'positive', 'confidence' => 0.85]);
    });

$agent = new RLMAgent($client, [
    'tools' => [$sentimentTool],
]);
```

### Callbacks and Monitoring

```php
$agent = new RLMAgent($client);

// Track iterations
$agent->onIteration(function ($iteration, $response, $context) {
    echo "Iteration {$iteration}\n";
});

// Track tool usage
$agent->onToolExecution(function ($tool, $input, $result) {
    echo "Tool {$tool} called\n";
});

// Track recursion
$agent->onRecursion(function ($depth, $task, $result) {
    if ($result === null) {
        echo "Entering recursion depth {$depth}\n";
    } else {
        echo "Exiting recursion depth {$depth}\n";
    }
});

// Unified progress updates
$agent->onUpdate(function (AgentUpdate $update): void {
    echo "[{$update->getType()}] " . json_encode($update->getData()) . "\n";
});
```

### Using Variables for Multi-Step Analysis

```php
$result = $agent->runWithInput(
    'Perform analysis in steps: ' .
    '1) Search for errors and store the count in a variable ' .
    '2) Search for warnings and store that count ' .
    '3) Calculate the error-to-warning ratio ' .
    '4) Provide a summary with the stored data',
    $logContent
);
```

### Recursive Decomposition

For very large inputs, the agent can recursively call itself on portions:

```php
$agent = new RLMAgent($client, [
    'max_recursion_depth' => 5,  // Allow up to 5 levels of recursion
]);

$result = $agent->runWithInput(
    'This document has multiple sections. Process each section separately ' .
    'using recursive_call with input_source set to the appropriate line ranges, ' .
    'then combine the results.',
    $multiSectionDocument
);
```

## Input Source Specifications

When using `recursive_call`, you can specify input sources:

| Source | Format | Description |
|--------|--------|-------------|
| Full input | `"full"` | Use the entire original input |
| Line slice | `"slice:START:END"` | Use lines START to END |
| Variable | `"variable:NAME"` | Use a stored variable's value |

```php
// These are used by the LLM when calling the recursive_call tool:
// - "full" - process entire input
// - "slice:1:100" - process lines 1-100
// - "slice:101:200" - process lines 101-200  
// - "variable:extracted_data" - process a stored variable
```

## Result Object

The `AgentResult` includes RLM-specific metadata:

```php
$result = $agent->runWithInput($task, $input);

// Standard result methods
$result->isSuccess();       // bool
$result->getAnswer();       // string
$result->getIterations();   // int
$result->getTokenUsage();   // array

// RLM-specific metadata
$metadata = $result->getMetadata();
$rlmData = $metadata['rlm'];

echo $rlmData['input_chars'];      // Input character count
echo $rlmData['input_lines'];      // Input line count
echo $rlmData['input_words'];      // Input word count
echo $rlmData['recursion_depth'];  // Maximum recursion depth reached
print_r($rlmData['recursion_history']); // History of recursive calls
print_r($rlmData['variables']);    // Variables stored during execution
```

## Best Practices

### 1. Use Descriptive Tasks

```php
// Good - specific and actionable
$agent->runWithInput(
    'Search for all ERROR entries in this log file. Count them by component ' .
    '(auth, database, api, etc.) and report which component has the most errors.',
    $logContent
);

// Less effective - too vague
$agent->runWithInput('Analyze this log', $logContent);
```

### 2. Leverage Search for Large Inputs

```php
// For large inputs, encourage the agent to search first
$agent->runWithInput(
    'This is a very large document. First use search_input to find relevant ' .
    'sections mentioning "security", then analyze those specific sections.',
    $largeDocument
);
```

### 3. Use Variables for Complex Workflows

```php
$agent->runWithInput(
    'Multi-step analysis: ' .
    '1. Search for critical errors and store count in "critical_count" variable ' .
    '2. Search for warnings and store in "warning_count" ' .
    '3. Calculate and report the error-to-warning ratio',
    $logContent
);
```

### 4. Set Appropriate Recursion Depth

```php
// Simple analysis
$agent = new RLMAgent($client, ['max_recursion_depth' => 3]);

// Complex hierarchical documents
$agent = new RLMAgent($client, ['max_recursion_depth' => 10]);
```

### 5. Monitor Token Usage

```php
$agent->onIteration(function ($iteration, $response, $context) {
    $usage = $context->getTokenUsage();
    if ($usage['total'] > 50000) {
        // Consider stopping or warning
    }
});
```

## Use Cases

### Log Analysis

```php
$agent->runWithInput(
    'Analyze this server log. Find all errors, group them by type, ' .
    'and identify any patterns or recurring issues.',
    file_get_contents('server.log')
);
```

### Document Review

```php
$agent->runWithInput(
    'Review this legal document. Extract all clauses related to ' .
    'intellectual property and summarize the key obligations.',
    file_get_contents('contract.txt')
);
```

### Code Analysis

```php
$agent->runWithInput(
    'Analyze this codebase dump. Find all function definitions, ' .
    'identify any security-related functions, and list potential issues.',
    $codebaseDump
);
```

### Data Extraction

```php
$agent->runWithInput(
    'Extract all email addresses and phone numbers from this document. ' .
    'Store them in variables and provide a summary at the end.',
    $documentContent
);
```

## Comparison with Other Agents

| Feature | RLMAgent | ReactAgent | PlanExecuteAgent |
|---------|----------|------------|------------------|
| Large Input Handling | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐ |
| Recursion Support | ⭐⭐⭐⭐⭐ | ⭐ | ⭐⭐ |
| Token Efficiency | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ |
| General Tasks | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| Setup Complexity | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |

### When to Use RLMAgent

- ✅ Processing inputs larger than context window
- ✅ Log file analysis
- ✅ Large document review
- ✅ Data extraction from large sources
- ✅ Multi-step analysis with intermediate results

### When to Use Other Agents

- Use **ReactAgent** for general tasks with tools
- Use **PlanExecuteAgent** for structured multi-step workflows
- Use **ChainOfThoughtAgent** for reasoning-heavy tasks

## Troubleshooting

### Agent Not Using Tools Effectively

```php
// Provide clearer instructions in the system prompt
$agent = new RLMAgent($client, [
    'system' => 'You are analyzing a large input. ALWAYS use get_input_info first ' .
                'to understand the data structure, then use search_input to find ' .
                'relevant content before drawing conclusions.',
]);
```

### Max Recursion Depth Reached

```php
// Increase depth if needed for complex hierarchical tasks
$agent = new RLMAgent($client, [
    'max_recursion_depth' => 15,  // Increase from default 10
]);

// Or encourage the agent to be more efficient
$agent->runWithInput(
    'Analyze efficiently - try to minimize recursive calls by using ' .
    'search_input to find all relevant sections at once.',
    $input
);
```

### High Token Usage

```php
// Monitor and limit iterations
$agent = new RLMAgent($client, [
    'max_iterations' => 10,  // Reduce from default 20
    'max_tokens' => 2048,    // Smaller responses
]);
```

## API Reference

### Constructor

```php
public function __construct(
    ClaudePhp $client,
    array $options = []
): self
```

### Methods

#### `runWithInput(string $task, string $input, ?REPLContext $context = null): AgentResult`
Execute with separate task and input. Primary method for RLM usage.

#### `run(string $task): AgentResult`
Execute with task only (input empty). Implements AgentInterface.

#### `addTool(ToolInterface $tool): self`
Add a custom tool.

#### `onIteration(callable $callback): self`
Set iteration callback.

#### `onToolExecution(callable $callback): self`
Set tool execution callback.

#### `onRecursion(callable $callback): self`
Set recursion callback. Receives `($depth, $task, $result)`.

#### `onUpdate(callable $callback): self`
Set unified progress callback.

#### `getName(): string`
Get the agent name.

#### `getCurrentContext(): ?REPLContext`
Get current REPL context (only during execution).

#### `resolveInputSource(string $source, REPLContext $context): ?string`
Resolve an input source specification to actual content.

## See Also

- [RLM Agent Example](../examples/rlm_agent.php)
- [ReactAgent Documentation](ReactAgent.md)
- [Tools Documentation](Tools.md)
- [Agent Selection Guide](agent-selection-guide.md)

## References

- Zhang, A.L., Kraska, T., & Khattab, O. (2026). Recursive Language Models. arXiv:2512.24601v1. MIT CSAIL.
