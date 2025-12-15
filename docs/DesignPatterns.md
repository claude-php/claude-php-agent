# Design Patterns in Claude PHP Agent Framework

## Overview

The Claude PHP Agent Framework implements six production-ready design patterns that enhance code organization, maintainability, and extensibility. These patterns follow industry best practices and provide clean, type-safe APIs for building sophisticated AI agents.

## Table of Contents

- [Pattern Summary](#pattern-summary)
- [Factory Pattern](#1-factory-pattern)
- [Builder Pattern](#2-builder-pattern)
- [Observer Pattern](#3-observer-pattern-event-system)
- [Strategy Pattern](#4-strategy-pattern)
- [Template Method Pattern](#5-template-method-pattern)
- [Error Handler Pattern](#6-error-handler-pattern)
- [Pattern Combinations](#pattern-combinations)
- [When to Use Each Pattern](#when-to-use-each-pattern)
- [Best Practices](#best-practices)

---

## Pattern Summary

| Pattern      | Class                 | Purpose                           | Use When                               |
| ------------ | --------------------- | --------------------------------- | -------------------------------------- |
| **Factory**  | `AgentFactory`        | Centralized agent creation        | Creating multiple agents consistently  |
| **Builder**  | `AgentConfigBuilder`  | Fluent configuration API          | Complex configurations (>3 parameters) |
| **Observer** | `EventDispatcher`     | Lifecycle event monitoring        | Monitoring, logging, analytics         |
| **Strategy** | `ResponseParserChain` | Pluggable response parsing        | Multiple parsing strategies needed     |
| **Template** | `PromptBuilder`       | Fluent prompt construction        | Complex, multi-section prompts         |
| **Handler**  | `ErrorHandler`        | Unified error handling with retry | All external API calls                 |

---

## 1. Factory Pattern

### Overview

The **Factory Pattern** provides a centralized way to create agents with consistent configuration and automatic dependency injection.

### Problem It Solves

Without Factory:

```php
// Inconsistent creation
$agent1 = new ReactAgent($client);
$agent2 = new ReactAgent($client, ['name' => 'agent2']);
$agent3 = new ReactAgent($client, ['name' => 'agent3'], $logger);

// Each developer does it differently
// Logger sometimes missing
// No consistency in configuration
```

With Factory:

```php
$factory = new AgentFactory($client, $logger);

// Consistent creation, logger always injected
$agent1 = $factory->create('react');
$agent2 = $factory->create('react', ['name' => 'agent2']);
$agent3 = $factory->createReactAgent(['name' => 'agent3']);
```

### Implementation

```php
use ClaudeAgents\Factory\AgentFactory;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;

$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);
$logger = new Logger('agents');

$factory = new AgentFactory($client, $logger);
```

### Creating Agents

**Generic Creation:**

```php
$agent = $factory->create('react', [
    'name' => 'my_agent',
    'max_iterations' => 10,
]);
```

**Specific Methods:**

```php
// Core reasoning agents
$reactAgent = $factory->createReactAgent(['name' => 'react1']);
$cotAgent = $factory->createChainOfThoughtAgent(['mode' => 'few_shot']);
$totAgent = $factory->createTreeOfThoughtsAgent(['branches' => 3]);
$planAgent = $factory->createPlanExecuteAgent(['allow_replan' => true]);
$reflectAgent = $factory->createReflectionAgent(['reflection_depth' => 2]);

// Specialized agents
$ragAgent = $factory->createRAGAgent(['top_k' => 5]);
$autonomousAgent = $factory->createAutonomousAgent(['goal' => 'Complete task']);
$dialogAgent = $factory->createDialogAgent(['name' => 'chatbot']);
$hierarchicalAgent = $factory->createHierarchicalAgent(['workers' => 3]);
$workerAgent = $factory->createWorkerAgent(['specialty' => 'data']);

// Monitoring & management
$alertAgent = $factory->createAlertAgent(['aggregation_window' => 300]);
$monitorAgent = $factory->createMonitoringAgent(['check_interval' => 60]);
$coordinatorAgent = $factory->createCoordinatorAgent(['max_agents' => 10]);
$schedulerAgent = $factory->createSchedulerAgent(['timezone' => 'UTC']);
$prioritizationAgent = $factory->createTaskPrioritizationAgent();

// AI-specific agents
$intentAgent = $factory->createIntentClassifierAgent(['intents' => $intentList]);
$memoryAgent = $factory->createMemoryManagerAgent(['max_memories' => 1000]);
$discriminatorAgent = $factory->createSolutionDiscriminatorAgent();
$learningAgent = $factory->createLearningAgent(['learning_rate' => 0.01]);

// Foundational agents
$reflexAgent = $factory->createReflexAgent(['rules' => $rules]);
$modelAgent = $factory->createModelBasedAgent(['model' => $worldModel]);
$utilityAgent = $factory->createUtilityBasedAgent(['utility_fn' => $utilityFunction]);

// Utility agents
$simulatorAgent = $factory->createEnvironmentSimulatorAgent();
$makerAgent = $factory->createMakerAgent(['template_dir' => './templates']);
$adaptiveAgent = $factory->createAdaptiveAgentService(['strategy' => 'auto']);
```

### Available Factory Methods

**Core Reasoning Agents:**

- `createReactAgent(array $options = []): ReactAgent`
- `createChainOfThoughtAgent(array $options = []): ChainOfThoughtAgent`
- `createTreeOfThoughtsAgent(array $options = []): TreeOfThoughtsAgent`
- `createPlanExecuteAgent(array $options = []): PlanExecuteAgent`
- `createReflectionAgent(array $options = []): ReflectionAgent`

**Specialized Agents:**

- `createRAGAgent(array $options = []): RAGAgent`
- `createAutonomousAgent(array $options = []): AutonomousAgent`
- `createDialogAgent(array $options = []): DialogAgent`
- `createHierarchicalAgent(array $options = []): HierarchicalAgent`
- `createWorkerAgent(array $options = []): WorkerAgent`

**Monitoring & Management:**

- `createAlertAgent(array $options = []): AlertAgent`
- `createMonitoringAgent(array $options = []): MonitoringAgent`
- `createCoordinatorAgent(array $options = []): CoordinatorAgent`
- `createSchedulerAgent(array $options = []): SchedulerAgent`
- `createTaskPrioritizationAgent(array $options = []): TaskPrioritizationAgent`

**AI-Specific Agents:**

- `createIntentClassifierAgent(array $options = []): IntentClassifierAgent`
- `createMemoryManagerAgent(array $options = []): MemoryManagerAgent`
- `createSolutionDiscriminatorAgent(array $options = []): SolutionDiscriminatorAgent`
- `createLearningAgent(array $options = []): LearningAgent`

**Foundational Agents:**

- `createReflexAgent(array $options = []): ReflexAgent`
- `createModelBasedAgent(array $options = []): ModelBasedAgent`
- `createUtilityBasedAgent(array $options = []): UtilityBasedAgent`

**Utility Agents:**

- `createEnvironmentSimulatorAgent(array $options = []): EnvironmentSimulatorAgent`
- `createMakerAgent(array $options = []): MakerAgent`
- `createAdaptiveAgentService(array $options = []): AdaptiveAgentService`

**Generic Creation:**

- `create(string $type, array $options = []): AgentInterface` - Create any agent by type name

### Supported Agent Types

The following table shows all supported agent types and their aliases for use with the `create()` method:

| Agent Type                 | Type Strings                              | Description                    |
| -------------------------- | ----------------------------------------- | ------------------------------ |
| ReactAgent                 | `react`                                   | Reasoning + Action agent       |
| ChainOfThoughtAgent        | `cot`, `chain-of-thought`                 | Step-by-step reasoning         |
| TreeOfThoughtsAgent        | `tot`, `tree-of-thoughts`                 | Multi-branch reasoning         |
| PlanExecuteAgent           | `plan-execute`, `plan`                    | Plan then execute              |
| ReflectionAgent            | `reflection`, `reflect`                   | Self-critique and improve      |
| WorkerAgent                | `worker`                                  | Specialized task executor      |
| HierarchicalAgent          | `hierarchical`, `master`                  | Master-worker coordination     |
| RAGAgent                   | `rag`                                     | Retrieval-augmented generation |
| AutonomousAgent            | `autonomous`                              | Goal-pursuing autonomous agent |
| DialogAgent                | `dialog`, `conversation`                  | Multi-turn conversation        |
| AlertAgent                 | `alert`                                   | Intelligent alerting           |
| MonitoringAgent            | `monitoring`, `monitor`                   | System monitoring              |
| CoordinatorAgent           | `coordinator`                             | Multi-agent coordination       |
| SchedulerAgent             | `scheduler`                               | Task scheduling                |
| TaskPrioritizationAgent    | `task-prioritization`, `prioritization`   | Task priority management       |
| IntentClassifierAgent      | `intent-classifier`, `intent`             | Intent classification          |
| MemoryManagerAgent         | `memory-manager`, `memory`                | Memory management              |
| SolutionDiscriminatorAgent | `solution-discriminator`, `discriminator` | Solution evaluation            |
| ReflexAgent                | `reflex`                                  | Rule-based reflex actions      |
| EnvironmentSimulatorAgent  | `environment-simulator`, `simulator`      | Environment simulation         |
| UtilityBasedAgent          | `utility-based`, `utility`                | Utility-function based         |
| ModelBasedAgent            | `model-based`, `model`                    | World model-based              |
| LearningAgent              | `learning`                                | Adaptive learning              |
| MakerAgent                 | `maker`                                   | Code/content generation        |
| AdaptiveAgentService       | `adaptive`                                | Adaptive agent selection       |

### Benefits

- ✅ **Consistency**: All agents created with same defaults
- ✅ **DRY**: No duplication of construction logic
- ✅ **Dependency Injection**: Logger automatically provided
- ✅ **Testability**: Easy to mock factory for testing
- ✅ **Discoverability**: One place to see all agent types
- ✅ **Comprehensive**: Supports 25 agent types in the framework

### When to Use

- Creating multiple agents in your application
- Production code requiring consistent configuration
- Multi-agent systems
- When logger injection is needed
- Testing scenarios requiring mocks

---

## 2. Builder Pattern

### Overview

The **Builder Pattern** provides a fluent, type-safe API for constructing complex agent configurations.

### Problem It Solves

Without Builder:

```php
// Array configuration - no type safety, easy to make mistakes
$config = [
    'model' => 'claude-opus-4',
    'max_tokens' => 4096,
    'max_iterations' => 10,
    'system' => 'You are helpful',
    'thinking' => ['type' => 'enabled', 'budget_tokens' => 10000],
    'temperature' => 0.7,
    'custom' => ['key' => 'value'],
];

// Typos not caught until runtime
// No IDE autocomplete
// Easy to forget required fields
```

With Builder:

```php
$config = AgentConfigBuilder::create()
    ->withModel('claude-opus-4')
    ->withMaxTokens(4096)
    ->withMaxIterations(10)
    ->withSystemPrompt('You are helpful')
    ->withThinking(10000)
    ->withTemperature(0.7)
    ->withCustomOption('key', 'value')
    ->build(); // Returns AgentConfig or array
```

### Implementation

```php
use ClaudeAgents\Config\AgentConfigBuilder;

$config = AgentConfigBuilder::create()
    ->withModel('claude-opus-4')
    ->withMaxTokens(4096)
    ->build();
```

### Available Builder Methods

**Model Configuration:**

```php
->withModel(string $model)
->withMaxTokens(int $maxTokens)
->withTemperature(float $temperature)
->withTopP(float $topP)
->withTopK(int $topK)
```

**Agent Configuration:**

```php
->withName(string $name)
->withSystemPrompt(string $prompt)
->withMaxIterations(int $iterations)
->withTimeout(int $seconds)
```

**Extended Thinking:**

```php
->withThinking(int $budgetTokens)  // Shorthand
->withThinkingConfig(array $config) // Full config
```

**Tools:**

```php
->withTools(array $tools)
->addTool(Tool $tool)
```

**Custom Options:**

```php
->withCustomOption(string $key, mixed $value)
->withCustomOptions(array $options)
```

**Build:**

```php
->build(): AgentConfig     // Returns AgentConfig object
->toArray(): array         // Returns array
```

### Complete Example

```php
use ClaudeAgents\Config\AgentConfigBuilder;
use ClaudeAgents\Tools\Tool;

$searchTool = Tool::create('search')
    ->description('Search for information')
    ->stringParam('query', 'Search query')
    ->handler(fn($input) => searchAPI($input['query']));

$config = AgentConfigBuilder::create()
    // Model settings
    ->withModel('claude-opus-4')
    ->withMaxTokens(4096)
    ->withTemperature(0.7)

    // Agent settings
    ->withName('research_agent')
    ->withSystemPrompt('You are a research assistant')
    ->withMaxIterations(15)
    ->withTimeout(300)

    // Extended thinking
    ->withThinking(10000)

    // Tools
    ->addTool($searchTool)

    // Custom options
    ->withCustomOption('caching', true)

    ->build();
```

### Benefits

- ✅ **Type Safety**: Compile-time checking
- ✅ **Readability**: Self-documenting code
- ✅ **IDE Support**: Full autocomplete
- ✅ **Validation**: Early error detection
- ✅ **Flexibility**: Easy to add optional parameters
- ✅ **Immutability**: Original config unchanged

### When to Use

- Complex configurations with >3 parameters
- When type safety is important
- Production code requiring validation
- When IDE autocomplete would help
- Building reusable configuration templates

---

## 3. Observer Pattern (Event System)

### Overview

The **Observer Pattern** (Event System) allows you to monitor agent lifecycle events without coupling your monitoring code to the agent implementation.

### Problem It Solves

Without Events:

```php
// Monitoring tightly coupled to agent
class MonitoredAgent extends ReactAgent {
    public function run($task) {
        $this->log("Starting...");
        $result = parent::run($task);
        $this->log("Complete");
        return $result;
    }
}

// Hard to add new monitors
// Modifying agent for each monitor
// Can't monitor without inheritance
```

With Events:

```php
// Monitoring decoupled from agent
$dispatcher = new EventDispatcher();

$dispatcher->listen(AgentStartedEvent::class, function($event) {
    echo "Agent started: {$event->getAgentName()}";
});

$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "Completed in {$event->getDuration()}s";
});

// Add monitors without touching agent code
// Multiple listeners per event
// Easy to add/remove monitoring
```

### Implementation

```php
use ClaudeAgents\Events\EventDispatcher;
use ClaudeAgents\Events\AgentStartedEvent;
use ClaudeAgents\Events\AgentCompletedEvent;
use ClaudeAgents\Events\AgentFailedEvent;

$dispatcher = new EventDispatcher();
```

### Available Events

**AgentStartedEvent:**

```php
$dispatcher->listen(AgentStartedEvent::class, function($event) {
    $event->getAgentName();    // string
    $event->getTask();         // string
    $event->getTimestamp();    // float
    $event->getMetadata();     // array
});
```

**AgentCompletedEvent:**

```php
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    $event->getAgentName();    // string
    $event->getDuration();     // float (seconds)
    $event->getIterations();   // int
    $event->getResult();       // mixed
    $event->getTimestamp();    // float
});
```

**AgentFailedEvent:**

```php
$dispatcher->listen(AgentFailedEvent::class, function($event) {
    $event->getAgentName();    // string
    $event->getError();        // string
    $event->getException();    // ?Throwable
    $event->getDuration();     // float
    $event->getTimestamp();    // float
});
```

### Complete Example

```php
use ClaudeAgents\Events\EventDispatcher;
use ClaudeAgents\Events\{AgentStartedEvent, AgentCompletedEvent, AgentFailedEvent};

$dispatcher = new EventDispatcher();

// Log to file
$dispatcher->listen(AgentStartedEvent::class, function($event) use ($logger) {
    $logger->info("Agent started", [
        'agent' => $event->getAgentName(),
        'task' => $event->getTask(),
    ]);
});

// Track metrics
$metrics = ['total' => 0, 'success' => 0, 'failed' => 0];

$dispatcher->listen(AgentCompletedEvent::class, function($event) use (&$metrics) {
    $metrics['total']++;
    $metrics['success']++;
    $metrics['avg_duration'] = $event->getDuration();
});

$dispatcher->listen(AgentFailedEvent::class, function($event) use (&$metrics) {
    $metrics['total']++;
    $metrics['failed']++;
});

// Send alerts
$dispatcher->listen(AgentFailedEvent::class, function($event) use ($alerting) {
    if ($event->getException() instanceof CriticalError) {
        $alerting->send("Agent {$event->getAgentName()} failed critically!");
    }
});

// Multiple listeners per event - all get called
```

### Dispatching Events

```php
// In your agent or application code
$dispatcher->dispatch(new AgentStartedEvent('agent1', 'task description'));

// ... agent execution ...

$dispatcher->dispatch(new AgentCompletedEvent(
    'agent1',
    duration: 5.2,
    iterations: 8,
    result: $result
));
```

### Benefits

- ✅ **Decoupling**: Agents don't know about observers
- ✅ **Extensibility**: Add listeners without modifying agents
- ✅ **Multiple Listeners**: Many observers per event
- ✅ **Runtime Configuration**: Add/remove listeners dynamically
- ✅ **Observability**: Real-time execution tracking

### When to Use

- Monitoring and observability
- Logging and analytics
- Performance tracking
- Alerting systems
- Debugging and diagnostics
- Metrics collection

---

## 4. Strategy Pattern

### Overview

The **Strategy Pattern** allows pluggable response parsing strategies with automatic fallthrough.

### Implementation

```php
use ClaudeAgents\Parsers\ResponseParserChain;
use ClaudeAgents\Parsers\JsonResponseParser;
use ClaudeAgents\Parsers\XmlParser;
use ClaudeAgents\Parsers\MarkdownParser;

$chain = new ResponseParserChain([
    new JsonResponseParser(),
    new XmlParser(),
    new MarkdownParser(),
]);

// Try each parser until one succeeds
$result = $chain->parse($response);
```

### Benefits

- ✅ **Flexibility**: Multiple parsing strategies
- ✅ **Automatic**: Chain tries parsers until success
- ✅ **Extensible**: Add parsers without modifying existing code
- ✅ **Clean**: Each parser has single responsibility

### When to Use

- Handling multiple response formats
- When format is unknown ahead of time
- Building robust parsing pipelines
- Supporting multiple output formats

**See [Parsers Documentation](Parsers.md) for complete details.**

---

## 5. Template Method Pattern

### Overview

The **Template Method Pattern** provides a fluent API for constructing complex prompts from multiple sections.

### Implementation

```php
use ClaudeAgents\Prompts\PromptBuilder;

$prompt = PromptBuilder::create()
    ->addContext('You are a helpful assistant')
    ->addTask('Solve this problem step by step')
    ->addExample('Input: 2+2', 'Output: 4')
    ->addConstraint('Show your reasoning')
    ->addInstructions('Format as JSON')
    ->build();
```

### Available Methods

```php
->addContext(string $context)
->addTask(string $task)
->addExample(string $input, string $output)
->addConstraint(string $constraint)
->addInstructions(string $instructions)
->addSection(string $title, string $content)
->build(): string
->clear(): self
```

### Benefits

- ✅ **Readability**: Clear prompt structure
- ✅ **Reusability**: Build prompts programmatically
- ✅ **Consistency**: Standard prompt format
- ✅ **Maintainability**: Easy to modify structure

### When to Use

- Complex, multi-section prompts
- When prompt structure needs to be consistent
- Building prompt templates
- Dynamic prompt construction

**See [Prompts Documentation](Prompts.md) for complete details.**

---

## 6. Error Handler Pattern

### Overview

The **Error Handler Pattern** provides unified error handling with automatic retry and exponential backoff.

### Implementation

```php
use ClaudeAgents\Exceptions\ErrorHandler;

$handler = new ErrorHandler($logger, maxRetries: 3, initialDelayMs: 500);

$result = $handler->executeWithRetry(
    fn: fn() => $agent->run($task),
    context: 'Agent execution'
);
```

### Features

- Configurable retry attempts
- Exponential backoff
- Error classification (retryable vs not)
- Logging and callbacks
- Safe tool execution

### Available Methods

```php
// Execute with retry
$handler->executeWithRetry(callable $fn, string $context, array $options);

// Safe tool execution
$handler->executeToolSafely(callable $toolFn, string $toolName, array $input);

// Tool with fallback
$handler->executeToolWithFallback(callable $toolFn, string $toolName, array $input, string $fallback);

// Input validation
$handler->validateToolInput(array $input, array $schema);

// Rate limiting
$rateLimiter = ErrorHandler::createRateLimiter(int $minIntervalMs);
```

### Benefits

- ✅ **Resilience**: Automatic retry on transient failures
- ✅ **Consistency**: Same error handling everywhere
- ✅ **Observability**: Callbacks for monitoring
- ✅ **Smart**: Exponential backoff prevents overwhelming

### When to Use

- All external API calls
- Tool execution
- Network operations
- Any operation that might fail transiently

**See [Best Practices Documentation](BestPractices.md) for complete details.**

---

## Pattern Combinations

### Factory + Builder

Perfect for complex agent creation:

```php
$factory = new AgentFactory($client, $logger);

$config = AgentConfigBuilder::create()
    ->withModel('claude-opus-4')
    ->withMaxTokens(4096)
    ->withThinking(10000)
    ->toArray();

$agent = $factory->create('react', $config);
```

### Factory + Events

Monitor all agents created by factory:

```php
$factory = new AgentFactory($client, $logger);
$dispatcher = new EventDispatcher();

$dispatcher->listen(AgentStartedEvent::class, fn($e) =>
    echo "Started: {$e->getAgentName()}"
);

// Create agents, events automatically tracked
$agent = $factory->createReactAgent(['name' => 'agent1']);
```

### Builder + Events + ErrorHandler

Production-ready agent setup:

```php
$config = AgentConfigBuilder::create()
    ->withModel('claude-opus-4')
    ->withMaxTokens(4096)
    ->withMaxIterations(10)
    ->build();

$dispatcher = new EventDispatcher();
$dispatcher->listen(AgentCompletedEvent::class, $yourHandler);

$errorHandler = new ErrorHandler($logger, maxRetries: 3);

$result = $errorHandler->executeWithRetry(
    fn() => $agent->run($task),
    context: 'Production agent'
);
```

### All Patterns Together

```php
// Setup
$factory = new AgentFactory($client, $logger);
$dispatcher = new EventDispatcher();
$errorHandler = new ErrorHandler($logger);

// Configure
$config = AgentConfigBuilder::create()
    ->withModel('claude-opus-4')
    ->withMaxTokens(4096)
    ->withThinking(10000)
    ->build();

// Create
$agent = $factory->create('react', $config);

// Build prompt
$prompt = PromptBuilder::create()
    ->addContext('You are helpful')
    ->addTask($task)
    ->build();

// Execute with monitoring and error handling
$dispatcher->listen(AgentStartedEvent::class, $yourMonitor);

$result = $errorHandler->executeWithRetry(
    fn() => $agent->run($prompt),
    context: 'Full stack execution'
);

// Parse result
$chain = new ResponseParserChain([
    new JsonResponseParser(),
    new MarkdownParser(),
]);
$parsed = $chain->parse($result);
```

---

## When to Use Each Pattern

### Quick Reference

| Scenario         | Pattern          | Why                         |
| ---------------- | ---------------- | --------------------------- |
| Creating agents  | **Factory**      | Consistency and DI          |
| Complex config   | **Builder**      | Type safety and readability |
| Monitoring       | **Observer**     | Decoupled observability     |
| Multiple formats | **Strategy**     | Flexible parsing            |
| Complex prompts  | **Template**     | Structured construction     |
| API calls        | **ErrorHandler** | Resilience and retry        |

### Decision Tree

```
Need to create agents?
├─ Yes → Use Factory
└─ No → Continue

Configuration has >3 parameters?
├─ Yes → Use Builder
└─ No → Use array

Need to monitor execution?
├─ Yes → Use Events
└─ No → Continue

Response format varies?
├─ Yes → Use Strategy (ParserChain)
└─ No → Use specific parser

Prompt has multiple sections?
├─ Yes → Use Template (PromptBuilder)
└─ No → Use string

Making external calls?
├─ Yes → Use ErrorHandler
└─ No → Continue
```

---

## Best Practices

### 1. Always Use Factory in Production

```php
// ✅ Good - Consistent, testable
$factory = new AgentFactory($client, $logger);
$agent = $factory->create('react', $config);

// ❌ Avoid - Inconsistent, harder to test
$agent = new ReactAgent($client, $config, $logger);
```

### 2. Use Builder for Complex Configs

```php
// ✅ Good - Type-safe, readable
$config = AgentConfigBuilder::create()
    ->withModel('claude-opus-4')
    ->withMaxTokens(4096)
    ->withThinking(10000)
    ->build();

// ❌ Avoid - Error-prone, no type safety
$config = ['model' => 'claude-opus-4', 'max_tokens' => 4096, ...];
```

### 3. Set Up Events Early

```php
// ✅ Good - Set up once, monitor everything
$dispatcher = new EventDispatcher();
$dispatcher->listen(AgentStartedEvent::class, $monitor);
// Now all agents emit events

// ❌ Avoid - Ad-hoc monitoring per agent
$agent->onIteration(fn() => echo "Iteration...");
```

### 4. Chain Parsers for Robustness

```php
// ✅ Good - Handles multiple formats
$chain = new ResponseParserChain([
    new JsonResponseParser(),
    new XmlParser(),
    new MarkdownParser(),
]);

// ❌ Avoid - Fails if format changes
$parser = new JsonResponseParser();
```

### 5. Always Wrap API Calls

```php
// ✅ Good - Resilient to transient failures
$handler = new ErrorHandler($logger, maxRetries: 3);
$result = $handler->executeWithRetry(fn() => $agent->run($task));

// ❌ Avoid - No retry, fails on transient errors
$result = $agent->run($task);
```

### 6. Combine Patterns Appropriately

```php
// ✅ Good - Full stack best practices
$factory = new AgentFactory($client, $logger);
$config = AgentConfigBuilder::create()->withModel('opus')->build();
$agent = $factory->create('react', $config);
$dispatcher->listen(AgentCompletedEvent::class, $handler);
$result = $errorHandler->executeWithRetry(fn() => $agent->run($task));

// ❌ Avoid - Missing best practices
$agent = new ReactAgent($client);
$result = $agent->run($task);
```

---

## Anti-Patterns to Avoid

### 1. Not Using Factory

```php
// ❌ Bad - Every developer creates differently
$agent1 = new ReactAgent($client);
$agent2 = new ReactAgent($client, ['name' => 'a'], $logger);
$agent3 = new ReactAgent($client, $options);

// ✅ Good - Consistent creation
$factory = new AgentFactory($client, $logger);
$agent1 = $factory->create('react');
$agent2 = $factory->create('react', ['name' => 'a']);
```

### 2. Magic Arrays Instead of Builder

```php
// ❌ Bad - Typos not caught
$config = ['modl' => 'opus']; // Typo!

// ✅ Good - Typo caught at compile time
$config = AgentConfigBuilder::create()->withModel('opus')->build();
```

### 3. Tight Coupling for Monitoring

```php
// ❌ Bad - Agent knows about monitoring
class MonitoredAgent extends ReactAgent {
    public function run($task) {
        $this->monitor->log("Starting");
        return parent::run($task);
    }
}

// ✅ Good - Monitoring decoupled
$dispatcher->listen(AgentStartedEvent::class, fn() => $monitor->log());
```

### 4. No Error Handling

```php
// ❌ Bad - Fails on first error
$result = $agent->run($task);

// ✅ Good - Resilient to failures
$handler = new ErrorHandler($logger);
$result = $handler->executeWithRetry(fn() => $agent->run($task));
```

---

## Summary

The Claude PHP Agent Framework uses six design patterns to provide:

- **Factory Pattern** - Consistent agent creation
- **Builder Pattern** - Type-safe configuration
- **Observer Pattern** - Decoupled monitoring
- **Strategy Pattern** - Flexible parsing
- **Template Pattern** - Structured prompts
- **Error Handler** - Resilient execution

### Key Takeaways

1. **Use Factory** for all agent creation in production
2. **Use Builder** when configuration has >3 parameters
3. **Use Events** for monitoring and observability
4. **Use ParserChain** for robust response handling
5. **Use PromptBuilder** for complex prompts
6. **Use ErrorHandler** for all external calls

### Next Steps

- Read [Best Practices](BestPractices.md) for production patterns
- See [Examples](../examples/design_patterns_demo.php) for working code
- Explore [Factory Documentation](Factory.md) for detailed API
- Learn [Builder Documentation](Builder.md) for configuration
- Review [Events Documentation](Events.md) for monitoring

---

_Last Updated: December 2024_  
_Framework Version: 2.0+_
