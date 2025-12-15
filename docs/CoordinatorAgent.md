# CoordinatorAgent Documentation

## Overview

The `CoordinatorAgent` is an intelligent task coordinator that manages multiple specialized agents and delegates tasks based on their capabilities and current workload. It provides automatic agent selection, load balancing, and performance tracking for complex multi-agent systems.

## Features

- 🎯 **Intelligent Task Delegation**: Automatically analyzes tasks and routes them to the most capable agent
- ⚖️ **Load Balancing**: Distributes work evenly across agents with similar capabilities
- 📊 **Performance Tracking**: Monitors agent performance, success rates, and response times
- 🔍 **Capability Matching**: Matches task requirements to agent capabilities using AI analysis
- 📈 **Workload Monitoring**: Tracks task distribution across all registered agents
- 🔄 **Fallback Mechanism**: Uses keyword extraction when AI analysis fails

## Installation

The CoordinatorAgent is included in the `claude-php-agent` package:

```bash
composer require claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\CoordinatorAgent;
use ClaudeAgents\Agents\WorkerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$coordinator = new CoordinatorAgent($client);

// Create specialized agents
$coderAgent = new WorkerAgent($client, [
    'name' => 'coder',
    'specialty' => 'software development and coding',
    'system' => 'You are an expert software developer.',
]);

$testerAgent = new WorkerAgent($client, [
    'name' => 'tester',
    'specialty' => 'testing and quality assurance',
    'system' => 'You are a QA expert.',
]);

// Register agents with their capabilities
$coordinator->registerAgent('coder', $coderAgent, ['coding', 'implementation']);
$coordinator->registerAgent('tester', $testerAgent, ['testing', 'qa']);

// Delegate a task
$result = $coordinator->run('Write unit tests for authentication');

if ($result->isSuccess()) {
    echo "Task delegated to: " . $result->getMetadata()['delegated_to'] . "\n";
    echo "Result: " . $result->getAnswer() . "\n";
}
```

## Configuration

The CoordinatorAgent accepts configuration options in its constructor:

```php
$coordinator = new CoordinatorAgent($client, [
    'name' => 'my_coordinator',      // Coordinator name
    'model' => 'claude-sonnet-4-5',   // Model for task analysis
    'max_tokens' => 256,              // Max tokens for analysis
    'logger' => $logger,              // PSR-3 logger instance
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'coordinator_agent'` | Unique name for the coordinator |
| `model` | string | `'claude-sonnet-4-5'` | Model used for task requirement analysis |
| `max_tokens` | int | `256` | Maximum tokens for requirement analysis |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

## Registering Agents

### Single Agent Registration

```php
$coordinator->registerAgent(
    'developer',           // Unique agent ID
    $developerAgent,       // AgentInterface instance
    ['coding', 'testing']  // Array of capabilities
);
```

### Multiple Agent Registration

```php
// Backend developer
$coordinator->registerAgent('backend', $backendAgent, [
    'backend', 'api', 'database', 'server'
]);

// Frontend developer
$coordinator->registerAgent('frontend', $frontendAgent, [
    'frontend', 'ui', 'ux', 'react'
]);

// DevOps engineer
$coordinator->registerAgent('devops', $devopsAgent, [
    'devops', 'deployment', 'infrastructure', 'docker'
]);
```

## Agent Selection Process

The coordinator uses a multi-step process to select the best agent for a task:

### 1. Requirement Analysis

The coordinator analyzes the task using Claude AI to determine required capabilities:

```php
// Task: "Write unit tests for the authentication module"
// Analyzed requirements: ["testing", "quality assurance", "coding"]
```

### 2. Capability Matching

Agents are scored based on how many required capabilities they possess:

```php
// Agents:
// - coder: ['coding', 'implementation'] → Match score: 1
// - tester: ['testing', 'qa'] → Match score: 1
// - writer: ['documentation', 'writing'] → Match score: 0
```

### 3. Load Balancing

When multiple agents have the same match score, the coordinator selects the one with the lowest current workload:

```php
// Both agents can handle the task:
// - tester: Match score: 1, Workload: 3 tasks
// - coder: Match score: 1, Workload: 1 task
// 
// Selected: coder (lower workload)
```

## Working with Results

### Success Result

```php
$result = $coordinator->run('Design a RESTful API');

if ($result->isSuccess()) {
    // Get the answer
    $answer = $result->getAnswer();
    
    // Get metadata
    $metadata = $result->getMetadata();
    
    echo "Delegated to: {$metadata['delegated_to']}\n";
    echo "Requirements: " . implode(', ', $metadata['requirements']) . "\n";
    echo "Duration: {$metadata['duration']}s\n";
    
    // Get performance info
    $perf = $metadata['agent_performance'];
    echo "Agent success rate: {$perf['successful_tasks']}/{$perf['total_tasks']}\n";
}
```

### Failure Result

```php
$result = $coordinator->run('Some task');

if (!$result->isSuccess()) {
    echo "Error: " . $result->getError() . "\n";
    
    // Check what went wrong
    $metadata = $result->getMetadata();
    echo "Requirements: " . implode(', ', $metadata['requirements']) . "\n";
    echo "Available agents: " . implode(', ', $metadata['available_agents']) . "\n";
}
```

## Monitoring and Analytics

### Workload Distribution

Track how tasks are distributed across agents:

```php
$workload = $coordinator->getWorkload();

foreach ($workload as $agentId => $taskCount) {
    echo "{$agentId}: {$taskCount} tasks\n";
}

// Output:
// coder: 5 tasks
// tester: 3 tasks
// writer: 2 tasks
```

### Performance Metrics

Monitor agent performance over time:

```php
$performance = $coordinator->getPerformance();

foreach ($performance as $agentId => $metrics) {
    $successRate = ($metrics['successful_tasks'] / $metrics['total_tasks']) * 100;
    
    echo "{$agentId}:\n";
    echo "  Total tasks: {$metrics['total_tasks']}\n";
    echo "  Success rate: " . round($successRate, 1) . "%\n";
    echo "  Avg duration: {$metrics['average_duration']}s\n";
}
```

### Visualizing Workload

```php
$workload = $coordinator->getWorkload();
$maxLoad = max($workload);

foreach ($workload as $agent => $load) {
    $percentage = ($load / $maxLoad) * 100;
    $bar = str_repeat('█', (int)($percentage / 3.33));
    echo sprintf("%-15s [%-30s] %d\n", $agent, $bar, $load);
}

// Output:
// coder          [██████████████████████████████] 5
// tester         [████████████████████] 3
// writer         [████████████] 2
```

## Advanced Patterns

### Custom Agent Implementation

Create agents with custom behavior:

```php
class SpecializedAgent implements AgentInterface
{
    private array $metrics = [];
    
    public function run(string $task): AgentResult
    {
        $start = microtime(true);
        
        // Your custom logic here
        $result = $this->processTask($task);
        
        // Track metrics
        $this->metrics[] = [
            'task' => $task,
            'duration' => microtime(true) - $start,
            'timestamp' => time(),
        ];
        
        return $result;
    }
    
    public function getName(): string
    {
        return 'specialized_agent';
    }
    
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}

// Register with coordinator
$coordinator->registerAgent('specialist', new SpecializedAgent(), [
    'specialized_task', 'custom_processing'
]);
```

### Multi-Stage Workflows

Coordinate complex workflows across multiple agents:

```php
class WorkflowCoordinator
{
    public function __construct(private CoordinatorAgent $coordinator)
    {
    }
    
    public function executeWorkflow(array $stages): array
    {
        $results = [];
        
        foreach ($stages as $stageName => $tasks) {
            echo "Executing stage: {$stageName}\n";
            $stageResults = [];
            
            foreach ($tasks as $task) {
                $result = $this->coordinator->run($task);
                $stageResults[] = [
                    'task' => $task,
                    'result' => $result,
                    'agent' => $result->getMetadata()['delegated_to'] ?? null,
                ];
            }
            
            $results[$stageName] = $stageResults;
        }
        
        return $results;
    }
}

// Usage
$workflow = new WorkflowCoordinator($coordinator);

$results = $workflow->executeWorkflow([
    'design' => [
        'Design the database schema',
        'Design the API endpoints',
    ],
    'implementation' => [
        'Implement user authentication',
        'Implement data validation',
    ],
    'testing' => [
        'Write integration tests',
        'Perform security audit',
    ],
]);
```

### Dynamic Agent Registration

Add agents dynamically based on runtime conditions:

```php
class DynamicCoordinator
{
    private CoordinatorAgent $coordinator;
    private array $agentPool = [];
    
    public function __construct(ClaudePhp $client)
    {
        $this->coordinator = new CoordinatorAgent($client);
    }
    
    public function ensureCapability(string $capability): void
    {
        // Check if we have an agent with this capability
        $hasCapability = false;
        
        foreach ($this->coordinator->getAgentIds() as $id) {
            if (in_array($capability, $this->coordinator->getAgentCapabilities($id))) {
                $hasCapability = true;
                break;
            }
        }
        
        // If not, create and register one
        if (!$hasCapability) {
            $agent = $this->createAgentForCapability($capability);
            $this->coordinator->registerAgent(
                "dynamic_{$capability}",
                $agent,
                [$capability]
            );
        }
    }
    
    private function createAgentForCapability(string $capability): AgentInterface
    {
        // Create specialized agent based on capability
        return new WorkerAgent($this->client, [
            'name' => $capability,
            'specialty' => $capability,
            'system' => "You are an expert in {$capability}.",
        ]);
    }
}
```

## Best Practices

### 1. Define Clear Capabilities

Use specific, descriptive capabilities:

```php
// Good
$coordinator->registerAgent('backend', $agent, [
    'rest_api', 'graphql', 'database_design', 'orm', 'caching'
]);

// Less effective
$coordinator->registerAgent('backend', $agent, [
    'backend', 'coding'
]);
```

### 2. Balance Agent Specialization

Don't make agents too specialized or too general:

```php
// Too specialized - may not get enough work
$coordinator->registerAgent('redis_expert', $agent, ['redis']);

// Better - broader but still specialized
$coordinator->registerAgent('cache_expert', $agent, [
    'caching', 'redis', 'memcached', 'cache_strategy'
]);

// Too general - defeats purpose of coordination
$coordinator->registerAgent('general', $agent, [
    'everything', 'anything', 'all_tasks'
]);
```

### 3. Monitor Performance Regularly

```php
// Check performance periodically
$performance = $coordinator->getPerformance();

foreach ($performance as $agentId => $metrics) {
    $successRate = ($metrics['successful_tasks'] / $metrics['total_tasks']) * 100;
    
    if ($successRate < 80) {
        // Agent may need adjustment
        error_log("Warning: {$agentId} has low success rate: {$successRate}%");
    }
    
    if ($metrics['average_duration'] > 30) {
        // Agent may be overloaded or slow
        error_log("Warning: {$agentId} has high average duration: {$metrics['average_duration']}s");
    }
}
```

### 4. Handle Registration Errors

```php
try {
    $coordinator->registerAgent('agent_id', $agent, $capabilities);
} catch (\Exception $e) {
    error_log("Failed to register agent: " . $e->getMessage());
    // Handle error appropriately
}
```

### 5. Provide Fallback Options

```php
$result = $coordinator->run($task);

if (!$result->isSuccess()) {
    // Try with a general-purpose agent as fallback
    $fallbackAgent = new WorkerAgent($client, [
        'name' => 'fallback',
        'specialty' => 'general problem solving',
    ]);
    
    $result = $fallbackAgent->run($task);
}
```

## API Reference

### CoordinatorAgent Methods

#### `__construct(ClaudePhp $client, array $options = [])`
Create a new CoordinatorAgent instance.

#### `registerAgent(string $id, AgentInterface $agent, array $capabilities = []): void`
Register an agent with the coordinator.

**Parameters:**
- `$id`: Unique identifier for the agent
- `$agent`: The agent instance implementing AgentInterface
- `$capabilities`: Array of capability strings

#### `run(string $task): AgentResult`
Delegate a task to the most suitable agent.

**Returns:** `AgentResult` with metadata including:
- `delegated_to`: ID of the selected agent
- `requirements`: Analyzed task requirements
- `workload`: Current workload distribution
- `duration`: Time taken to process
- `agent_performance`: Performance metrics for the selected agent

#### `getName(): string`
Get the coordinator name.

#### `getAgentIds(): array`
Get all registered agent IDs.

#### `getAgentCapabilities(string $id): array`
Get the capabilities for a specific agent.

#### `getWorkload(): array`
Get the current workload distribution across all agents.

**Returns:** Array mapping agent IDs to task counts.

#### `getPerformance(): array`
Get performance metrics for all agents.

**Returns:** Array mapping agent IDs to performance metrics:
- `total_tasks`: Total number of tasks handled
- `successful_tasks`: Number of successful tasks
- `average_duration`: Average task duration in seconds

## Integration Examples

### Integration with Laravel

```php
// In a service provider
use ClaudeAgents\Agents\CoordinatorAgent;
use ClaudePhp\ClaudePhp;

public function register()
{
    $this->app->singleton(CoordinatorAgent::class, function ($app) {
        $client = new ClaudePhp(config('services.anthropic.key'));
        $coordinator = new CoordinatorAgent($client, [
            'logger' => Log::channel('coordinator')
        ]);
        
        // Register your agents
        $this->registerAgents($coordinator);
        
        return $coordinator;
    });
}

// In your controller or service
public function handle(Request $request)
{
    $coordinator = app(CoordinatorAgent::class);
    $result = $coordinator->run($request->input('task'));
    
    return response()->json([
        'success' => $result->isSuccess(),
        'answer' => $result->getAnswer(),
        'metadata' => $result->getMetadata(),
    ]);
}
```

### Integration with Symfony

```php
// services.yaml
services:
    ClaudeAgents\Agents\CoordinatorAgent:
        arguments:
            $client: '@ClaudePhp\ClaudePhp'
            $options:
                name: 'app_coordinator'
                logger: '@logger'

// In your controller
public function processTask(CoordinatorAgent $coordinator, Request $request): JsonResponse
{
    $task = $request->request->get('task');
    $result = $coordinator->run($task);
    
    return $this->json([
        'success' => $result->isSuccess(),
        'data' => $result->getAnswer(),
    ]);
}
```

## Troubleshooting

### No Suitable Agent Found

**Problem:** Task fails with "No suitable agent found"

**Solutions:**
1. Check agent capabilities match task requirements:
   ```php
   $capabilities = $coordinator->getAgentCapabilities('agent_id');
   var_dump($capabilities);
   ```

2. Register a general-purpose fallback agent:
   ```php
   $fallback = new WorkerAgent($client, [
       'name' => 'fallback',
       'specialty' => 'general problem solving',
   ]);
   $coordinator->registerAgent('fallback', $fallback, ['general']);
   ```

3. Review the analyzed requirements:
   ```php
   $result = $coordinator->run($task);
   $requirements = $result->getMetadata()['requirements'] ?? [];
   echo "Task required: " . implode(', ', $requirements) . "\n";
   ```

### Poor Load Balancing

**Problem:** One agent getting all the work

**Possible causes:**
1. Only one agent has the required capabilities
2. Capabilities are too specific

**Solutions:**
1. Register multiple agents with overlapping capabilities
2. Use broader capability definitions
3. Check workload distribution regularly

### Slow Performance

**Problem:** Task delegation takes too long

**Solutions:**
1. Use simpler models for requirement analysis:
   ```php
   new CoordinatorAgent($client, ['model' => 'claude-haiku-3-5']);
   ```

2. Reduce max_tokens for analysis:
   ```php
   new CoordinatorAgent($client, ['max_tokens' => 128]);
   ```

3. Pre-analyze common task patterns and cache capabilities

## See Also

- [CoordinatorAgent Tutorial](tutorials/CoordinatorAgent_Tutorial.md)
- [Examples](../examples/coordinator_agent.php)
- [Advanced Examples](../examples/advanced_coordinator.php)
- [Agent Selection Guide](agent-selection-guide.md)

