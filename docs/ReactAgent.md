# ReactAgent Documentation

## Overview

The `ReactAgent` is a simplified wrapper around the base `Agent` class that implements the **ReAct (Reason-Act-Observe)** pattern for problem-solving. It provides an intuitive interface for creating agents that can reason about tasks, use tools to gather information or perform actions, and observe results to make informed decisions.

## Features

- 🤔 **Reason-Act-Observe Pattern**: Built-in implementation of the ReAct framework
- 🔧 **Simple Tool Integration**: Easy tool registration with fluent API
- 🔄 **Automatic Loop Management**: Handles iteration logic automatically
- 📊 **Execution Tracking**: Built-in callbacks for monitoring iterations and tool usage
- 🎯 **Token Management**: Automatic tracking of API token usage
- ⚙️ **Flexible Configuration**: Extensive configuration options for customization

## Installation

The ReactAgent is included in the `claude-php-agent` package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');

// Create a simple tool
$calculator = Tool::create('calculate')
    ->description('Perform calculations')
    ->stringParam('expression', 'Math expression to evaluate')
    ->handler(function (array $input): string {
        return (string) eval("return {$input['expression']};");
    });

// Create the ReactAgent
$agent = new ReactAgent($client, [
    'name' => 'my_agent',
    'tools' => [$calculator],
    'system' => 'You are a helpful math assistant.',
]);

// Run a task
$result = $agent->run('What is 25 * 17?');

if ($result->isSuccess()) {
    echo $result->getAnswer(); // "425"
}
```

## The ReAct Pattern

The ReAct pattern alternates between three phases:

1. **Reason**: The agent thinks about what needs to be done
2. **Act**: The agent uses tools to gather information or perform actions
3. **Observe**: The agent reviews the results and decides on next steps

This cycle continues until the agent reaches a conclusion or hits the maximum iteration limit.

### Example ReAct Flow

```
User: "What's the weather in Paris and what's 15% of 200?"

Iteration 1 (Reason):
  Agent: "I need to get weather data and perform a calculation"

Iteration 1 (Act):
  Tool: get_weather("Paris")
  Tool: calculate("200 * 0.15")

Iteration 1 (Observe):
  Agent: "Weather is sunny, 22°C. Calculation result is 30"

Iteration 2 (Reason):
  Agent: "I have all the information needed"

Iteration 2 (Act):
  [No tools needed]

Iteration 2 (Observe):
  Agent: "The weather in Paris is sunny and 22°C. 15% of 200 is 30."
```

## Configuration

The ReactAgent accepts configuration options in its constructor:

```php
$agent = new ReactAgent($client, [
    'name' => 'my_react_agent',           // Agent identifier
    'system' => 'Custom system prompt',   // System instructions
    'model' => 'claude-3-5-sonnet-20241022', // Claude model to use
    'max_iterations' => 10,               // Maximum reasoning loops
    'max_tokens' => 4096,                 // Max tokens per response
    'tools' => [$tool1, $tool2],         // Initial tools
    'thinking' => [                       // Extended thinking configuration
        'type' => 'enabled',
        'budget_tokens' => 10000,
    ],
    'logger' => $logger,                  // PSR-3 logger instance
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'react_agent'` | Unique identifier for the agent |
| `system` | string | `null` | Custom system prompt |
| `model` | string | `'claude-3-5-sonnet-20241022'` | Claude model to use |
| `max_iterations` | int | `10` | Maximum reasoning loop iterations |
| `max_tokens` | int | `4096` | Maximum tokens per API response |
| `tools` | array | `[]` | Array of ToolInterface instances |
| `thinking` | array | `[]` | Extended thinking configuration |
| `logger` | LoggerInterface | `null` | PSR-3 compatible logger |

## Working with Tools

### Adding Tools

```php
$agent = new ReactAgent($client);

// Add single tool
$agent->addTool($weatherTool);

// Add multiple tools via fluent interface
$agent->addTool($calculator)
      ->addTool($search)
      ->addTool($database);

// Or provide tools in constructor
$agent = new ReactAgent($client, [
    'tools' => [$calculator, $search, $database],
]);
```

### Creating Tools

```php
use ClaudeAgents\Tools\Tool;

// Simple tool
$greeter = Tool::create('greet')
    ->description('Greet a person by name')
    ->stringParam('name', 'Person\'s name')
    ->handler(function (array $input): string {
        return "Hello, {$input['name']}!";
    });

// Tool with multiple parameters
$calculator = Tool::create('calculate')
    ->description('Perform arithmetic operations')
    ->numberParam('a', 'First number')
    ->numberParam('b', 'Second number')
    ->stringParam('operation', 'Operation: +, -, *, /')
    ->handler(function (array $input): float {
        return match($input['operation']) {
            '+' => $input['a'] + $input['b'],
            '-' => $input['a'] - $input['b'],
            '*' => $input['a'] * $input['b'],
            '/' => $input['a'] / $input['b'],
        };
    });

// Tool returning complex data
$database = Tool::create('query_users')
    ->description('Query user database')
    ->stringParam('query', 'Search query')
    ->handler(function (array $input): string {
        $users = DB::query($input['query']);
        return json_encode($users);
    });
```

## Callbacks and Monitoring

### Iteration Callback

Track each reasoning iteration:

```php
$agent->onIteration(function ($iteration, $response, $context) {
    echo "Iteration {$iteration}\n";
    echo "Current answer: {$context->getAnswer()}\n";
});
```

### Tool Execution Callback

Monitor tool usage:

```php
$agent->onToolExecution(function ($tool, $input, $result) {
    echo "Tool: {$tool}\n";
    echo "Input: " . json_encode($input) . "\n";
    echo "Result: {$result}\n";
});
```

### Chaining Callbacks

```php
$agent = new ReactAgent($client)
    ->addTool($calculator)
    ->onIteration(fn($i) => echo "Iteration {$i}\n")
    ->onToolExecution(fn($t) => echo "Used tool: {$t}\n");
```

## Running Tasks

### Simple Task

```php
$result = $agent->run('What is 15 * 23?');

if ($result->isSuccess()) {
    echo $result->getAnswer(); // "345"
}
```

### Complex Multi-Step Task

```php
$result = $agent->run(
    'Find the weather in London, calculate 20% of 500, ' .
    'and search for information about PHP'
);

if ($result->isSuccess()) {
    echo $result->getAnswer();
    echo "Iterations: {$result->getIterations()}\n";
    echo "Tools used: " . count($result->getToolCalls()) . "\n";
}
```

### Error Handling

```php
$result = $agent->run('Complex task...');

if ($result->isSuccess()) {
    echo "Success: {$result->getAnswer()}\n";
} else {
    echo "Error: {$result->getError()}\n";
    echo "Iterations completed: {$result->getIterations()}\n";
}
```

## Result Object

The `AgentResult` object provides comprehensive information about the execution:

```php
$result = $agent->run('Your task');

// Status
$result->isSuccess();           // bool
$result->hasFailed();          // bool
$result->isCompleted();        // bool

// Content
$result->getAnswer();          // string - final answer
$result->getError();           // string - error message if failed

// Execution info
$result->getIterations();      // int - number of iterations
$result->getMessages();        // array - conversation history
$result->getToolCalls();       // array - all tool executions

// Token usage
$usage = $result->getTokenUsage();
echo $usage['input'];          // Input tokens
echo $usage['output'];         // Output tokens
echo $usage['total'];          // Total tokens

// Metadata
$metadata = $result->getMetadata();
```

## Advanced Examples

### Research Assistant

```php
$search = Tool::create('web_search')
    ->stringParam('query', 'Search query')
    ->handler(fn($i) => performWebSearch($i['query']));

$summarize = Tool::create('summarize')
    ->stringParam('text', 'Text to summarize')
    ->handler(fn($i) => summarizeText($i['text']));

$agent = new ReactAgent($client, [
    'name' => 'researcher',
    'tools' => [$search, $summarize],
    'system' => 'You are a research assistant. Search for information ' .
                'and provide clear, concise summaries.',
]);

$result = $agent->run('Research the latest developments in quantum computing');
```

### Data Analysis Agent

```php
$queryDb = Tool::create('query_database')
    ->stringParam('sql', 'SQL query')
    ->handler(fn($i) => DB::query($i['sql']));

$analyze = Tool::create('analyze_data')
    ->stringParam('data', 'JSON data to analyze')
    ->handler(fn($i) => analyzeData($i['data']));

$visualize = Tool::create('create_chart')
    ->stringParam('data', 'Data for chart')
    ->stringParam('type', 'Chart type')
    ->handler(fn($i) => createChart($i['data'], $i['type']));

$agent = new ReactAgent($client, [
    'name' => 'analyst',
    'tools' => [$queryDb, $analyze, $visualize],
    'max_iterations' => 15,
]);

$result = $agent->run(
    'Query sales data for last quarter, analyze trends, ' .
    'and create a visualization'
);
```

### Customer Support Agent

```php
$getTicket = Tool::create('get_ticket')
    ->stringParam('ticket_id', 'Support ticket ID')
    ->handler(fn($i) => Support::getTicket($i['ticket_id']));

$searchKb = Tool::create('search_kb')
    ->stringParam('query', 'Knowledge base search')
    ->handler(fn($i) => KB::search($i['query']));

$updateTicket = Tool::create('update_ticket')
    ->stringParam('ticket_id', 'Ticket ID')
    ->stringParam('response', 'Response message')
    ->handler(fn($i) => Support::update($i['ticket_id'], $i['response']));

$agent = new ReactAgent($client, [
    'name' => 'support_bot',
    'tools' => [$getTicket, $searchKb, $updateTicket],
    'system' => 'You are a customer support agent. Be helpful, ' .
                'empathetic, and provide clear solutions.',
]);

$result = $agent->run('Help with ticket #12345');
```

## Best Practices

### 1. Provide Clear System Prompts

```php
$agent = new ReactAgent($client, [
    'system' => 'You are a financial analyst. When analyzing data, ' .
                'always consider risk factors and provide specific ' .
                'recommendations with supporting evidence.',
]);
```

### 2. Set Appropriate Iteration Limits

```php
// Simple tasks
$agent = new ReactAgent($client, ['max_iterations' => 5]);

// Complex reasoning tasks
$agent = new ReactAgent($client, ['max_iterations' => 15]);

// Research and exploration
$agent = new ReactAgent($client, ['max_iterations' => 25]);
```

### 3. Use Descriptive Tool Names

```php
// Good
$tool = Tool::create('search_product_inventory')
    ->description('Search product inventory by SKU or name');

// Less clear
$tool = Tool::create('search')
    ->description('Search stuff');
```

### 4. Validate Tool Inputs

```php
$tool = Tool::create('send_email')
    ->stringParam('to', 'Recipient email')
    ->stringParam('subject', 'Email subject')
    ->handler(function (array $input): string {
        if (!filter_var($input['to'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email');
        }
        return sendEmail($input['to'], $input['subject']);
    });
```

### 5. Handle Tool Errors Gracefully

```php
$tool = Tool::create('api_call')
    ->handler(function (array $input): string {
        try {
            return callExternalApi($input['endpoint']);
        } catch (\Exception $e) {
            return "Error calling API: {$e->getMessage()}";
        }
    });
```

### 6. Monitor Token Usage

```php
$agent->onIteration(function ($iteration, $response, $context) {
    $usage = $context->getTokenUsage();
    if ($usage['total'] > 100000) {
        throw new \RuntimeException('Token limit exceeded');
    }
});
```

### 7. Use Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('agent');
$logger->pushHandler(new StreamHandler('agent.log', Logger::INFO));

$agent = new ReactAgent($client, [
    'logger' => $logger,
]);
```

## Performance Optimization

### Reduce Iterations

```php
// Provide context in system prompt to reduce iterations
$agent = new ReactAgent($client, [
    'system' => 'You have access to: weather tool, calculator. ' .
                'Always try to answer in the fewest steps possible.',
    'max_iterations' => 5,
]);
```

### Cache Tool Results

```php
$cache = [];

$tool = Tool::create('expensive_operation')
    ->handler(function (array $input) use (&$cache): string {
        $key = json_encode($input);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $result = expensiveOperation($input);
        $cache[$key] = $result;
        return $result;
    });
```

### Batch Tool Operations

```php
$tool = Tool::create('batch_query')
    ->handler(function (array $input): string {
        // Query multiple items at once
        $ids = explode(',', $input['ids']);
        $results = DB::whereIn('id', $ids)->get();
        return json_encode($results);
    });
```

## Comparison with Base Agent

| Feature | ReactAgent | Base Agent |
|---------|-----------|-----------|
| API Simplicity | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| Configuration | Simple constructor | Fluent builder |
| ReAct Pattern | Built-in | Manual via ReactLoop |
| Use Case | General tasks | Advanced customization |
| Learning Curve | Low | Medium |

### When to Use ReactAgent

- ✅ Building simple to moderate complexity agents
- ✅ Need quick agent prototyping
- ✅ Want built-in ReAct pattern
- ✅ Prefer constructor-based configuration

### When to Use Base Agent

- ✅ Need fine-grained control over execution
- ✅ Want to use custom loop strategies
- ✅ Building complex multi-agent systems
- ✅ Prefer fluent builder pattern

## API Reference

### Constructor

```php
public function __construct(
    ClaudePhp $client,
    array $options = []
): self
```

### Methods

#### `addTool(ToolInterface $tool): self`
Add a tool to the agent.

#### `onIteration(callable $callback): self`
Set iteration callback. Callback receives: `($iteration, $response, $context)`.

#### `onToolExecution(callable $callback): self`
Set tool execution callback. Callback receives: `($tool, $input, $result)`.

#### `run(string $task): AgentResult`
Execute the agent with the given task.

#### `getName(): string`
Get the agent name.

#### `getAgent(): Agent`
Get the underlying base Agent instance.

## Troubleshooting

### Agent Hits Max Iterations

```php
// Increase iteration limit
$agent = new ReactAgent($client, ['max_iterations' => 20]);

// Or provide more context in system prompt
$agent = new ReactAgent($client, [
    'system' => 'Focus on efficiency. Try to complete tasks in 3-5 steps.',
]);
```

### Tools Not Being Called

```php
// Ensure tools have clear descriptions
$tool = Tool::create('search')
    ->description('Search the web for information. Use this when you need ' .
                  'current information or facts not in your training data.')
    ->handler(...);
```

### High Token Usage

```php
// Reduce max_tokens
$agent = new ReactAgent($client, ['max_tokens' => 2048]);

// Monitor usage
$agent->onIteration(function ($i, $r, $c) {
    echo "Tokens: {$c->getTokenUsage()['total']}\n";
});
```

### Inconsistent Results

```php
// Set temperature to 0 for deterministic behavior
// (Note: Requires base Agent customization)
$agent = $reactAgent->getAgent();
$config = $agent->getConfig();
// Temperature control via config
```

## See Also

- [ReactAgent Tutorial](tutorials/ReactAgent_Tutorial.md)
- [Examples](../examples/react_agent.php)
- [Base Agent Documentation](Agent.md)
- [Tool Creation Guide](Tools.md)
- [Multi-Agent Systems](MultiAgent.md)

## Further Reading

- [ReAct: Synergizing Reasoning and Acting in Language Models](https://arxiv.org/abs/2210.03629)
- [Claude API Documentation](https://docs.anthropic.com/)
- [Agent Design Patterns](agent-selection-guide.md)

