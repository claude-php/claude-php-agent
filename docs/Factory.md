# Factory Pattern

Centralized agent creation using the Factory Pattern for consistency, reduced coupling, and improved testability.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Creating Agents](#creating-agents)
- [Configuration](#configuration)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Examples](#examples)

## Overview

The **Factory Pattern** is a creational design pattern that provides a unified interface for creating agents. Instead of directly instantiating agent classes, you use the `AgentFactory`, which:

- ✅ Centralizes creation logic
- ✅ Ensures consistent configuration
- ✅ Reduces coupling between code and agent classes
- ✅ Simplifies testing with dependency injection
- ✅ Provides type-safe factory methods

### Problem Without Factory

```php
// ❌ Scattered creation logic, inconsistent logging, tight coupling
$reactAgent = new ReactAgent($client, AgentConfig::create('react1'), $logger);
$chainAgent = new ChainOfThoughtAgent($client, AgentConfig::create('chain1'), $logger);
$planAgent = new PlanExecuteReflectAgent($client, AgentConfig::create('plan1'), $logger);

// What if we need to change constructor signature?
// What if we need to add validation?
// How do we mock for testing?
```

### Solution With Factory

```php
// ✅ Centralized, consistent, testable
$factory = new AgentFactory($client, $logger);

$reactAgent = $factory->create('react', ['name' => 'react1']);
$chainAgent = $factory->create('chain_of_thought', ['name' => 'chain1']);
$planAgent = $factory->create('plan_execute', ['name' => 'plan1']);

// Or use type-safe methods
$reactAgent = $factory->createReactAgent(['name' => 'react1']);
```

## Quick Start

### Basic Usage

```php
use ClaudeAgents\Factory\AgentFactory;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Initialize dependencies
$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);
$logger = new Logger('agents');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Create factory
$factory = new AgentFactory($client, $logger);

// Create agents
$agent = $factory->create('react', [
    'name' => 'customer_support',
    'model' => 'claude-sonnet-4-20250514',
    'max_iterations' => 10,
]);

$result = $agent->run('Help me with my order #12345');
```

### With Event Dispatcher

```php
use ClaudeAgents\Events\EventDispatcher;

$dispatcher = new EventDispatcher();
$factory = new AgentFactory($client, $logger, $dispatcher);

// All agents created will automatically dispatch lifecycle events
$agent = $factory->create('react');
```

## Creating Agents

### Generic `create()` Method

```php
$agent = $factory->create(string $type, array $config = []);
```

Supported agent types:

| Type | Agent Class | Description |
|------|-------------|-------------|
| `react` | `ReactAgent` | ReAct pattern (Reasoning + Acting) |
| `chain_of_thought` | `ChainOfThoughtAgent` | Step-by-step reasoning |
| `plan_execute` | `PlanExecuteReflectAgent` | Planning with reflection |
| `hierarchical` | `HierarchicalAgent` | Multi-level delegation |
| `autonomous` | `AutonomousAgent` | Self-directed execution |
| `dialog` | `DialogAgent` | Conversational |
| `learning` | `LearningAgent` | Adaptive learning |
| `reflection` | `ReflectionAgent` | Self-improvement |
| `coordinator` | `CoordinatorAgent` | Multi-agent orchestration |
| `tree_of_thoughts` | `TreeOfThoughtsAgent` | Branching exploration |

**Example:**

```php
$agent = $factory->create('react', [
    'name' => 'my_agent',
    'model' => 'claude-opus-4-20250514',
    'max_iterations' => 15,
    'temperature' => 0.7,
]);
```

### Type-Safe Factory Methods

For better IDE support and type safety, use specific factory methods:

```php
// ReAct Agent
$agent = $factory->createReactAgent([
    'name' => 'react_agent',
    'max_iterations' => 10,
]);

// Chain of Thought Agent
$agent = $factory->createChainOfThoughtAgent([
    'name' => 'cot_agent',
    'model' => 'claude-sonnet-4-20250514',
]);

// Plan Execute Agent
$agent = $factory->createPlanExecuteAgent([
    'name' => 'planner',
    'reflection_enabled' => true,
]);

// Hierarchical Agent
$agent = $factory->createHierarchicalAgent([
    'name' => 'manager',
]);

// Autonomous Agent
$agent = $factory->createAutonomousAgent([
    'name' => 'autonomous',
]);

// Dialog Agent
$agent = $factory->createDialogAgent([
    'name' => 'chat',
]);

// Learning Agent
$agent = $factory->createLearningAgent([
    'name' => 'adaptive',
]);

// Reflection Agent
$agent = $factory->createReflectionAgent([
    'name' => 'reflective',
]);

// Coordinator Agent
$agent = $factory->createCoordinatorAgent([
    'name' => 'coordinator',
]);

// Tree of Thoughts Agent
$agent = $factory->createTreeOfThoughtsAgent([
    'name' => 'explorer',
]);
```

## Configuration

### Common Configuration Options

All agents support these configuration options:

```php
$agent = $factory->create('react', [
    'name' => 'my_agent',              // Required: Agent identifier
    'model' => 'claude-opus-4-20250514', // Model to use
    'max_iterations' => 10,            // Maximum reasoning loops
    'temperature' => 0.7,              // Response creativity (0-1)
    'max_tokens' => 4000,              // Maximum response length
    'system_prompt' => 'Custom prompt', // System instructions
]);
```

### Agent-Specific Configuration

Some agents have additional options:

**ReAct Agent:**
```php
$agent = $factory->createReactAgent([
    'name' => 'react',
    'max_iterations' => 15,            // ReAct loops
    'require_final_answer' => true,    // Enforce final answer format
]);
```

**Plan Execute Agent:**
```php
$agent = $factory->createPlanExecuteAgent([
    'name' => 'planner',
    'reflection_enabled' => true,      // Enable self-reflection
    'plan_steps' => 5,                 // Number of planning steps
]);
```

**Hierarchical Agent:**
```php
$agent = $factory->createHierarchicalAgent([
    'name' => 'manager',
    'delegation_strategy' => 'capability', // How to delegate
]);
```

### Default Values

The factory provides sensible defaults:

```php
[
    'model' => 'claude-sonnet-4-20250514',
    'max_iterations' => 10,
    'temperature' => 0.7,
    'max_tokens' => 4096,
]
```

Override any of these as needed.

## Testing

### Mocking the Factory

```php
use PHPUnit\Framework\TestCase;
use ClaudeAgents\Factory\AgentFactory;

class MyServiceTest extends TestCase
{
    public function testAgentCreation()
    {
        // Create mock factory
        $factory = $this->createMock(AgentFactory::class);
        
        // Set expectations
        $mockAgent = $this->createMock(ReactAgent::class);
        $factory->expects($this->once())
            ->method('create')
            ->with('react', ['name' => 'test'])
            ->willReturn($mockAgent);
        
        // Test your service
        $service = new MyService($factory);
        $agent = $service->createAgent('test');
        
        $this->assertSame($mockAgent, $agent);
    }
}
```

### Injecting Factory as Dependency

```php
class AgentService
{
    public function __construct(
        private AgentFactory $factory
    ) {}
    
    public function createSupportAgent(): Agent
    {
        return $this->factory->createReactAgent([
            'name' => 'support',
            'max_iterations' => 5,
        ]);
    }
}

// In production
$service = new AgentService($factory);

// In tests
$mockFactory = $this->createMock(AgentFactory::class);
$service = new AgentService($mockFactory);
```

### Test Doubles

```php
// Create a real factory with test configuration
$testLogger = new Logger('test');
$testLogger->pushHandler(new NullHandler());

$testFactory = new AgentFactory($client, $testLogger);

// Use in integration tests
$agent = $testFactory->create('react', ['name' => 'test']);
```

## Best Practices

### 1. Always Use Factory

**❌ Don't:**
```php
$agent = new ReactAgent($client, AgentConfig::create('test'), $logger);
```

**✅ Do:**
```php
$agent = $factory->createReactAgent(['name' => 'test']);
```

### 2. Inject Factory as Dependency

**❌ Don't:**
```php
class Service {
    public function process() {
        $client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);
        $logger = new Logger('app');
        $factory = new AgentFactory($client, $logger);
        $agent = $factory->create('react');
    }
}
```

**✅ Do:**
```php
class Service {
    public function __construct(
        private AgentFactory $factory
    ) {}
    
    public function process() {
        $agent = $this->factory->create('react');
    }
}
```

### 3. Use Type-Safe Methods When Possible

**❌ Less Safe:**
```php
$agent = $factory->create('react', ['name' => 'test']);
```

**✅ More Safe:**
```php
$agent = $factory->createReactAgent(['name' => 'test']);
```

### 4. Configure Once, Reuse

```php
// ❌ Don't: Repeated configuration
$agent1 = $factory->create('react', ['model' => 'opus', 'temp' => 0.7]);
$agent2 = $factory->create('react', ['model' => 'opus', 'temp' => 0.7]);

// ✅ Do: Shared configuration
$config = ['model' => 'opus', 'temperature' => 0.7];
$agent1 = $factory->create('react', [...$config, 'name' => 'agent1']);
$agent2 = $factory->create('react', [...$config, 'name' => 'agent2']);
```

### 5. Combine with Builder for Complex Config

```php
use ClaudeAgents\Builder\AgentConfigBuilder;

$config = AgentConfigBuilder::create()
    ->name('complex_agent')
    ->model('claude-opus-4-20250514')
    ->maxIterations(20)
    ->temperature(0.8)
    ->systemPrompt('You are an expert...')
    ->build();

$agent = $factory->create('react', $config->toArray());
```

### 6. Use Events for Monitoring

```php
$dispatcher = new EventDispatcher();
$dispatcher->listen(AgentStartedEvent::class, function($event) {
    echo "Agent {$event->getAgentName()} started\n";
});

$factory = new AgentFactory($client, $logger, $dispatcher);

// All agents created will dispatch events
$agent = $factory->create('react');
```

## Examples

### Multi-Agent System

```php
$factory = new AgentFactory($client, $logger);

// Create specialized agents
$researcher = $factory->createReactAgent([
    'name' => 'researcher',
    'system_prompt' => 'You are a research specialist',
]);

$writer = $factory->createChainOfThoughtAgent([
    'name' => 'writer',
    'system_prompt' => 'You are a technical writer',
]);

$reviewer = $factory->createReflectionAgent([
    'name' => 'reviewer',
    'system_prompt' => 'You review and improve content',
]);

// Use in workflow
$findings = $researcher->run('Research topic X');
$draft = $writer->run("Write about: {$findings->getAnswer()}");
$final = $reviewer->run("Review and improve: {$draft->getAnswer()}");
```

### Agent Templates

```php
class AgentTemplates
{
    public function __construct(
        private AgentFactory $factory
    ) {}
    
    public function createCustomerSupport(): Agent
    {
        return $this->factory->createDialogAgent([
            'name' => 'support',
            'system_prompt' => 'You are a helpful customer support agent...',
            'temperature' => 0.7,
            'max_iterations' => 5,
        ]);
    }
    
    public function createDataAnalyst(): Agent
    {
        return $this->factory->createReactAgent([
            'name' => 'analyst',
            'system_prompt' => 'You are a data analyst with expertise in...',
            'max_iterations' => 15,
        ]);
    }
    
    public function createCodeReviewer(): Agent
    {
        return $this->factory->createReflectionAgent([
            'name' => 'reviewer',
            'system_prompt' => 'You are an expert code reviewer...',
            'temperature' => 0.3, // More deterministic
        ]);
    }
}

// Usage
$templates = new AgentTemplates($factory);
$agent = $templates->createCustomerSupport();
```

### Testing with Factory

```php
class OrderProcessorTest extends TestCase
{
    public function testProcessOrder()
    {
        // Create test factory
        $client = $this->createMock(ClaudePhp::class);
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        
        $factory = new AgentFactory($client, $logger);
        
        // Inject into system under test
        $processor = new OrderProcessor($factory);
        
        // Test
        $result = $processor->process(['order_id' => 123]);
        $this->assertTrue($result->isSuccess());
    }
}
```

## See Also

- [Builder Pattern](Builder.md) - Type-safe configuration
- [Design Patterns](DesignPatterns.md) - All patterns overview
- [Best Practices](BestPractices.md) - Agent creation patterns
- [Examples](../examples/factory_pattern_example.php) - Working code

## API Reference

### AgentFactory

```php
class AgentFactory
{
    public function __construct(
        ClaudePhp $client,
        ?Logger $logger = null,
        ?EventDispatcher $dispatcher = null
    );
    
    // Generic creation
    public function create(string $type, array $config = []): Agent;
    
    // Type-safe methods
    public function createReactAgent(array $config = []): ReactAgent;
    public function createChainOfThoughtAgent(array $config = []): ChainOfThoughtAgent;
    public function createPlanExecuteAgent(array $config = []): PlanExecuteReflectAgent;
    public function createHierarchicalAgent(array $config = []): HierarchicalAgent;
    public function createAutonomousAgent(array $config = []): AutonomousAgent;
    public function createDialogAgent(array $config = []): DialogAgent;
    public function createLearningAgent(array $config = []): LearningAgent;
    public function createReflectionAgent(array $config = []): ReflectionAgent;
    public function createCoordinatorAgent(array $config = []): CoordinatorAgent;
    public function createTreeOfThoughtsAgent(array $config = []): TreeOfThoughtsAgent;
}
```

### Configuration Array

```php
[
    'name' => string,              // Required
    'model' => string,             // Optional, default: 'claude-sonnet-4-20250514'
    'max_iterations' => int,       // Optional, default: 10
    'temperature' => float,        // Optional, default: 0.7
    'max_tokens' => int,           // Optional, default: 4096
    'system_prompt' => string,     // Optional
    // ... agent-specific options
]
```

