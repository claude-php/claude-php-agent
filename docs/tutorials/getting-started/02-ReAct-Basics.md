# Tutorial 2: ReAct Loop Basics

**Time: 45 minutes** | **Difficulty: Intermediate**

In the previous tutorial, we built an agent that could make one tool call. But what about tasks that require multiple steps? That's where the **ReAct pattern** comes in. In this tutorial, we'll implement a proper ReAct loop that enables iterative reasoning and multi-step problem solving.

## 🎯 Learning Objectives

By the end of this tutorial, you'll be able to:

- Implement the ReAct (Reason-Act-Observe) loop
- Handle multiple tool calls in sequence
- Maintain conversation state across iterations
- Implement proper stop conditions
- Debug agent reasoning steps
- Prevent infinite loops with iteration limits

## 🔄 What is ReAct?

**ReAct** stands for **Reason** → **Act** → **Observe**, and it's the fundamental pattern for autonomous agents.

### The Loop

```
Start
  ↓
┌─────────────────────────────────┐
│  REASON                         │
│  "What do I need to do next?"   │
│  "What info is missing?"        │
└───────────┬─────────────────────┘
            ↓
┌─────────────────────────────────┐
│  ACT                            │
│  "Call tool X with params Y"    │
│  Or "I have enough to answer"   │
└───────────┬─────────────────────┘
            ↓
┌─────────────────────────────────┐
│  OBSERVE                        │
│  "Tool returned Z"              │
│  "Do I have what I need?"       │
└───────────┬─────────────────────┘
            │
        ┌───────┐
        │ Done? │
        └───┬───┘
            │
      No ───┴─── Yes
      │           │
      │           ↓
      │        [Return
      │         Answer]
      │
      └──> (Back to REASON)
```

### Why It Matters

**Without ReAct**, agents can only:

- Answer questions with their training data
- Make ONE tool call per task

**With ReAct**, agents can:

- Gather information step-by-step
- Chain multiple tools together
- Adapt based on tool results
- Solve complex multi-step problems

## 🏗️ What We're Building

We'll build a ReAct agent that can:

1. Accept complex tasks requiring multiple steps
2. Reason about what to do next
3. Execute tools iteratively
4. Observe results and adapt
5. Continue until the task is complete
6. Respect iteration limits

### Example Task

**Question**: "What is (50 × 30) + (100 - 25)?"

**Traditional Agent** (from Tutorial 1):

- Can only make ONE tool call
- Would fail or give incomplete answer

**ReAct Agent** (what we're building):

- Iteration 1: Calculate 50 × 30 = 1,500
- Iteration 2: Calculate 100 - 25 = 75
- Iteration 3: Calculate 1,500 + 75 = 1,575
- Final Answer: "1,575"

## 📋 Prerequisites

Make sure you have:

- Completed [Tutorial 1: Your First Agent](./01-First-Agent.md)
- Understanding of tool definitions
- Claude PHP Agent Framework installed
- API key configured

## 🔑 Core Components

### 1. The Main Loop

The ReAct loop is simple but powerful:

```php
<?php

use ClaudeAgents\Helpers\AgentHelpers;

$messages = [['role' => 'user', 'content' => $userTask]];
$maxIterations = 10;
$iteration = 0;

while ($iteration < $maxIterations) {
    $iteration++;

    // Call Claude
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 4096,
        'messages' => $messages,
        'tools' => $tools
    ]);

    // Add response to history
    $messages[] = [
        'role' => 'assistant',
        'content' => $response->content
    ];

    // Check if done
    if ($response->stop_reason === 'end_turn') {
        // Task complete!
        break;
    }

    // Execute tools if requested
    if ($response->stop_reason === 'tool_use') {
        // Extract and execute tools
        // Add results to messages
        // Loop continues...
    }
}
```

### 2. Stop Conditions

Your loop needs to exit when:

1. **Task Complete**: `stop_reason === 'end_turn'`
2. **Max Iterations**: `$iteration >= $maxIterations`
3. **Error**: Tool execution fails critically
4. **No Tools**: `stop_reason === 'tool_use'` but no tool uses found

### 3. State Management

The conversation history IS your state:

```php
$messages = [
    ['role' => 'user', 'content' => 'Task...'],             // Turn 1
    ['role' => 'assistant', 'content' => [/* tool use */]], // Turn 2
    ['role' => 'user', 'content' => [/* tool result */]],   // Turn 3
    ['role' => 'assistant', 'content' => [/* tool use */]], // Turn 4
    // ... continues until done
];
```

Each iteration adds to this history, giving Claude context about what's already been done.

## 🛠️ Implementation

### Step 1: Define Your Tools

Let's create a calculator tool that we'll use multiple times:

```php
<?php

use ClaudeAgents\Tools\Tool;

$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations with precision')
    ->stringParam('expression', 'Math expression to evaluate (e.g., "25 * 17")')
    ->handler(function (array $input): string {
        $expression = $input['expression'];

        // Validate input
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expression)) {
            return "Error: Invalid expression";
        }

        try {
            // In production, use a math parser library
            $result = eval("return {$expression};");
            return (string)$result;
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    });
```

### Step 2: Implement the ReAct Loop

Using the AgentHelpers for simplicity:

```php
<?php

use ClaudeAgents\Helpers\AgentHelpers;

// Create logger for debugging
$logger = AgentHelpers::createConsoleLogger('react_agent', 'debug');

// Initial message
$messages = [
    ['role' => 'user', 'content' => 'Calculate (50 * 30) + (100 - 25). Show your work.']
];

// Tool executor
$toolExecutor = function(string $name, array $input) use ($calculator) {
    if ($name === 'calculate') {
        return ($calculator->handler())($input);
    }
    return "Unknown tool: {$name}";
};

// Run the ReAct loop
$result = AgentHelpers::runAgentLoop(
    client: $client,
    messages: $messages,
    tools: [AgentHelpers::createTool(
        'calculate',
        'Perform mathematical calculations',
        ['expression' => ['type' => 'string', 'description' => 'Math expression']],
        ['expression']
    )],
    toolExecutor: $toolExecutor,
    config: [
        'max_iterations' => 10,
        'debug' => true,
        'logger' => $logger,
    ]
);

// Display result
if ($result['success']) {
    $answer = AgentHelpers::extractTextContent($result['response']);
    echo "\nFinal Answer: {$answer}\n";
    echo "Iterations: {$result['iterations']}\n";
} else {
    echo "Error: {$result['error']}\n";
}
```

### Step 3: Watch It Work

When you run this, you'll see something like:

```
[2024-12-16 10:30:00] react_agent.DEBUG: Agent Iteration 1/10
[2024-12-16 10:30:01] react_agent.DEBUG: Tool call {"tool":"calculate","parameters":{"expression":"50 * 30"}}
[2024-12-16 10:30:01] react_agent.DEBUG: Tool execution result {"tool":"calculate","result":"1500"}

[2024-12-16 10:30:02] react_agent.DEBUG: Agent Iteration 2/10
[2024-12-16 10:30:03] react_agent.DEBUG: Tool call {"tool":"calculate","parameters":{"expression":"100 - 25"}}
[2024-12-16 10:30:03] react_agent.DEBUG: Tool execution result {"tool":"calculate","result":"75"}

[2024-12-16 10:30:04] react_agent.DEBUG: Agent Iteration 3/10
[2024-12-16 10:30:05] react_agent.DEBUG: Tool call {"tool":"calculate","parameters":{"expression":"1500 + 75"}}
[2024-12-16 10:30:05] react_agent.DEBUG: Tool execution result {"tool":"calculate","result":"1575"}

[2024-12-16 10:30:06] react_agent.INFO: Agent completed successfully {"iterations":4}

Final Answer: First, I calculated 50 × 30 = 1,500. Then I calculated 100 - 25 = 75.
Finally, I added them together: 1,500 + 75 = 1,575.

Iterations: 4
```

## 🐛 Debugging ReAct Loops

### Visualize Each Iteration

```php
<?php

use ClaudeAgents\Helpers\AgentHelpers;

// The debug flag and logger show what's happening
$result = AgentHelpers::runAgentLoop(
    client: $client,
    messages: $messages,
    tools: $tools,
    toolExecutor: $executor,
    config: [
        'debug' => true,  // Enable debug output
        'logger' => AgentHelpers::createConsoleLogger('agent', 'debug'),
    ]
);
```

### Common Issues

**Issue: Infinite Loop**

**Symptom**: Agent keeps making tool calls without completing

**Causes**:

- Max iterations too high (or missing)
- Tool results not formatted correctly
- Tool always returns incomplete information

**Fix**:

```php
// Always set a reasonable limit
$config = ['max_iterations' => 10];

// Check if stuck
if ($result['iterations'] >= 5 && !$hasProgressed) {
    echo "Warning: Agent may be stuck\n";
}
```

**Issue: Loop Exits Too Early**

**Symptom**: Agent stops before task is complete

**Causes**:

- Max iterations too low
- Misinterpreting stop_reason
- Tool result contains errors

**Fix**:

```php
// Increase iterations for complex tasks
$config = ['max_iterations' => 15];

// Check result
if (!$result['success']) {
    echo "Agent did not complete: {$result['error']}\n";
}
```

**Issue: Tool Results Not Working**

**Symptom**: Agent doesn't use tool results

**Causes**:

- `tool_use_id` doesn't match
- Results not added to conversation
- Results in wrong format

**Fix**:

```php
// Ensure proper formatting
$toolResult = AgentHelpers::formatToolResult(
    toolUseId: $toolUse['id'],  // Must match!
    result: $result,
    isError: false
);
```

## 📊 Iteration Limits

### How to Choose

| Task Complexity | Suggested Limit  | Example                |
| --------------- | ---------------- | ---------------------- |
| Simple          | 3-5 iterations   | Single calculation     |
| Medium          | 5-10 iterations  | Multi-step calculation |
| Complex         | 10-15 iterations | Research + analysis    |
| Very Complex    | 15-25 iterations | Multi-stage workflows  |

### Costs

Each iteration uses tokens:

- Tool definitions: ~50-200 tokens
- System prompt: ~350 tokens
- Growing message history: 100-1000+ tokens
- Claude's response: 50-500+ tokens

**Example**: 10-iteration task might use 5,000-15,000 tokens total.

### Token Management

```php
<?php

use ClaudeAgents\Helpers\AgentHelpers;

// Estimate conversation size
$estimatedTokens = AgentHelpers::estimateTokens($messages);

if ($estimatedTokens > 50000) {
    echo "Warning: Conversation getting large\n";
    // Trim history
    $messages = AgentHelpers::manageConversationHistory($messages, maxMessages: 10);
}
```

## 🎯 Best Practices

### 1. Always Set Max Iterations

```php
// ✅ Good
$config = ['max_iterations' => 10];

// ❌ Bad - potential infinite loop
// No limit!
```

### 2. Preserve Conversation History

```php
// ✅ Good - Keep all messages
$messages[] = ['role' => 'assistant', 'content' => $response->content];
$messages[] = ['role' => 'user', 'content' => $toolResults];

// ❌ Bad - Losing context
$messages = [['role' => 'user', 'content' => $toolResults]];
```

### 3. Handle All Stop Reasons

```php
// ✅ Good - Handle all cases
switch ($response->stop_reason) {
    case 'end_turn':
        // Complete
        break;
    case 'tool_use':
        // Execute tools
        break;
    case 'max_tokens':
        // Increase max_tokens
        break;
    default:
        // Unexpected
        break;
}
```

### 4. Log for Debugging

```php
// ✅ Good - Detailed logging
$logger = AgentHelpers::createConsoleLogger('agent', 'debug');
$config = ['debug' => true, 'logger' => $logger];

// ❌ Bad - No visibility
// Silent execution
```

### 5. Validate Tool Results

```php
// ✅ Good - Check before adding
$toolResults = AgentHelpers::extractToolUses($response);
if (empty($toolResults)) {
    $logger->warning('No tool results to process');
    break;
}
```

## 🎯 Complete Example

Here's a complete working ReAct agent:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Helpers\AgentHelpers;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize
$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);
$logger = AgentHelpers::createConsoleLogger('react_demo', 'info');

// Define calculator tool
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'Math expression to evaluate')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return "Error: Invalid expression";
        }
        try {
            return (string)eval("return {$expr};");
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    });

// Tool executor
$executor = fn($name, $input) => ($calculator->handler())($input);

// Test multi-step tasks
$tasks = [
    'Calculate (25 * 17) + (100 / 4)',
    'What is 15% of 250, then add 50 to that result?',
    'Calculate: ((100 + 50) * 2) - (30 / 3)',
];

foreach ($tasks as $task) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Task: {$task}\n";
    echo str_repeat("=", 60) . "\n";

    $result = AgentHelpers::runAgentLoop(
        client: $client,
        messages: [['role' => 'user', 'content' => $task]],
        tools: [AgentHelpers::createTool(
            'calculate',
            'Perform mathematical calculations',
            ['expression' => ['type' => 'string']],
            ['expression']
        )],
        toolExecutor: $executor,
        config: [
            'max_iterations' => 10,
            'debug' => false,  // Set to true for detailed output
            'logger' => $logger,
        ]
    );

    if ($result['success']) {
        $answer = AgentHelpers::extractTextContent($result['response']);
        echo "\nAnswer: {$answer}\n";
        echo "Iterations: {$result['iterations']}\n";
    } else {
        echo "Error: {$result['error']}\n";
    }
}
```

## ✅ Checkpoint

Before moving on, make sure you understand:

- [ ] What ReAct (Reason-Act-Observe) means
- [ ] How to implement a basic ReAct loop
- [ ] Why iteration limits are critical
- [ ] How to maintain conversation state
- [ ] When the loop should exit
- [ ] How to debug ReAct iterations

## 🚀 Next Steps

Now you have a ReAct agent that can handle multi-step tasks with a single tool. But real agents need multiple diverse tools to be truly useful.

**[Tutorial 3: Multi-Tool Agent →](./03-Multi-Tool.md)**

Learn how to give your agent multiple tools and help Claude choose the right one!

## 💡 Key Takeaways

1. **ReAct enables autonomy** through iterative reasoning
2. **Always set iteration limits** to prevent infinite loops
3. **Preserve conversation history** for context
4. **Handle all stop reasons** properly
5. **Use logging** to understand agent behavior
6. **Start with 10 iterations** and adjust based on task complexity

## 📚 Further Reading

- [ReAct Paper](https://arxiv.org/abs/2210.03629) - Original research
- [Best Practices Guide](../../BestPractices.md#react-loop-patterns)
- [Agent Helpers Documentation](../../helpers/)
- [ReAct Agent Example](../../../examples/react_agent.php)
