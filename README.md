# Claude PHP Agent Framework

[![Tests](https://github.com/claude-php/claude-php-agent/actions/workflows/tests.yml/badge.svg)](https://github.com/claude-php/claude-php-agent/actions/workflows/tests.yml)
[![Code Quality](https://github.com/claude-php/claude-php-agent/actions/workflows/code-quality.yml/badge.svg)](https://github.com/claude-php/claude-php-agent/actions/workflows/code-quality.yml)
[![Security](https://github.com/claude-php/claude-php-agent/actions/workflows/security.yml/badge.svg)](https://github.com/claude-php/claude-php-agent/actions/workflows/security.yml)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1%7C%5E8.2%7C%5E8.3-blue)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Latest Stable Version](https://img.shields.io/packagist/v/claude-php/agent.svg)](https://packagist.org/packages/claude-php/agent)
[![Total Downloads](https://img.shields.io/packagist/dt/claude-php/agent.svg)](https://packagist.org/packages/claude-php/agent)

A powerful PHP framework for building AI agents with Claude, featuring ReAct loops, tool orchestration, hierarchical agents, and advanced agentic patterns.

## Features

- 🔄 **Loop Strategies** - ReactLoop, PlanExecuteLoop, ReflectionLoop, and StreamingLoop
- 🛠️ **Tool System** - Easy tool definition, registration, and execution
- 🧠 **Memory Management** - Persistent state across agent iterations
- 🏗️ **Agent Patterns** - ReAct, Plan-Execute, Reflection, Hierarchical, and more
- 🤖 **Adaptive Agent Service** - Intelligent agent selection, validation, and auto-adaptation
- 📊 **Output Parsers** - JSON, XML, Markdown, CSV, Lists, and Regex with auto-detection
- 🔗 **Chain Composition** - Sequential, parallel, and conditional chain execution
- ⚡ **Production Ready** - Retry logic, error handling, logging, and monitoring
- 🚀 **Async/Concurrent** - AMPHP-powered parallel execution for batch operations
- 🌐 **MCP Server** - Model Context Protocol integration for Claude Desktop and IDEs
- 🎯 **Extensible** - Build custom agents and patterns with ease
- 🆕 **Component Validation** - Runtime validation by instantiation (v0.8.0)
- 🏢 **Services System** - Enterprise service management with dependency injection (v0.7.0)
- 🧪 **Code Generation** - AI-powered code generation with validation pipelines (v0.8.0)

## Installation

```bash
composer require claude-php/agent
```

## Quick Start

```php
<?php

use ClaudeAgents\Agent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a simple calculator tool
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->parameter('expression', 'string', 'Math expression to evaluate')
    ->required('expression')
    ->handler(function (array $input): string {
        return (string) eval("return {$input['expression']};");
    });

// Create an agent with the tool
$agent = Agent::create($client)
    ->withTool($calculator)
    ->withSystemPrompt('You are a helpful assistant that can perform calculations.');

// Run the agent
$result = $agent->run('What is 25 * 17 + 100?');

echo $result->getAnswer();
```

### Progress Updates (Streaming UI / Live Status)

While an agent is running, you can receive continuous progress events (iteration output, tool results, and streaming deltas when using `StreamingLoop`) via `onUpdate()`:

```php
use ClaudeAgents\Progress\AgentUpdate;

$agent = Agent::create($client)
    ->onUpdate(function (AgentUpdate $update): void {
        // Send to WebSocket/SSE, update CLI spinner, etc.
        // $update->getType() and $update->getData() are structured and stable.
    });
```

## MCP Server Integration

The framework includes a full **Model Context Protocol (MCP)** server that exposes agent capabilities to MCP clients like **Claude Desktop**, IDEs, and other AI tools.

### Quick Start

```bash
# 1. Set your API key
export ANTHROPIC_API_KEY=your_api_key_here

# 2. Start the MCP server
php bin/mcp-server

# 3. Add to Claude Desktop config
{
  "mcpServers": {
    "claude-php-agent": {
      "command": "php",
      "args": ["/path/to/claude-php-agent/bin/mcp-server"]
    }
  }
}
```

### Features

- **15 MCP Tools** - Agent discovery, execution, visualization, and configuration
- **Dual Transport** - STDIO for Claude Desktop, SSE for web clients
- **Agent Discovery** - Search and explore 16+ agent types
- **Workflow Visualization** - ASCII art diagrams and JSON graphs
- **Real-time Execution** - Run agents directly through MCP
- **Session Management** - Isolated per-client sessions with memory

### Available Tools

**Agent Discovery:** `search_agents`, `list_agent_types`, `get_agent_details`, `count_agents`  
**Execution:** `run_agent`, `get_execution_status`  
**Tool Management:** `list_tools`, `search_tools`, `get_tool_details`  
**Visualization:** `visualize_workflow`, `get_agent_graph`, `export_agent_config`  
**Configuration:** `update_agent_config`, `create_agent_instance`, `validate_agent_config`

📚 **[Full MCP Documentation](docs/mcp-server-integration.md)**

## Core Concepts

### Loop Strategies

The framework provides multiple loop strategies for different types of tasks:

#### ReactLoop (Default)
The **Reason-Act-Observe** pattern for general-purpose tasks:

```php
use ClaudeAgents\Loops\ReactLoop;

$agent = Agent::create()
    ->withLoopStrategy(new ReactLoop())
    ->withTools([$searchTool, $calculatorTool])
    ->maxIterations(10);
```

#### PlanExecuteLoop
Plan first, then execute systematically for complex multi-step tasks:

```php
use ClaudeAgents\Loops\PlanExecuteLoop;

$loop = new PlanExecuteLoop(allowReplan: true);
$loop->onPlanCreated(function ($steps) {
    echo "Plan: " . count($steps) . " steps\n";
});

$agent = Agent::create()
    ->withLoopStrategy($loop)
    ->withTools($tools);
```

#### ReflectionLoop
Generate, reflect, and refine for high-quality outputs:

```php
use ClaudeAgents\Loops\ReflectionLoop;

$loop = new ReflectionLoop(
    maxRefinements: 3,
    qualityThreshold: 8
);

$agent = Agent::create()
    ->withLoopStrategy($loop);
```

See the [Loop Strategies Guide](docs/loop-strategies.md) for detailed documentation.

### Tools

Tools give Claude the ability to interact with the world:

```php
use ClaudeAgents\Tools\Tool;

// Fluent API for tool creation
$weatherTool = Tool::create('get_weather')
    ->description('Get current weather for a location')
    ->parameter('city', 'string', 'City name')
    ->parameter('units', 'string', 'Temperature units (celsius/fahrenheit)', false)
    ->required('city')
    ->handler(function (array $input): string {
        // Your weather API call here
        return json_encode(['temp' => 72, 'conditions' => 'sunny']);
    });
```

### Agent Patterns

#### Basic ReAct Agent

```php
use ClaudeAgents\Agents\ReactAgent;

$agent = new ReactAgent($client, [
    'tools' => [$tool1, $tool2],
    'max_iterations' => 10,
    'system' => 'You are a helpful assistant.',
]);

$result = $agent->run('Complete this task...');
```

#### Hierarchical Agent (Master-Worker)

```php
use ClaudeAgents\Agents\HierarchicalAgent;
use ClaudeAgents\Agents\WorkerAgent;

$master = new HierarchicalAgent($client);

$master->registerWorker('researcher', new WorkerAgent($client, [
    'specialty' => 'research and information gathering',
]));

$master->registerWorker('writer', new WorkerAgent($client, [
    'specialty' => 'writing and content creation',
]));

$result = $master->run('Research PHP 8 features and write a summary');
```

#### Reflection Agent

```php
use ClaudeAgents\Agents\ReflectionAgent;

$agent = new ReflectionAgent($client, [
    'max_refinements' => 3,
    'quality_threshold' => 8,
]);

$result = $agent->run('Write a function to validate email addresses');
// Agent will generate, reflect, and refine until quality threshold is met
```

### Memory & State

```php
use ClaudeAgents\Memory\Memory;
use ClaudeAgents\Memory\FileMemory;

// In-memory state
$memory = new Memory();
$memory->set('user_preference', 'dark_mode');

// Persistent file-based memory
$memory = new FileMemory('/path/to/state.json');

$agent = Agent::create()
    ->withMemory($memory)
    ->run('Remember my preferences...');
```

### Production Features

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Tools\ToolResult;
use Psr\Log\LoggerInterface;

$agent = Agent::create($client)
    ->withLogger($psrLogger)
    ->withRetry(maxAttempts: 3, delayMs: 1000) // ms
    ->onError(function (Throwable $e, int $attempt) {
        // Handle errors
    })
    ->onToolExecution(function (string $tool, array $input, ToolResult $result) {
        // Monitor tool usage
    })
    ->onUpdate(function (\ClaudeAgents\Progress\AgentUpdate $update) {
        // Unified progress updates (iterations, tools, streaming deltas, start/end)
    });
```

#### MAKER Agent (Massively Decomposed Agentic Processes)

**⚡ NEW: Solve million-step tasks with near-zero error rates!**

```php
use ClaudeAgents\Agents\MakerAgent;

// Based on: "Solving a Million-Step LLM Task with Zero Errors"
// https://arxiv.org/html/2511.09030v1

$maker = new MakerAgent($client, [
    'voting_k' => 3,                    // First-to-ahead-by-3 voting
    'enable_red_flagging' => true,      // Detect unreliable responses
    'max_decomposition_depth' => 10,    // Extreme decomposition
]);

// Can reliably handle tasks requiring millions of steps
$result = $maker->run('Solve this complex multi-step problem...');

// Track detailed execution statistics
$stats = $result->getMetadata()['execution_stats'];
echo "Steps: {$stats['total_steps']}\n";
echo "Votes: {$stats['votes_cast']}\n";
echo "Error Rate: " . $result->getMetadata()['error_rate'] . "\n";
```

**Key Features:**
- ✓ Extreme task decomposition into atomic subtasks
- ✓ Multi-agent voting for error correction at each step
- ✓ Red-flagging to detect and retry unreliable responses
- ✓ Scales to organization-level tasks (millions of steps)
- ✓ Sub-linear cost scaling with proper decomposition

**Paper Results:** Successfully solved 20-disk Towers of Hanoi (1,048,575 moves) with ZERO errors!

See [MakerAgent Documentation](docs/MakerAgent.md) for detailed documentation.

#### Adaptive Agent Service

**🎯 NEW: Intelligent agent selection with automatic validation and adaptation!**

```php
use ClaudeAgents\Agents\AdaptiveAgentService;

// Create service that automatically selects the best agent
$service = new AdaptiveAgentService($client, [
    'max_attempts' => 3,              // Try up to 3 times
    'quality_threshold' => 7.0,       // Require 7/10 quality
    'enable_reframing' => true,       // Reframe on failure
]);

// Register various agents with their profiles
$service->registerAgent('react', $reactAgent, [
    'type' => 'react',
    'complexity_level' => 'medium',
    'quality' => 'standard',
]);

$service->registerAgent('reflection', $reflectionAgent, [
    'type' => 'reflection',
    'complexity_level' => 'medium',
    'quality' => 'high',
]);

// Service automatically:
// 1. Analyzes the task
// 2. Selects the best agent
// 3. Validates the result
// 4. Retries with different agents if needed
$result = $service->run('Your task here');

echo "Agent used: {$result->getMetadata()['final_agent']}\n";
echo "Quality: {$result->getMetadata()['final_quality']}/10\n";
```

**Key Features:**
- ✓ Intelligent agent selection based on task analysis
- ✓ Automatic quality validation and scoring
- ✓ Adaptive retry with different agents on failure
- ✓ Request reframing for better results
- ✓ Performance tracking and learning

See [docs/adaptive-agent-service.md](./docs/adaptive-agent-service.md) for detailed documentation.

## Agent Patterns Reference

| Pattern | Use Case | Scalability | Example |
|---------|----------|-------------|---------|
| **ReAct** | General-purpose autonomous tasks | ~100 steps | Research, calculations, data processing |
| **Plan-Execute** | Complex multi-step tasks | ~1K steps | Project planning, workflows |
| **Reflection** | Quality-critical outputs | ~500 steps | Code generation, writing |
| **Hierarchical** | Multi-domain tasks | ~5K steps | Business analysis, reports |
| **Chain-of-Thought** | Complex reasoning | ~500 steps | Math problems, logic puzzles |
| **Tree-of-Thoughts** | Exploration tasks | ~1K steps | Creative writing, optimization |
| **MAKER/MDAP** | Million-step tasks, zero errors | Millions+ | Long sequences, organization-level tasks |
| **Monitoring** | System monitoring, anomaly detection | Real-time | Server metrics, performance tracking |
| **Scheduler** | Task scheduling, cron jobs | Continuous | Automated workflows, batch processing |
| **Alert** | Intelligent alerting, notifications | Real-time | System alerts, incident management |
| **Reflex** | Rule-based responses | Instant | FAQs, simple automation |
| **Model-Based** | State-aware decision making | ~500 steps | Planning, simulation |
| **Utility-Based** | Optimization, trade-offs | ~100 steps | Resource allocation, decision support |
| **Learning** | Adaptive behavior, feedback loops | Continuous | Personalization, strategy evolution |
| **Collaboration** | Multi-agent coordination (AutoGen) | ~5K steps | Team workflows, complex research |
| **TaskPrioritization** | Goal-driven task management (BabyAGI) | ~1K steps | Project breakdown, execution |
| **Coordinator** | Agent orchestration, load balancing | ~10K steps | Distributed systems, agent networks |
| **Dialog** | Conversational AI, context tracking | Continuous | Customer service, assistants |
| **IntentClassifier** | Intent recognition, entity extraction | Instant | Command routing, NLU |
| **EnvironmentSimulator** | What-if analysis, prediction | ~100 steps | Testing, planning |
| **SolutionDiscriminator** | Solution evaluation, voting | ~50 steps | Quality assurance, selection |
| **MemoryManager** | Knowledge management, retrieval | Continuous | Shared memory, context |
| **AdaptiveAgentService** | Meta-agent selection & validation | Varies | Auto-optimization, quality assurance |

## Configuration

```php
use ClaudeAgents\Config\AgentConfig;

$config = new AgentConfig([
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 4096,
    'max_iterations' => 10,
    'temperature' => 0.7,
    'timeout' => 30.0,
    'retry' => [
        'max_attempts' => 3,
        'delay_ms' => 1000,
        'multiplier' => 2,
    ],
]);

$agent = Agent::create()->withConfig($config);
```

## Async & Concurrent Execution

The framework leverages **AMPHP** for true asynchronous and concurrent execution:

### Batch Processing

Process multiple agent tasks concurrently:

```php
use ClaudeAgents\Async\BatchProcessor;

$processor = BatchProcessor::create($agent);

$processor->addMany([
    'task1' => 'Summarize this document...',
    'task2' => 'Analyze this data...',
    'task3' => 'Generate a report...',
]);

// Execute with concurrency of 5
$results = $processor->run(concurrency: 5);

// Get statistics
$stats = $processor->getStats();
echo "Success rate: " . ($stats['success_rate'] * 100) . "%\n";
```

### Parallel Tool Execution

Execute multiple tool calls simultaneously:

```php
use ClaudeAgents\Async\ParallelToolExecutor;

$executor = new ParallelToolExecutor($tools);

$calls = [
    ['tool' => 'get_weather', 'input' => ['city' => 'London']],
    ['tool' => 'get_time', 'input' => ['timezone' => 'UTC']],
    ['tool' => 'calculate', 'input' => ['expression' => '42 * 8']],
];

// All execute in parallel!
$results = $executor->execute($calls);
```

### Promise-Based Workflows

Use promises for async operations:

```php
use ClaudeAgents\Async\Promise;

$promises = $processor->runAsync();

// Do other work...

// Wait for all to complete
$results = Promise::all($promises);
```

See the [examples directory](./examples) for complete async/concurrent examples.

### Output Parsers

Transform unstructured LLM responses into structured data:

```php
use ClaudeAgents\Parsers\ParserFactory;
use ClaudeAgents\Chains\LLMChain;

$factory = ParserFactory::create();

// JSON with schema validation
$jsonParser = $factory->json([
    'type' => 'object',
    'required' => ['sentiment', 'confidence']
]);

// Auto-detect and parse
$result = $factory->autoParse($llmResponse);

// Use with chains
$chain = LLMChain::create($client)
    ->withPromptTemplate($template)
    ->withOutputParser(fn($text) => $jsonParser->parse($text));
```

**Available Parsers:**
- **JsonParser** - Extract and validate JSON
- **ListParser** - Parse bullet/numbered lists
- **RegexParser** - Pattern-based extraction
- **XmlParser** - Parse XML/HTML
- **MarkdownParser** - Extract structured markdown
- **CsvParser** - Parse CSV/TSV data
- **ParserFactory** - Auto-detection and convenience methods

See [Parsers Documentation](docs/Parsers.md) for complete guide.

## Examples

See the [examples](./examples) directory for 110+ complete working examples including:

**Core Examples (70+ files):**
- Basic ReAct agents and multi-tool usage
- Hierarchical agent systems (master-worker pattern)
- Reflection agents for self-improvement
- Production-ready agent setups with error handling
- Adaptive agent service with intelligent selection
- Async/concurrent execution with AMPHP
- MAKER framework for million-step reliable tasks
- Output parsers for structured data extraction
- Chain composition patterns

**🆕 Tutorial Examples (42 files in `examples/tutorials/`):**
- Component validation patterns (7 examples)
- Services system usage (7 examples)
- MCP server integration (7 examples)
- Code generation workflows (7 examples)
- Production deployment patterns (7 examples)
- Testing strategies (7 examples)

> 💡 All tutorial examples are fully runnable: `php examples/tutorials/component-validation/01-basic-validation.php`

## Documentation

### 🎓 Getting Started

New to AI agents? Start with our comprehensive tutorial series:

- **[📚 Getting Started Tutorials](docs/tutorials/getting-started/)** - Complete beginner-friendly series
  - [Tutorial 0: Introduction to Agentic AI](docs/tutorials/getting-started/00-Introduction.md)
  - [Tutorial 1: Your First Agent](docs/tutorials/getting-started/01-First-Agent.md)
  - [Tutorial 2: ReAct Loop Basics](docs/tutorials/getting-started/02-ReAct-Basics.md)
  - [Tutorial 3: Multi-Tool Agent](docs/tutorials/getting-started/03-Multi-Tool.md)
  - [Tutorial 4: Production-Ready Patterns](docs/tutorials/getting-started/04-Production-Patterns.md)
  - [Tutorial 5: Advanced Patterns](docs/tutorials/getting-started/05-Advanced-Patterns.md)

### 🆕 New Features Tutorials (v0.7.0 - v0.8.0)

Master the latest framework capabilities:

- **[🔍 Component Validation](docs/tutorials/ComponentValidation_Tutorial.md)** - Runtime validation by instantiation (45min)
- **[🏢 Services System](docs/tutorials/ServicesSystem_Tutorial.md)** - Enterprise service management (50min)
- **[🌐 MCP Server Integration](docs/tutorials/MCPServer_Tutorial.md)** - Connect to Claude Desktop (55min)
- **[🧪 Code Generation](docs/tutorials/CodeGeneration_Tutorial.md)** - AI-powered code generation (50min)
- **[🚀 Production Patterns](docs/tutorials/ProductionPatterns_Tutorial.md)** - Production deployment (60min)
- **[✅ Testing Strategies](docs/tutorials/TestingStrategies_Tutorial.md)** - Comprehensive testing (55min)

> 💡 **42 runnable examples** included in `examples/tutorials/` - Each tutorial comes with 7 working code samples!

### 📖 Complete Documentation

- **[Quick Start Guide](QUICKSTART.md)** - Get started in 5 minutes
- **[Documentation Index](docs/README.md)** - Complete guide to all features
- **[All Tutorials](docs/tutorials/README.md)** - 17+ comprehensive tutorials with examples
- **[Loop Strategies](docs/loop-strategies.md)** - Understanding agent loops
- **[Agent Selection Guide](docs/agent-selection-guide.md)** - Choose the right pattern
- **[Best Practices Guide](docs/BestPractices.md)** - Production-ready patterns
- **[MCP Server Integration](docs/mcp-server-integration.md)** - Claude Desktop connectivity
- **[Component Validation](docs/component-validation-service.md)** - Runtime validation guide
- **[Services System](docs/services/README.md)** - Enterprise service management
- **[Examples](examples/)** - 70+ working code examples + 42 tutorial examples

## Requirements

- PHP 8.1, 8.2, or 8.3
- Composer
- [claude-php/claude-php-sdk](https://github.com/claude-php/Claude-PHP-SDK)

## Installation

```bash
composer require claude-php/agent
```

For detailed setup instructions, see [QUICKSTART.md](QUICKSTART.md).

## Contributing

We welcome contributions! Please see:

- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Contribution guidelines
- **[SECURITY.md](SECURITY.md)** - Security policy
- **[CHANGELOG.md](CHANGELOG.md)** - Version history

## Support

- **Issues**: [GitHub Issues](https://github.com/claude-php/claude-php-agent/issues)
- **Discussions**: [GitHub Discussions](https://github.com/claude-php/claude-php-agent/discussions)
- **Documentation**: [docs/](docs/)

## License

MIT License - see [LICENSE](LICENSE) for details.

## What's New

### v0.8.0 (Latest)
- ✨ **Component Validation Service** - Runtime validation by instantiation
- ✨ **Code Generation Agent** - AI-powered code generation with validation
- 📚 New tutorials: Component Validation, Code Generation, Production Patterns, Testing Strategies
- 📝 42 new tutorial examples in `examples/tutorials/`

### v0.7.0
- ✨ **Services System** - Enterprise service management with dependency injection
- ✨ **MCP Server** - Model Context Protocol integration for Claude Desktop
- 📚 New tutorials: Services System, MCP Server Integration
- 🔧 Enhanced observability and monitoring

See [CHANGELOG.md](CHANGELOG.md) for complete version history.

## Acknowledgments

Built with ❤️ using [Claude PHP SDK](https://github.com/claude-php/Claude-PHP-SDK) and inspired by the latest research in AI agents and LLM orchestration.

