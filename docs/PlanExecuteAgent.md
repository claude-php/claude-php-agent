# PlanExecuteAgent

The `PlanExecuteAgent` implements the plan-and-execute pattern, a systematic approach to task completion that separates planning from execution. This agent first creates a detailed step-by-step plan, then executes each step sequentially, with optional plan revision based on intermediate results.

## Table of Contents

- [Overview](#overview)
- [Key Concepts](#key-concepts)
- [Features](#features)
- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Configuration Options](#configuration-options)
- [Planning Modes](#planning-modes)
- [Working with Tools](#working-with-tools)
- [Adaptive Replanning](#adaptive-replanning)
- [Best Practices](#best-practices)
- [API Reference](#api-reference)

## Overview

The Plan-Execute pattern breaks complex tasks into two distinct phases:

1. **Planning Phase**: Creates a detailed, actionable plan with numbered steps
2. **Execution Phase**: Executes each step sequentially, with context from previous steps
3. **Synthesis Phase**: Combines all step results into a comprehensive final answer

This separation enables:
- More systematic task completion
- Better visibility into the reasoning process
- Ability to adapt the plan based on results
- Improved handling of complex multi-step tasks

## Key Concepts

### Why Plan-Execute?

The plan-execute pattern is particularly effective for:

- **Complex Tasks**: Breaking down multi-faceted problems into manageable steps
- **Multi-Tool Workflows**: Coordinating multiple tool calls in sequence
- **Sequential Dependencies**: When later steps depend on earlier results
- **Adaptive Execution**: Adjusting strategy based on intermediate outcomes

### Plan-Execute vs Direct Execution

**Direct Agent Execution:**
```
User Task → Agent Response (single interaction)
```

**Plan-Execute Pattern:**
```
User Task → Create Plan → Execute Step 1 → Execute Step 2 → ... → Synthesize Result
```

## Features

- ✅ Systematic plan creation
- ✅ Sequential step execution with context
- ✅ Optional adaptive replanning
- ✅ Tool integration support
- ✅ Progress tracking and metadata
- ✅ Token usage monitoring
- ✅ PSR-3 logging support
- ✅ Comprehensive error handling
- ✅ Step-by-step result tracking

## Installation

The `PlanExecuteAgent` is included in the `claude-php-agent` package:

```bash
composer require claude-php-agent
```

## Basic Usage

### Simple Plan-Execute

```php
use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new PlanExecuteAgent($client);

$result = $agent->run(
    'Write a comprehensive product description for a new eco-friendly water bottle. ' .
    'Include key features, target audience, and environmental benefits.'
);

if ($result->isSuccess()) {
    echo $result->getAnswer();
    
    // View the execution plan
    $metadata = $result->getMetadata();
    echo "Executed {$metadata['plan_steps']} steps\n";
}
```

### With Tools

```php
use ClaudeAgents\Tools\Tool;

$calculatorTool = Tool::create('calculator')
    ->description('Perform calculations')
    ->stringParam('expression', 'Math expression to evaluate')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        $result = eval("return {$expr};");
        return "Result: {$result}";
    });

$agent = new PlanExecuteAgent($client);
$agent->addTool($calculatorTool);

$result = $agent->run(
    'Calculate the total cost: venue $500, catering for 20 people at $25 each, ' .
    'decorations $150. Provide itemized breakdown.'
);
```

## Configuration Options

### Constructor Parameters

```php
new PlanExecuteAgent(ClaudePhp $client, array $options = [])
```

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | `string` | `'plan_execute_agent'` | Agent identifier |
| `model` | `string` | `'claude-sonnet-4-5'` | Claude model to use |
| `max_tokens` | `int` | `2048` | Maximum tokens per response |
| `tools` | `array` | `[]` | Array of ToolInterface objects |
| `allow_replan` | `bool` | `true` | Enable adaptive replanning |
| `logger` | `LoggerInterface` | `NullLogger` | PSR-3 logger instance |

### Example Configuration

```php
$agent = new PlanExecuteAgent($client, [
    'name' => 'content_planner',
    'model' => 'claude-3-5-sonnet-20241022',
    'max_tokens' => 4096,
    'allow_replan' => true,
    'logger' => $logger,
]);
```

## Planning Modes

### Fixed Planning (allow_replan = false)

The agent creates a plan once and executes all steps without modification:

```php
$agent = new PlanExecuteAgent($client, [
    'allow_replan' => false,
]);

$result = $agent->run('Write a technical blog post about PHP 8.3 features');
```

**Best for:**
- Well-defined tasks
- Predictable workflows
- Tasks where steps won't fail

### Adaptive Planning (allow_replan = true)

The agent can revise the plan based on step results:

```php
$agent = new PlanExecuteAgent($client, [
    'allow_replan' => true, // Default
]);

$result = $agent->run('Research and summarize latest AI developments');
```

**Best for:**
- Complex, uncertain tasks
- Tasks where steps might fail
- Workflows requiring adaptation

## Working with Tools

### Adding Single Tools

```php
$dateTool = Tool::create('get_date')
    ->description('Get current date and time')
    ->stringParam('format', 'Date format string', required: false)
    ->handler(function (array $input): string {
        $format = $input['format'] ?? 'Y-m-d H:i:s';
        return date($format);
    });

$agent->addTool($dateTool);
```

### Adding Multiple Tools

```php
$agent = new PlanExecuteAgent($client, [
    'tools' => [$calculatorTool, $dateTool, $searchTool],
]);

// Or chain them
$agent->addTool($tool1)
      ->addTool($tool2)
      ->addTool($tool3);
```

### Tool Integration Example

```php
$weatherTool = Tool::create('get_weather')
    ->description('Get weather information')
    ->stringParam('location', 'City name')
    ->handler(function (array $input): string {
        // Simulated weather API call
        return "Weather in {$input['location']}: Sunny, 72°F";
    });

$agent = new PlanExecuteAgent($client);
$agent->addTool($weatherTool);

$result = $agent->run(
    'Plan a weekend outdoor event. Check the weather, ' .
    'suggest 3 activities, and estimate costs.'
);
```

## Adaptive Replanning

### How Replanning Works

When `allow_replan` is enabled, the agent:

1. Executes a step
2. Analyzes the result for failure indicators
3. If failure detected, creates a revised plan
4. Continues with the new plan

### Failure Detection

The agent looks for indicators like:
- "error"
- "failed"
- "unable"
- "cannot"
- "impossible"

```php
// Step execution
Step 1: "Unable to access the database"

// Replanning triggered
New Plan:
1. Try alternative data source
2. Use cached data if available
3. Provide estimate based on historical data
```

### Custom Replanning Behavior

```php
$agent = new PlanExecuteAgent($client, [
    'allow_replan' => true,
]);

$result = $agent->run(
    'Analyze sales data and create report. If data unavailable, ' .
    'use last quarter\'s trends to estimate.'
);

// Agent will adapt if data access fails
```

## Best Practices

### 1. Choose Appropriate Tasks

**Good for Plan-Execute:**
- Multi-step workflows
- Tasks requiring tool coordination
- Complex analysis or creation tasks
- Tasks with sequential dependencies

**Not ideal for:**
- Simple single-step queries
- Tasks requiring real-time interaction
- Highly parallel independent tasks

### 2. Provide Clear Task Descriptions

```php
// ✅ Good: Clear, specific task
$result = $agent->run(
    'Create a marketing email: ' .
    '1) Subject line (under 50 chars), ' .
    '2) Opening paragraph, ' .
    '3) Call-to-action button text, ' .
    '4) Closing. Target audience: small business owners.'
);

// ❌ Vague: Unclear expectations
$result = $agent->run('Make something for marketing');
```

### 3. Use Tools for Factual Operations

```php
// Let tools handle facts, let agent handle reasoning
$calculatorTool = Tool::create('calculator')
    ->description('Precise calculations')
    ->handler(function ($input) { /* ... */ });

$agent->addTool($calculatorTool);

$result = $agent->run(
    'Calculate ROI for three investment scenarios and recommend the best option'
);
```

### 4. Monitor Token Usage

```php
$result = $agent->run($task);

if ($result->isSuccess()) {
    $usage = $result->getTokenUsage();
    
    // Plan-execute uses more tokens than simple agents
    echo "Total tokens: {$usage['total']}\n";
    
    // Cost estimation (approximate)
    $cost = ($usage['input'] * 0.003 + $usage['output'] * 0.015) / 1000;
    echo "Estimated cost: $" . number_format($cost, 4) . "\n";
}
```

### 5. Handle Errors Gracefully

```php
$result = $agent->run($complexTask);

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    // Check if replanning occurred
    if (count($metadata['step_results']) > $metadata['plan_steps']) {
        echo "Note: Plan was adapted during execution\n";
    }
    
    echo $result->getAnswer();
} else {
    // Log the error
    error_log("Plan-Execute failed: " . $result->getError());
    
    // Provide fallback
    echo "Unable to complete the task. Please try simplifying the request.\n";
}
```

### 6. Leverage Step Results

```php
$result = $agent->run($task);

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    // Access individual step results
    foreach ($metadata['step_results'] as $step) {
        echo "Step {$step['step']}: {$step['description']}\n";
        echo "Result: " . substr($step['result'], 0, 100) . "...\n\n";
    }
}
```

## API Reference

### PlanExecuteAgent Class

```php
class PlanExecuteAgent implements AgentInterface
```

#### Constructor

```php
public function __construct(
    ClaudePhp $client,
    array $options = []
)
```

#### Methods

##### run(string $task): AgentResult

Execute the plan-execute workflow for a task.

**Parameters:**
- `$task` - The task to complete

**Returns:**
- `AgentResult` object containing:
  - `answer` - Final synthesized result
  - `iterations` - Number of API calls made
  - `metadata` - Detailed execution information
  - `success` - Whether the task completed successfully

**Example:**

```php
$result = $agent->run('Analyze Q4 sales and create executive summary');

if ($result->isSuccess()) {
    echo $result->getAnswer();
    print_r($result->getMetadata());
}
```

##### addTool(ToolInterface $tool): self

Add a tool for use during execution.

**Parameters:**
- `$tool` - Tool instance

**Returns:**
- `$this` for method chaining

**Example:**

```php
$agent->addTool($calculatorTool)
      ->addTool($dateTool)
      ->addTool($searchTool);
```

##### getName(): string

Get the agent's name.

**Returns:**
- The agent's configured name

### AgentResult Class

Result object returned by the agent.

```php
// Success check
$result->isSuccess(): bool

// Get the final answer
$result->getAnswer(): string

// Get error message (if failed)
$result->getError(): string

// Get metadata
$result->getMetadata(): array

// Get iteration count
$result->getIterations(): int

// Get token usage
$result->getTokenUsage(): array
```

### Metadata Structure

```php
[
    'token_usage' => [
        'input' => 1234,
        'output' => 567,
        'total' => 1801,
    ],
    'plan_steps' => 5,
    'step_results' => [
        [
            'step' => 1,
            'description' => 'Analyze the requirements',
            'result' => 'Requirements analyzed: ...',
        ],
        // ... more steps
    ],
]
```

## Advanced Usage

### Combining with Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('plan-execute');
$logger->pushHandler(new StreamHandler('logs/planning.log', Logger::INFO));

$agent = new PlanExecuteAgent($client, [
    'logger' => $logger,
    'name' => 'production-planner',
]);

// All planning and execution steps will be logged
$result = $agent->run($task);
```

### Multi-Tool Coordination

```php
// Create a sophisticated workflow
$dataFetchTool = Tool::create('fetch_data')
    ->description('Fetch data from database')
    ->stringParam('query', 'SQL query')
    ->handler(function ($input) { /* ... */ });

$analysisTools = Tool::create('analyze')
    ->description('Statistical analysis')
    ->arrayParam('data', 'Data to analyze')
    ->handler(function ($input) { /* ... */ });

$visualizationTool = Tool::create('create_chart')
    ->description('Create data visualization')
    ->handler(function ($input) { /* ... */ });

$agent = new PlanExecuteAgent($client, [
    'tools' => [$dataFetchTool, $analysisTool, $visualizationTool],
]);

$result = $agent->run(
    'Analyze monthly sales trends: fetch last 12 months data, ' .
    'perform trend analysis, create visualization, and summarize insights.'
);
```

### Performance Optimization

```php
// For faster execution on simple tasks
$quickAgent = new PlanExecuteAgent($client, [
    'max_tokens' => 1024,        // Shorter responses
    'allow_replan' => false,     // Skip replanning
]);

// For comprehensive complex tasks
$thoroughAgent = new PlanExecuteAgent($client, [
    'max_tokens' => 4096,        // Longer responses
    'allow_replan' => true,      // Enable adaptation
    'model' => 'claude-sonnet-4-5', // Most capable model
]);
```

## Troubleshooting

### Common Issues

**Problem:** Agent creates too many steps

**Solution:** Be more specific in your task description:

```php
// Instead of:
$agent->run('Create a blog post');

// Try:
$agent->run('Create a 500-word blog post with: intro, 3 main points, conclusion');
```

**Problem:** Steps don't have enough context

**Solution:** This is automatic - each step receives results from previous steps. If needed, add more detail to the task:

```php
$agent->run(
    'Analyze user feedback and create report. ' .
    'Include sentiment analysis, key themes, and actionable recommendations.'
);
```

**Problem:** Token usage is high

**Solution:** 
- Reduce `max_tokens`
- Disable replanning if not needed
- Simplify the task
- Use more focused tools

```php
$agent = new PlanExecuteAgent($client, [
    'max_tokens' => 1024,
    'allow_replan' => false,
]);
```

**Problem:** Execution is slow

**Solution:** Plan-execute involves multiple API calls. For speed:
- Use simpler tasks
- Consider using a standard Agent for single-step tasks
- Reduce the number of required steps

## Examples

See the `examples/plan_execute_example.php` file for comprehensive working examples including:
- Simple planning without tools
- Multi-tool coordination
- Complex multi-step planning
- Adaptive replanning scenarios

Run the example:

```bash
export ANTHROPIC_API_KEY='your-api-key'
php examples/plan_execute_example.php
```

## Further Reading

- [Tutorial: Plan-Execute Agent](tutorials/PlanExecuteAgent_Tutorial.md)
- [Agent Selection Guide](agent-selection-guide.md)
- [Research: ReAct Pattern](https://arxiv.org/abs/2210.03629)

## License

This component is part of the claude-php-agent package and is licensed under the MIT License.

