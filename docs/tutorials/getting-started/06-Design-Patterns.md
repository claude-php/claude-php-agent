# Tutorial 6: Design Patterns for Production Code

**Time: 45 minutes** | **Difficulty: Intermediate**

You've learned how to build agents, handle errors, and implement advanced patterns. Now let's make your code truly production-ready using industry-standard design patterns.

## 🎯 Learning Objectives

By the end of this tutorial, you'll be able to:

- Use the Factory Pattern for consistent agent creation
- Apply the Builder Pattern for type-safe configuration  
- Implement the Observer Pattern for event-driven monitoring
- Use the Strategy Pattern for flexible response parsing
- Apply the Template Method Pattern for structured prompts
- Combine patterns for production-quality code
- Create reusable agent templates

## 🏗️ What We're Building

We'll refactor a basic agent into a production-ready system using:

1. **Factory Pattern** - Centralized agent creation
2. **Builder Pattern** - Type-safe configuration
3. **Observer Pattern** - Event-driven monitoring
4. **Strategy Pattern** - Flexible response parsing
5. **Template Method** - Structured prompts
6. **Error Handler Pattern** - Resilient execution

## 📋 Prerequisites

Make sure you have:

- Completed [Tutorial 4: Production Patterns](./04-Production-Patterns.md)
- Understanding of production systems
- Basic knowledge of design patterns (helpful but not required)
- Claude PHP Agent Framework installed

## 🎨 Why Design Patterns?

### The Problem with Direct Creation

```php
// ❌ Without patterns: Hard to maintain, test, and scale
$agent1 = new ReactAgent($client, AgentConfig::create('agent1'), $logger);
$agent2 = new ReactAgent($client, AgentConfig::create('agent2'), $logger);

// What if constructor changes?
// How do we ensure consistent configuration?
// How do we mock for testing?
// How do we monitor execution?
```

### The Solution with Patterns

```php
// ✅ With patterns: Maintainable, testable, scalable
$factory = new AgentFactory($client, $logger, $eventDispatcher);

$config = AgentConfigBuilder::create()
    ->name('agent1')
    ->maxIterations(10)
    ->build();

$agent = $factory->createReactAgent($config->toArray());

// Easy to maintain, test, and monitor!
```

---

## 🏭 Step 1: Factory Pattern

The Factory Pattern centralizes object creation.

### Basic Usage

```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Factory\AgentFactory;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Setup
$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);
$logger = new Logger('tutorial');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Create factory
$factory = new AgentFactory($client, $logger);

echo "✅ Created AgentFactory\n\n";
```

### Creating Agents

```php
// Generic creation
$agent = $factory->create('react', [
    'name' => 'customer_support',
    'max_iterations' => 10,
]);

echo "✅ Created ReAct agent via factory\n";

// Type-safe creation (recommended)
$reactAgent = $factory->createReactAgent([
    'name' => 'react_agent',
    'max_iterations' => 15,
]);

$dialogAgent = $factory->createDialogAgent([
    'name' => 'dialog_agent',
    'temperature' => 0.7,
]);

echo "✅ Created agents with type-safe methods\n\n";
```

### Benefits

- ✅ Consistent configuration across all agents
- ✅ Centralized dependency injection
- ✅ Easy to test with mocks
- ✅ Type-safe factory methods

---

## 🔨 Step 2: Builder Pattern

The Builder Pattern provides fluent, validated configuration.

### Basic Configuration

```php
use ClaudeAgents\Builder\AgentConfigBuilder;

$config = AgentConfigBuilder::create()
    ->name('production_agent')
    ->model('claude-sonnet-4-20250514')
    ->maxIterations(15)
    ->temperature(0.7)
    ->maxTokens(4096)
    ->systemPrompt('You are a helpful assistant')
    ->build();

echo "✅ Built configuration with fluent API\n\n";

// Use with factory
$agent = $factory->createReactAgent($config->toArray());
```

### Automatic Validation

```php
try {
    $invalidConfig = AgentConfigBuilder::create()
        ->name('test')
        ->temperature(5.0)  // Invalid! Must be 0.0-1.0
        ->build();
} catch (InvalidArgumentException $e) {
    echo "❌ Caught validation error: {$e->getMessage()}\n";
}

try {
    $invalidConfig2 = AgentConfigBuilder::create()
        ->name('test')
        ->maxIterations(200)  // Invalid! Must be 1-100
        ->build();
} catch (InvalidArgumentException $e) {
    echo "❌ Caught validation error: {$e->getMessage()}\n\n";
}
```

### Configuration Templates

```php
class AgentTemplates
{
    public static function customerSupport(): AgentConfig
    {
        return AgentConfigBuilder::create()
            ->name('support')
            ->model('claude-sonnet-4-20250514')
            ->temperature(0.7)
            ->maxIterations(5)
            ->systemPrompt('You are a helpful customer support agent with expertise in resolving issues.')
            ->build();
    }
    
    public static function dataAnalyst(): AgentConfig
    {
        return AgentConfigBuilder::create()
            ->name('analyst')
            ->model('claude-opus-4-20250514')
            ->temperature(0.3)  // More deterministic
            ->maxIterations(15)
            ->systemPrompt('You are a data analyst with expertise in statistics and visualization.')
            ->build();
    }
    
    public static function codeReviewer(): AgentConfig
    {
        return AgentConfigBuilder::create()
            ->name('reviewer')
            ->model('claude-opus-4-20250514')
            ->temperature(0.2)  // Very deterministic
            ->maxIterations(10)
            ->systemPrompt('You are an expert code reviewer focusing on security and best practices.')
            ->build();
    }
}

// Usage
$supportConfig = AgentTemplates::customerSupport();
$supportAgent = $factory->createDialogAgent($supportConfig->toArray());

echo "✅ Created agent from template\n\n";
```

### Benefits

- ✅ IDE autocomplete and type checking
- ✅ Automatic validation (temperature 0-1, iterations 1-100)
- ✅ Clear, self-documenting code
- ✅ Reusable configuration templates

---

## 👀 Step 3: Observer Pattern (Events)

The Observer Pattern enables decoupled monitoring.

### Setup Event Dispatcher

```php
use ClaudeAgents\Events\EventDispatcher;
use ClaudeAgents\Events\{AgentStartedEvent, AgentCompletedEvent, AgentFailedEvent};

$dispatcher = new EventDispatcher();

echo "✅ Created EventDispatcher\n\n";
```

### Subscribe to Events

```php
// Event 1: Agent started
$dispatcher->listen(AgentStartedEvent::class, function($event) {
    echo "🚀 Agent '{$event->getAgentName()}' started\n";
    echo "   Task: {$event->getTask()}\n";
});

// Event 2: Agent completed
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "✅ Agent '{$event->getAgentName()}' completed\n";
    echo "   Duration: " . round($event->getDuration(), 2) . "s\n";
    echo "   Iterations: {$event->getIterations()}\n";
});

// Event 3: Agent failed
$dispatcher->listen(AgentFailedEvent::class, function($event) {
    echo "❌ Agent '{$event->getAgentName()}' failed\n";
    echo "   Error: {$event->getError()}\n";
});

echo "✅ Subscribed to lifecycle events\n\n";
```

### Metrics Collection with Events

```php
class MetricsCollector
{
    private array $metrics = [
        'total_runs' => 0,
        'successful_runs' => 0,
        'failed_runs' => 0,
        'total_duration' => 0.0,
    ];
    
    public function onAgentCompleted(AgentCompletedEvent $event): void
    {
        $this->metrics['total_runs']++;
        $this->metrics['successful_runs']++;
        $this->metrics['total_duration'] += $event->getDuration();
    }
    
    public function onAgentFailed(AgentFailedEvent $event): void
    {
        $this->metrics['total_runs']++;
        $this->metrics['failed_runs']++;
    }
    
    public function getMetrics(): array
    {
        return [
            ...$this->metrics,
            'success_rate' => $this->metrics['total_runs'] > 0
                ? $this->metrics['successful_runs'] / $this->metrics['total_runs']
                : 0,
            'avg_duration' => $this->metrics['successful_runs'] > 0
                ? $this->metrics['total_duration'] / $this->metrics['successful_runs']
                : 0,
        ];
    }
}

$metrics = new MetricsCollector();
$dispatcher->listen(AgentCompletedEvent::class, [$metrics, 'onAgentCompleted']);
$dispatcher->listen(AgentFailedEvent::class, [$metrics, 'onAgentFailed']);

echo "✅ Registered metrics collector\n\n";
```

### Integrate with Factory

```php
// Create factory with event dispatcher
$factoryWithEvents = new AgentFactory($client, $logger, $dispatcher);

// All agents created will automatically dispatch events
$monitoredAgent = $factoryWithEvents->createReactAgent([
    'name' => 'monitored_agent',
]);

echo "✅ Created factory with automatic event dispatching\n\n";
```

### Benefits

- ✅ Agents don't know about monitoring code
- ✅ Add/remove monitoring without changing agents
- ✅ Multiple listeners per event
- ✅ Real-time metrics and alerts

---

## 🎯 Step 4: Putting It All Together

Let's build a production-ready system using all patterns:

```php
echo "═══ Production-Ready Setup ═══\n\n";

// 1. Create event dispatcher for monitoring
$productionDispatcher = new EventDispatcher();

// 2. Set up metrics collection
$productionMetrics = new MetricsCollector();
$productionDispatcher->listen(AgentCompletedEvent::class, [$productionMetrics, 'onAgentCompleted']);
$productionDispatcher->listen(AgentFailedEvent::class, [$productionMetrics, 'onAgentFailed']);

// 3. Set up console logging
$productionDispatcher->listen(AgentStartedEvent::class, function($event) {
    echo "[" . date('H:i:s') . "] Agent started: {$event->getAgentName()}\n";
});

$productionDispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "[" . date('H:i:s') . "] Agent completed in " . round($event->getDuration(), 2) . "s\n";
});

// 4. Create factory with all dependencies
$productionFactory = new AgentFactory($client, $logger, $productionDispatcher);

// 5. Build configuration with builder
$productionConfig = AgentConfigBuilder::create()
    ->name('production_agent')
    ->model('claude-sonnet-4-20250514')
    ->maxIterations(10)
    ->temperature(0.7)
    ->systemPrompt('You are a production AI assistant.')
    ->build();

// 6. Create agent via factory
$productionAgent = $productionFactory->createReactAgent($productionConfig->toArray());

// 7. Add tools
$productionAgent->withTool(
    Tool::create('get_weather')
        ->description('Get current weather')
        ->stringParam('location', 'City name')
        ->handler(function($input) {
            return "Weather in {$input['location']}: 72°F, sunny";
        })
);

echo "✅ Production setup complete!\n\n";

// 8. Run agent (events dispatch automatically)
echo "═══ Running Production Agent ═══\n\n";

$result = $productionAgent->run('What is the weather in San Francisco?');

if ($result->isSuccess()) {
    echo "\n📊 Result: " . substr($result->getAnswer(), 0, 100) . "...\n\n";
}

// 9. Display metrics
echo "═══ Metrics ═══\n\n";
$stats = $productionMetrics->getMetrics();
echo "Total Runs: {$stats['total_runs']}\n";
echo "Successful: {$stats['successful_runs']}\n";
echo "Failed: {$stats['failed_runs']}\n";
echo "Success Rate: " . round($stats['success_rate'] * 100, 1) . "%\n";
echo "Avg Duration: " . round($stats['avg_duration'], 2) . "s\n\n";
```

---

## 🔧 Step 5: Additional Patterns

### Strategy Pattern: Response Parsing

```php
use ClaudeAgents\Parsers\ResponseParserChain;
use ClaudeAgents\Parsers\{JsonParser, MarkdownParser, XmlParser};

$parserChain = new ResponseParserChain([
    new JsonParser(),
    new MarkdownParser(),
    new XmlParser(),
]);

// Automatically tries parsers until one succeeds
$jsonResponse = '```json{"status": "success", "data": {"id": 1}}```';
$parsed = $parserChain->parse($jsonResponse);

echo "✅ Parsed JSON response: " . json_encode($parsed) . "\n\n";
```

### Template Method: Prompt Building

```php
use ClaudeAgents\Prompts\PromptBuilder;

$prompt = PromptBuilder::create()
    ->addContext('You are a helpful coding assistant')
    ->addTask('Review the following PHP code')
    ->addCode('<?php function add($a, $b) { return $a + $b; } ?>', 'php')
    ->addConstraint('Limit feedback to 3 key points')
    ->addInstructions('Be concise and actionable')
    ->build();

echo "✅ Built structured prompt\n\n";
echo "Prompt preview:\n";
echo substr($prompt, 0, 200) . "...\n\n";
```

---

## ✅ Checkpoint

You now have a production-ready agent system with:

- ✅ Centralized creation (Factory)
- ✅ Type-safe configuration (Builder)
- ✅ Automatic monitoring (Observer)
- ✅ Flexible parsing (Strategy)
- ✅ Structured prompts (Template Method)

---

## 💡 Best Practices

### 1. Always Use Factory

**❌ Don't:**
```php
$agent = new ReactAgent($client, AgentConfig::create('test'), $logger);
```

**✅ Do:**
```php
$agent = $factory->createReactAgent(['name' => 'test']);
```

### 2. Use Builder for Complex Configs

**❌ Don't:**
```php
$config = ['name' => 'agent', 'model' => 'opus', 'max_iterations' => 20];
```

**✅ Do:**
```php
$config = AgentConfigBuilder::create()
    ->name('agent')
    ->model('claude-opus-4-20250514')
    ->maxIterations(20)
    ->build();
```

### 3. Monitor with Events

**❌ Don't:**
```php
// Agent code knows about monitoring
$agent->run($task);
$metrics->record($agent->getStats());
```

**✅ Do:**
```php
// Monitoring via events
$dispatcher->listen(AgentCompletedEvent::class, [$metrics, 'record']);
$agent->run($task);  // Events dispatched automatically
```

### 4. Create Templates

**❌ Don't:**
```php
// Repeated configuration
$agent1 = $factory->create('react', ['name' => 'a1', 'model' => 'opus', ...]);
$agent2 = $factory->create('react', ['name' => 'a2', 'model' => 'opus', ...]);
```

**✅ Do:**
```php
// Reusable template
$template = AgentTemplates::production();
$agent1 = $factory->create('react', [...$template->toArray(), 'name' => 'a1']);
$agent2 = $factory->create('react', [...$template->toArray(), 'name' => 'a2']);
```

---

## 🚀 Congratulations!

You've completed the Design Patterns tutorial! You now know how to:

✅ Use Factory Pattern for consistent agent creation  
✅ Apply Builder Pattern for type-safe configuration  
✅ Implement Observer Pattern for event-driven monitoring  
✅ Use Strategy Pattern for flexible response parsing  
✅ Apply Template Method for structured prompts  
✅ Combine patterns for production-quality code

## 🎓 Your Agent Journey

```
✅ Tutorial 0: Concepts
✅ Tutorial 1: First Agent  
✅ Tutorial 2: ReAct Loop
✅ Tutorial 3: Multi-Tool
✅ Tutorial 4: Production
✅ Tutorial 5: Advanced Patterns
✅ Tutorial 6: Design Patterns

→ You're now a production AI agent expert!
```

## 📚 Further Reading

- [Design Patterns Guide](../../DesignPatterns.md) - Comprehensive pattern reference
- [Factory Pattern](../../Factory.md) - Factory documentation
- [Builder Pattern](../../Builder.md) - Builder documentation
- [Event System](../../Events.md) - Observer pattern documentation
- [Best Practices](../../BestPractices.md) - Production patterns
- [Complete Demo](../../../examples/design_patterns_demo.php) - Working example

## 💡 Key Takeaways

1. **Factory centralizes creation** - Consistency and testability
2. **Builder ensures type safety** - IDE support and validation
3. **Events decouple monitoring** - Extensibility and flexibility
4. **Strategy enables flexibility** - Handle variable formats
5. **Templates reduce boilerplate** - DRY principle
6. **Combine patterns** - Greater than the sum of parts

## 🎯 Real-World Application

Use these patterns in your next project:

1. **Start with Factory** - Always create agents via factory
2. **Add Builder** - For complex configurations
3. **Add Events** - When you need monitoring
4. **Add Strategy** - When dealing with variable formats
5. **Create Templates** - As patterns emerge

### Example: Production Service

```php
class AgentService
{
    public function __construct(
        private AgentFactory $factory,
        private EventDispatcher $dispatcher,
        private MetricsCollector $metrics
    ) {
        // Register event handlers
        $this->dispatcher->listen(
            AgentCompletedEvent::class,
            [$this->metrics, 'onAgentCompleted']
        );
    }
    
    public function createSupportAgent(): Agent
    {
        $config = AgentTemplates::customerSupport();
        return $this->factory->createDialogAgent($config->toArray());
    }
    
    public function createAnalystAgent(): Agent
    {
        $config = AgentTemplates::dataAnalyst();
        return $this->factory->createReactAgent($config->toArray());
    }
    
    public function getMetrics(): array
    {
        return $this->metrics->getMetrics();
    }
}

// Usage
$service = new AgentService($factory, $dispatcher, $metrics);
$agent = $service->createSupportAgent();
```

---

**You're now ready to build world-class AI agents!** 🚀

Share your projects, ask questions, and contribute back to the community!

---

*Last Updated: December 2024*  
*Framework Version: 2.0+*

