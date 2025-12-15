# Quick Start Guide

Get up and running with Claude PHP Agent Framework in 5 minutes!

## Installation

```bash
composer require claude-php/agent
```

## Prerequisites

- PHP 8.1 or higher
- Composer
- Anthropic API key ([get one here](https://console.anthropic.com/))

## Setup

### 1. Get Your API Key

Sign up at [Anthropic](https://console.anthropic.com/) and get your API key.

### 2. Configure Environment

Create a `.env` file (or set environment variables):

```bash
ANTHROPIC_API_KEY=your_api_key_here
```

Or copy the example:

```bash
cp .env.example .env
# Edit .env with your API key
```

## Your First Agent (30 seconds)

Create `agent.php`:

```php
<?php

require 'vendor/autoload.php';

use ClaudePhp\ClaudePhp;
use ClaudeAgents\Agent;

// Initialize Claude client
$client = new ClaudePhp([
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

// Create an agent
$agent = Agent::create($client)
    ->withSystemPrompt('You are a helpful assistant.');

// Run it!
$result = $agent->run('What is the capital of France?');

echo $result->getAnswer();
// Output: The capital of France is Paris.
```

Run it:

```bash
php agent.php
```

## Add a Tool (2 minutes)

Let's give your agent the ability to perform calculations:

```php
<?php

require 'vendor/autoload.php';

use ClaudePhp\ClaudePhp;
use ClaudeAgents\Agent;
use ClaudeAgents\Tools\Tool;

$client = new ClaudePhp(['api_key' => getenv('ANTHROPIC_API_KEY')]);

// Create a calculator tool
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->parameter('expression', 'string', 'Math expression to evaluate')
    ->required('expression')
    ->handler(function (array $input): string {
        // Safety: In production, use a proper math parser!
        return (string) eval("return {$input['expression']};");
    });

// Create agent with the tool
$agent = Agent::create($client)
    ->withTool($calculator)
    ->withSystemPrompt('You are a helpful assistant that can perform calculations.');

// The agent will automatically use the calculator when needed
$result = $agent->run('What is 127 * 89 + 456?');

echo $result->getAnswer();
// Output: The result is 11,759 (127 × 89 = 11,303 + 456 = 11,759).
```

## Choose an Agent Pattern (3 minutes)

Different tasks need different approaches:

### Simple Tasks: ReAct Agent

```php
use ClaudeAgents\Agents\ReactAgent;

$agent = new ReactAgent($client, [
    'tools' => [$searchTool, $calculatorTool],
    'max_iterations' => 10,
]);

$result = $agent->run('Search for PHP 8.3 features and calculate...');
```

### Complex Tasks: Plan-Execute Agent

```php
use ClaudeAgents\Agents\PlanExecuteAgent;

$agent = new PlanExecuteAgent($client, [
    'tools' => [$tools],
    'allow_replan' => true,
]);

// Agent will create a plan first, then execute it step by step
$result = $agent->run('Research competitors and create a comparison table');
```

### Quality-Critical: Reflection Agent

```php
use ClaudeAgents\Agents\ReflectionAgent;

$agent = new ReflectionAgent($client, [
    'max_refinements' => 3,
    'quality_threshold' => 8,
]);

// Agent will generate, reflect, and refine until quality threshold is met
$result = $agent->run('Write a professional email to a client');
```

### Large Teams: Hierarchical Agent

```php
use ClaudeAgents\Agents\HierarchicalAgent;
use ClaudeAgents\Agents\WorkerAgent;

$master = new HierarchicalAgent($client);

// Register specialist workers
$master->registerWorker('researcher', new WorkerAgent($client, [
    'specialty' => 'research and data gathering',
]));

$master->registerWorker('writer', new WorkerAgent($client, [
    'specialty' => 'writing and content creation',
]));

// Master delegates to workers automatically
$result = $master->run('Research AI trends and write a blog post');
```

## Production Features

### Error Handling & Retries

```php
$agent = Agent::create($client)
    ->maxRetries(3)
    ->retryDelay(1000) // ms
    ->timeout(30.0)
    ->onError(function (Throwable $e, int $attempt) {
        error_log("Attempt {$attempt} failed: {$e->getMessage()}");
    });
```

### Logging

```php
use Psr\Log\LoggerInterface;

$agent = Agent::create($client)
    ->withLogger($psrLogger)
    ->onToolExecution(function (string $tool, array $input, string $result) {
        // Monitor tool usage
        echo "Tool '{$tool}' executed\n";
    });
```

### Memory/State

```php
use ClaudeAgents\Memory\FileMemory;

$memory = new FileMemory('/path/to/state.json');

$agent = Agent::create($client)
    ->withMemory($memory);

$agent->run('Remember that I prefer dark mode');
// Memory persists across runs
```

## Next Steps

### 📚 Learn More

- **[Full Documentation](docs/README.md)** - Complete guides and references
- **[Examples](examples/)** - 70+ working examples
- **[Agent Selection Guide](docs/agent-selection-guide.md)** - Choose the right pattern
- **[Loop Strategies](docs/loop-strategies.md)** - Understanding agent loops

### 🔧 Advanced Topics

- [RAG (Retrieval-Augmented Generation)](docs/RAGAgent.md)
- [Multi-Agent Systems](docs/MultiAgent.md)
- [Chain Composition](docs/Chains.md)
- [Async/Parallel Execution](examples/amphp_async_example.php)
- [Output Parsers](docs/Parsers.md)

### 💡 Common Use Cases

**Chatbot**: [Dialog Agent](docs/DialogAgent.md)  
**Search & Answer**: [RAG Agent](docs/RAGAgent.md)  
**Data Processing**: [ReAct Agent](docs/ReactAgent.md)  
**Content Generation**: [Reflection Agent](docs/ReflectionAgent.md)  
**System Monitoring**: [Monitoring Agent](docs/MonitoringAgent.md)  
**Task Automation**: [Autonomous Agent](docs/AutonomousAgent.md)

## Troubleshooting

### "Class not found" Error

Make sure you've installed dependencies:

```bash
composer install
```

### API Key Issues

Check that your API key is properly set:

```php
echo getenv('ANTHROPIC_API_KEY'); // Should print your key
```

### Rate Limits

If you hit rate limits, add delays:

```php
$agent->retryDelay(2000); // 2 seconds between retries
```

## Getting Help

- **Documentation**: [docs/README.md](docs/README.md)
- **Examples**: [examples/](examples/)
- **Issues**: [GitHub Issues](https://github.com/claude-php/agent/issues)
- **Discussions**: [GitHub Discussions](https://github.com/claude-php/agent/discussions)

## What's Next?

Now that you have a basic agent running:

1. ✅ Try adding more tools
2. ✅ Experiment with different agent patterns
3. ✅ Add error handling and logging
4. ✅ Explore the examples directory
5. ✅ Build something awesome!

Happy coding! 🚀
