# HierarchicalAgent Documentation

## Overview

The `HierarchicalAgent` implements the master-worker pattern for coordinating multiple specialized AI agents to solve complex, multi-domain tasks. The master agent decomposes complex tasks into subtasks, delegates them to specialized worker agents, and synthesizes the results into a comprehensive answer.

## Features

- 🎯 **Task Decomposition**: Automatically breaks down complex tasks into manageable subtasks
- 👥 **Worker Coordination**: Manages multiple specialized worker agents
- 🔄 **Result Synthesis**: Combines outputs from multiple workers into coherent responses
- 📊 **Token Tracking**: Aggregates token usage across all agent interactions
- 🔍 **Flexible Worker Assignment**: Intelligently assigns tasks to appropriate specialists
- 📝 **Comprehensive Logging**: Tracks execution flow and worker activities

## Installation

The HierarchicalAgent is included in the `claude-php-agent` package:

```bash
composer require your-org/claude-php-agent
```

## Architecture

```
┌─────────────────────────────────────┐
│       Master Agent                  │
│  (HierarchicalAgent)                │
│                                     │
│  1. Decomposes task                 │
│  2. Delegates to workers            │
│  3. Synthesizes results             │
└──────────┬──────────────────────────┘
           │
           │ Coordinates
           ▼
┌──────────────────────────────────────┐
│         Worker Agents                │
├──────────────────────────────────────┤
│  • Math Agent                        │
│  • Writing Agent                     │
│  • Research Agent                    │
│  • Code Agent                        │
│  • ... (custom workers)              │
└──────────────────────────────────────┘
```

## Basic Usage

```php
use ClaudeAgents\Agents\HierarchicalAgent;
use ClaudeAgents\Agents\WorkerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');

// Create specialized workers
$mathWorker = new WorkerAgent($client, [
    'name' => 'math_agent',
    'specialty' => 'mathematical calculations and analysis',
    'system' => 'You are a mathematics expert. Provide precise calculations.',
]);

$writingWorker = new WorkerAgent($client, [
    'name' => 'writing_agent',
    'specialty' => 'content creation and writing',
    'system' => 'You are a professional writer. Create clear, engaging content.',
]);

// Create master agent
$master = new HierarchicalAgent($client, [
    'name' => 'master_coordinator',
]);

// Register workers
$master->registerWorker('math_agent', $mathWorker);
$master->registerWorker('writing_agent', $writingWorker);

// Run complex task
$result = $master->run('Calculate the average of 45, 67, 89, and 123, then write a brief explanation of what an average represents.');

if ($result->isSuccess()) {
    echo $result->getAnswer();
    
    $metadata = $result->getMetadata();
    echo "Workers used: " . implode(', ', $metadata['workers_used']);
    echo "Total tokens: " . $metadata['token_usage']['total'];
}
```

## Configuration

### HierarchicalAgent Options

The HierarchicalAgent accepts the following configuration options:

```php
$master = new HierarchicalAgent($client, [
    'name' => 'my_master',              // Master agent name
    'model' => 'claude-sonnet-4-5',     // Model for decomposition/synthesis
    'max_tokens' => 2048,               // Max tokens per API call
    'logger' => $logger,                // PSR-3 logger instance
]);
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'master_agent'` | Unique identifier for the master agent |
| `model` | string | `'claude-sonnet-4-5'` | Claude model to use |
| `max_tokens` | int | `2048` | Maximum tokens per API response |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

### WorkerAgent Options

Each worker agent can be configured independently:

```php
$worker = new WorkerAgent($client, [
    'name' => 'specialist_agent',       // Worker identifier
    'specialty' => 'specific domain',   // Description of expertise
    'system' => 'Custom system prompt', // Worker's system prompt
    'model' => 'claude-sonnet-4-5',     // Model to use
    'max_tokens' => 2048,               // Max tokens per response
]);
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'worker'` | Worker identifier |
| `specialty` | string | `'general tasks'` | Description of worker's expertise |
| `system` | string | Auto-generated | System prompt for the worker |
| `model` | string | `'claude-sonnet-4-5'` | Claude model to use |
| `max_tokens` | int | `2048` | Maximum tokens per response |

## Worker Management

### Registering Workers

Register specialized workers with descriptive names:

```php
$master->registerWorker('data_analyst', $dataWorker);
$master->registerWorker('code_reviewer', $codeWorker);
$master->registerWorker('documentation_writer', $docsWorker);
```

### Retrieving Workers

Access registered workers:

```php
$worker = $master->getWorker('data_analyst');
if ($worker !== null) {
    echo $worker->getSpecialty();
}
```

### Listing Workers

Get all registered worker names:

```php
$workerNames = $master->getWorkerNames();
foreach ($workerNames as $name) {
    $worker = $master->getWorker($name);
    echo "{$name}: {$worker->getSpecialty()}\n";
}
```

## How It Works

### 1. Task Decomposition

The master agent analyzes the complex task and breaks it into subtasks, assigning each to an appropriate worker:

```
Input: "Analyze sales data and create a report"

Decomposition:
- Agent: data_analyst
  Subtask: Analyze the sales data and calculate key metrics
  
- Agent: writing_agent
  Subtask: Create a professional report with the analysis results
```

### 2. Worker Execution

Each worker processes its assigned subtask independently:

```php
// Math worker calculates statistics
$mathResult = $mathWorker->run("Calculate average, median, and standard deviation");

// Writing worker creates narrative
$writingResult = $writingWorker->run("Write a professional summary of the findings");
```

### 3. Result Synthesis

The master combines all worker outputs into a cohesive final answer:

```
Worker Outputs:
- data_analyst: "Average: 125.5, Growth: 15%, Top Product: Widget A"
- writing_agent: "Q4 sales showed strong performance with 15% growth..."

Final Answer:
"Based on comprehensive analysis, Q4 sales performance demonstrates strong growth of 15%, with an average sale value of $125.5. Widget A emerged as the top-performing product..."
```

## Advanced Usage

### Custom Specialized Workers

Create workers for specific domains:

```php
// Code analysis worker
$codeWorker = new WorkerAgent($client, [
    'name' => 'code_analyst',
    'specialty' => 'code review, security analysis, and best practices',
    'system' => 'You are an expert code reviewer. Analyze code for quality, security, and performance issues.',
]);

// Database optimization worker
$dbWorker = new WorkerAgent($client, [
    'name' => 'db_optimizer',
    'specialty' => 'database query optimization and schema design',
    'system' => 'You are a database expert. Optimize queries and suggest schema improvements.',
]);

// API design worker
$apiWorker = new WorkerAgent($client, [
    'name' => 'api_designer',
    'specialty' => 'RESTful API design and documentation',
    'system' => 'You are an API design expert. Design clean, RESTful APIs with proper documentation.',
]);

$master->registerWorker('code_analyst', $codeWorker);
$master->registerWorker('db_optimizer', $dbWorker);
$master->registerWorker('api_designer', $apiWorker);

// Complex software architecture task
$result = $master->run(
    'Review this codebase for security issues, optimize the database queries, ' .
    'and design a new RESTful API endpoint for user management.'
);
```

### Fallback Behavior

If a requested worker isn't available, the system automatically falls back:

1. First, it tries to use any available worker
2. If no workers are registered, it notes that no worker is available
3. The synthesis phase still proceeds with available results

```php
// Task requests 'specialist_agent' which doesn't exist
// System will use first available worker as fallback
$result = $master->run('Task requiring specialist_agent');
```

## Result Metadata

The HierarchicalAgent provides comprehensive metadata about execution:

```php
$result = $master->run('Complex task');

$metadata = $result->getMetadata();

// Execution information
$iterations = $result->getIterations();        // Total steps: decompose + subtasks + synthesize
$subtasks = $metadata['subtasks'];             // Number of subtasks created
$workers = $metadata['workers_used'];          // Array of worker names used
$duration = $metadata['duration_seconds'];     // Total execution time

// Token usage (aggregated across all API calls)
$tokenUsage = $metadata['token_usage'];
echo "Input tokens: {$tokenUsage['input']}\n";
echo "Output tokens: {$tokenUsage['output']}\n";
echo "Total tokens: {$tokenUsage['total']}\n";
```

## Best Practices

### 1. Create Focused Specialists

Each worker should have a clear, specific domain:

```php
// Good: Focused specialty
$worker = new WorkerAgent($client, [
    'specialty' => 'Python code security analysis and vulnerability detection',
]);

// Less ideal: Too broad
$worker = new WorkerAgent($client, [
    'specialty' => 'general programming and software development',
]);
```

### 2. Use Descriptive System Prompts

Provide clear instructions for each worker:

```php
$worker = new WorkerAgent($client, [
    'system' => 'You are a financial analyst specializing in risk assessment. ' .
                'Analyze data for potential risks, calculate risk scores, and ' .
                'provide actionable mitigation strategies.',
]);
```

### 3. Register Multiple Complementary Workers

Build a team with diverse but complementary skills:

```php
$master->registerWorker('data_scientist', $dataWorker);      // Analyzes data
$master->registerWorker('visualizer', $vizWorker);           // Creates charts
$master->registerWorker('statistician', $statsWorker);       // Statistical tests
$master->registerWorker('presenter', $presentWorker);        // Presents findings
```

### 4. Monitor Token Usage

Track token consumption for cost management:

```php
$result = $master->run($task);

$usage = $result->getTokenUsage();
$cost = ($usage['input'] * 0.003 / 1000) + ($usage['output'] * 0.015 / 1000);
echo "Estimated cost: $" . number_format($cost, 4);
```

### 5. Use Logging for Debugging

Enable logging to track execution flow:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('hierarchical');
$logger->pushHandler(new StreamHandler('hierarchical.log', Logger::DEBUG));

$master = new HierarchicalAgent($client, [
    'logger' => $logger,
]);
```

## Use Cases

### Software Development Team

```php
// Code review system
$codeReviewer = new WorkerAgent($client, [
    'specialty' => 'code quality and security review',
]);

$testWriter = new WorkerAgent($client, [
    'specialty' => 'writing unit and integration tests',
]);

$docWriter = new WorkerAgent($client, [
    'specialty' => 'technical documentation',
]);

$master->registerWorker('reviewer', $codeReviewer);
$master->registerWorker('tester', $testWriter);
$master->registerWorker('documenter', $docWriter);

$result = $master->run('Review this pull request, suggest tests, and update documentation');
```

### Business Analysis

```php
// Market analysis team
$dataAnalyst = new WorkerAgent($client, [
    'specialty' => 'market data analysis and trend identification',
]);

$competitorAnalyst = new WorkerAgent($client, [
    'specialty' => 'competitive intelligence and positioning',
]);

$strategyWriter = new WorkerAgent($client, [
    'specialty' => 'business strategy and recommendations',
]);

$master->registerWorker('data', $dataAnalyst);
$master->registerWorker('competition', $competitorAnalyst);
$master->registerWorker('strategy', $strategyWriter);

$result = $master->run('Analyze Q4 market trends and recommend strategy for Q1');
```

### Content Creation

```php
// Editorial team
$researcher = new WorkerAgent($client, [
    'specialty' => 'research and fact-checking',
]);

$writer = new WorkerAgent($client, [
    'specialty' => 'creative writing and storytelling',
]);

$editor = new WorkerAgent($client, [
    'specialty' => 'editing, proofreading, and style',
]);

$master->registerWorker('research', $researcher);
$master->registerWorker('write', $writer);
$master->registerWorker('edit', $editor);

$result = $master->run('Create a comprehensive blog post about quantum computing for general audience');
```

## Error Handling

The HierarchicalAgent handles errors gracefully:

```php
$result = $master->run('Complex task');

if (!$result->isSuccess()) {
    echo "Error: {$result->getError()}\n";
    
    // Check if it was a decomposition failure
    if (strpos($result->getError(), 'decompose') !== false) {
        echo "Failed to break down the task\n";
    }
}
```

Common error scenarios:

- **Decomposition failure**: Task cannot be broken into subtasks
- **No workers available**: No workers registered or all workers fail
- **API errors**: Claude API failures during execution
- **Synthesis failure**: Cannot combine worker outputs (falls back to error message)

## Performance Considerations

### Execution Time

The total execution time includes:
- 1 API call for task decomposition
- N API calls for N subtasks (workers)
- 1 API call for result synthesis

Example timing:
```
Decomposition: ~2-3 seconds
Worker 1: ~3-5 seconds
Worker 2: ~3-5 seconds
Worker 3: ~3-5 seconds
Synthesis: ~2-4 seconds
Total: ~13-22 seconds for 3 workers
```

### Token Optimization

To minimize token usage:

1. Use focused, specific tasks
2. Keep worker system prompts concise
3. Monitor and adjust max_tokens settings
4. Consider using `claude-haiku-3-5` for simple subtasks

```php
// High-performance master with budget-friendly workers
$master = new HierarchicalAgent($client, [
    'model' => 'claude-sonnet-4-5',  // Smart decomposition
]);

$budgetWorker = new WorkerAgent($client, [
    'model' => 'claude-haiku-3-5',   // Fast, economical
    'max_tokens' => 1024,
]);
```

## Comparison with Other Patterns

| Pattern | Best For | Complexity | Cost |
|---------|----------|------------|------|
| **Hierarchical** | Multi-domain problems requiring specialists | High | High |
| **Chain of Thought** | Sequential reasoning tasks | Medium | Low |
| **Autonomous** | Open-ended exploration with tools | High | High |
| **Dialog** | Conversational interactions | Low | Low |

## API Reference

### HierarchicalAgent

```php
class HierarchicalAgent implements AgentInterface
{
    public function __construct(ClaudePhp $client, array $options = []);
    public function registerWorker(string $name, WorkerAgent $worker): self;
    public function getWorker(string $name): ?WorkerAgent;
    public function getWorkerNames(): array;
    public function run(string $task): AgentResult;
    public function getName(): string;
}
```

### WorkerAgent

```php
class WorkerAgent implements AgentInterface
{
    public function __construct(ClaudePhp $client, array $options = []);
    public function run(string $task): AgentResult;
    public function getName(): string;
    public function getSpecialty(): string;
}
```

## Examples

See the [examples/hierarchical_agent.php](../examples/hierarchical_agent.php) file for a complete working example.

## Further Reading

- [Tutorial: Building Hierarchical Agent Systems](tutorials/HierarchicalAgent_Tutorial.md)
- [Agent Selection Guide](agent-selection-guide.md)
- [Multi-Agent Patterns](../README.md#multi-agent-patterns)

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/your-org/claude-php-agent).

