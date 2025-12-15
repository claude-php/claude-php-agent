# Tutorial 1: Your First Agent

**Time: 30 minutes** | **Difficulty: Beginner**

Now that you understand the concepts, let's build your first working AI agent! In this tutorial, we'll create a simple agent with a single tool using the Claude PHP Agent Framework.

## 🎯 Learning Objectives

By the end of this tutorial, you'll be able to:

- Create a basic agent with the framework
- Define a tool with proper input schema
- Execute a simple agent task
- Understand the agent's decision-making process
- Debug agent behavior with callbacks

## 🏗️ What We're Building

We'll create a **Calculator Agent** that can:

1. Receive math questions from users
2. Recognize when calculation is needed
3. Use a calculator tool to compute exact answers
4. Respond with the results

This simple agent demonstrates the complete Request → Reason → Act → Observe cycle.

## 📋 Prerequisites

Make sure you have:

- PHP 8.1+ installed
- Composer installed
- Claude PHP Agent Framework installed
- Anthropic API key configured

### Installation

```bash
composer require claude-php/claude-php-agent
```

### Environment Setup

Create a `.env` file:

```env
ANTHROPIC_API_KEY=your-api-key-here
```

## 🛠️ Step 1: Define Your Tool

Tools give agents capabilities beyond their knowledge. Let's create a calculator tool:

```php
<?php

use ClaudeAgents\Tools\Tool;

$calculatorTool = Tool::create('calculate')
    ->description(
        'Perform precise mathematical calculations. ' .
        'Supports basic arithmetic: +, -, *, /, and parentheses.'
    )
    ->stringParam(
        'expression',
        'The mathematical expression to evaluate (e.g., "2 + 2", "15 * 8")'
    )
    ->handler(function (array $input): string {
        $expression = $input['expression'];
        
        // Validate: only allow safe characters
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expression)) {
            return "Error: Invalid expression";
        }
        
        try {
            // In production, use a proper math parser library
            // like mossadal/math-parser or nxp/math-executor
            $result = eval("return {$expression};");
            return (string)$result;
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    });
```

### 🔑 Key Points

- **Name**: Simple, descriptive identifier
- **Description**: Helps Claude understand when to use the tool
- **Parameters**: Define what input the tool needs
- **Handler**: The actual function that executes

⚠️ **Security Note**: In production, never use `eval()` with user input! Use a proper math parser library.

## 🚀 Step 2: Create the Agent

Now let's create an agent and give it our calculator tool:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudePhp\ClaudePhp;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize Claude client
$client = new ClaudePhp(
    apiKey: $_ENV['ANTHROPIC_API_KEY']
);

// Configure agent
$config = AgentConfig::fromArray([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 1024,
    'max_iterations' => 5,
]);

// Create agent with calculator tool
$agent = Agent::create($client)
    ->withConfig($config)
    ->withTool($calculatorTool)
    ->withSystemPrompt(
        'You are a helpful assistant with access to a calculator. ' .
        'Use the calculator for precise mathematical calculations.'
    );
```

## ▶️ Step 3: Run the Agent

Let's ask it to solve a math problem:

```php
<?php

echo "Asking: What is 157 × 89?\n\n";

$response = $agent->run('What is 157 × 89?');

echo "Answer: {$response}\n";
```

### What Happens Behind the Scenes

```
1. Agent receives: "What is 157 × 89?"
   
2. Agent reasons: "This requires calculation, I should use the calculator tool"
   
3. Agent acts: Calls calculate("157 * 89")
   
4. Tool executes: Returns "13973"
   
5. Agent observes: Received result 13973
   
6. Agent responds: "157 × 89 equals 13,973"
```

## 🐛 Step 4: Add Debugging

Want to see what the agent is thinking? Add callbacks or use the helper utilities with logging:

### Option 1: Using Callbacks

```php
<?php

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTool($calculatorTool)
    ->onIteration(function ($iteration, $response, $context) {
        echo "\n━━━ Iteration {$iteration} ━━━\n";
        echo "Stop Reason: {$response->stop_reason}\n";
        
        if (isset($response->usage)) {
            echo "Tokens: {$response->usage->input_tokens} in, ";
            echo "{$response->usage->output_tokens} out\n";
        }
    })
    ->onToolCall(function ($tool, $input, $result) {
        echo "\n🔧 Tool Called: {$tool->name()}\n";
        echo "Input: " . json_encode($input) . "\n";
        echo "Result: {$result}\n";
    });

$response = $agent->run('What is (25 * 17) + (100 / 4)?');
```

### Option 2: Using AgentHelpers with Logger

```php
<?php

use ClaudeAgents\Helpers\AgentHelpers;

// Create a console logger for debugging
$logger = AgentHelpers::createConsoleLogger('agent', 'debug');

// Run with built-in ReAct loop and logging
$result = AgentHelpers::runAgentLoop(
    client: $client,
    messages: [['role' => 'user', 'content' => 'What is (25 * 17) + (100 / 4)?']],
    tools: [$calculatorTool],
    toolExecutor: fn($name, $input) => ($calculatorTool->handler())($input),
    config: [
        'max_iterations' => 10,
        'debug' => true,
        'logger' => $logger,
    ]
);
```

### Output

```
━━━ Iteration 1 ━━━
Stop Reason: tool_use
Tokens: 245 in, 87 out

🔧 Tool Called: calculate
Input: {"expression":"(25 * 17) + (100 / 4)"}
Result: 450

━━━ Iteration 2 ━━━
Stop Reason: end_turn
Tokens: 385 in, 45 out

Answer: (25 × 17) + (100 ÷ 4) = 425 + 25 = 450
```

## 📊 Step 5: Track Token Usage

Monitor costs by tracking token usage:

```php
<?php

$totalInputTokens = 0;
$totalOutputTokens = 0;

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTool($calculatorTool)
    ->onIteration(function ($iteration, $response, $context) 
        use (&$totalInputTokens, &$totalOutputTokens) {
        
        if (isset($response->usage)) {
            $totalInputTokens += $response->usage->input_tokens;
            $totalOutputTokens += $response->usage->output_tokens;
        }
    });

$response = $agent->run('Calculate 157 × 89');

echo "\n📈 Token Usage:\n";
echo "Input Tokens: {$totalInputTokens}\n";
echo "Output Tokens: {$totalOutputTokens}\n";
echo "Total: " . ($totalInputTokens + $totalOutputTokens) . "\n";

// Estimate cost (Claude 3.5 Sonnet pricing)
$inputCost = ($totalInputTokens / 1_000_000) * 3.00;
$outputCost = ($totalOutputTokens / 1_000_000) * 15.00;
$totalCost = $inputCost + $outputCost;

echo "Estimated Cost: $" . number_format($totalCost, 6) . "\n";
```

## 🎯 Complete Example

Here's a complete working example:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize client
$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);

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

// Configure agent
$config = AgentConfig::fromArray([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 1024,
    'max_iterations' => 5,
]);

// Create and run agent
$agent = Agent::create($client)
    ->withConfig($config)
    ->withTool($calculator)
    ->withSystemPrompt('You are a helpful math assistant with a calculator.')
    ->onIteration(function ($iter, $resp, $ctx) {
        echo "Iteration {$iter}: {$resp->stop_reason}\n";
    });

// Test questions
$questions = [
    'What is 157 × 89?',
    'Calculate (25 * 17) + (100 / 4)',
    'What is 15% of 250?',
];

foreach ($questions as $question) {
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Q: {$question}\n";
    echo "A: " . $agent->run($question) . "\n";
}
```

## 🧪 Try It Yourself

Save the code above as `my_first_agent.php` and run:

```bash
php my_first_agent.php
```

## 🐛 Troubleshooting

### Issue: Agent doesn't use the tool

**Possible causes:**
- Tool description doesn't match the task
- Agent thinks it can answer without the tool
- System prompt doesn't encourage tool use

**Fix:** Make descriptions more specific:

```php
// ❌ Bad
->description('Do math')

// ✅ Good
->description('Perform precise mathematical calculations with guaranteed accuracy')
```

### Issue: "Expression is undefined"

**Cause:** The expression variable isn't properly sanitized.

**Fix:** Always validate input:

```php
if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expression)) {
    return "Error: Invalid expression";
}
```

### Issue: High token usage

**Causes:**
- Too many iterations
- Verbose system prompts
- Tool descriptions too long

**Fix:** Optimize configuration:

```php
$config = AgentConfig::fromArray([
    'max_iterations' => 3,  // Reduce if tasks are simple
    'max_tokens' => 512,    // Reduce if responses are short
]);
```

## ✅ Checkpoint

Before moving on, make sure you understand:

- [ ] How to create a tool with Tool::create()
- [ ] How to define tool parameters
- [ ] How to create an agent with Agent::create()
- [ ] How to run an agent with ->run()
- [ ] How to add callbacks for debugging
- [ ] How to track token usage

## 🚀 Next Steps

Congratulations! You've built your first agent. But real tasks often require multiple steps and multiple tools.

**[Tutorial 2: ReAct Loop Basics →](./02-ReAct-Basics.md)**

Learn how to implement proper multi-step reasoning with the ReAct pattern!

## 💡 Key Takeaways

1. **Agents need tools** to extend their capabilities
2. **Tools have three parts**: name, description, and handler
3. **Callbacks enable debugging** and monitoring
4. **Token tracking** helps manage costs
5. **Start simple** and add complexity gradually

## 📚 Further Reading

- [Tools Documentation](../../Tools.md)
- [Agent Configuration](../../contracts.md)
- [Basic Agent Example](../../../examples/basic_agent.php)
- [Multi-Tool Agent Example](../../../examples/multi_tool_agent.php)

