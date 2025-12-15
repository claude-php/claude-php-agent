# Event System

Decoupled observability and monitoring using the Observer Pattern with lifecycle events.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Event Types](#event-types)
- [Subscribing to Events](#subscribing-to-events)
- [Event Handlers](#event-handlers)
- [Use Cases](#use-cases)
- [Best Practices](#best-practices)
- [Examples](#examples)

## Overview

The **Event System** implements the Observer Pattern, allowing you to monitor agent execution without coupling monitoring logic to agent code. When significant events occur (agent start, completion, failure), the agent dispatches events that registered listeners can handle.

### Benefits

- ✅ **Decoupling**: Agents don't know about observers
- ✅ **Extensibility**: Add new listeners without modifying agents
- ✅ **Multiple Handlers**: Many listeners can respond to the same event
- ✅ **Testability**: Mock event handlers for testing
- ✅ **Flexibility**: Subscribe/unsubscribe at runtime

### Problem Without Events

```php
// ❌ Tight coupling: Agent knows about monitoring
class MyAgent {
    public function run($task) {
        $this->logger->info("Agent started");
        $this->metrics->recordStart();
        $this->alertSystem->notifyStart();
        
        // ... agent logic ...
        
        $this->logger->info("Agent completed");
        $this->metrics->recordComplete();
        $this->alertSystem->notifyComplete();
    }
}

// Hard to add new monitoring
// Hard to test
// Violates Single Responsibility Principle
```

### Solution With Events

```php
// ✅ Decoupled: Agent just dispatches events
class MyAgent {
    public function run($task) {
        $this->dispatcher->dispatch(new AgentStartedEvent('my_agent', $task));
        
        // ... agent logic ...
        
        $this->dispatcher->dispatch(new AgentCompletedEvent('my_agent', ...));
    }
}

// Monitoring is separate
$dispatcher->listen(AgentStartedEvent::class, function($event) {
    $logger->info("Agent started");
    $metrics->recordStart();
    $alerts->notifyStart();
});

// Easy to add/remove monitoring
// Easy to test
// Follows SOLID principles
```

## Quick Start

### Basic Usage

```php
use ClaudeAgents\Events\EventDispatcher;
use ClaudeAgents\Events\{AgentStartedEvent, AgentCompletedEvent, AgentFailedEvent};

// Create dispatcher
$dispatcher = new EventDispatcher();

// Subscribe to events
$dispatcher->listen(AgentStartedEvent::class, function($event) {
    echo "Agent '{$event->getAgentName()}' started\n";
    echo "Task: {$event->getTask()}\n";
});

$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "Agent completed in " . round($event->getDuration(), 2) . "s\n";
    echo "Iterations: {$event->getIterations()}\n";
});

$dispatcher->listen(AgentFailedEvent::class, function($event) {
    error_log("Agent failed: " . $event->getError());
});

// Dispatch events (usually done by the agent automatically)
$dispatcher->dispatch(new AgentStartedEvent('my_agent', 'Process data'));
// ... agent execution ...
$dispatcher->dispatch(new AgentCompletedEvent(
    'my_agent',
    duration: 2.5,
    iterations: 5,
    result: 'Success'
));
```

### With AgentFactory

The `AgentFactory` can inject an `EventDispatcher` into all created agents:

```php
$dispatcher = new EventDispatcher();

// Set up listeners
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "Agent completed!\n";
});

// Create factory with dispatcher
$factory = new AgentFactory($client, $logger, $dispatcher);

// All agents created will dispatch events
$agent = $factory->create('react', ['name' => 'my_agent']);

// Events are dispatched automatically during agent.run()
$result = $agent->run('Do something');
```

## Event Types

### AgentStartedEvent

Dispatched when an agent begins execution.

**Properties:**
```php
$event->getAgentName(): string      // Agent identifier
$event->getTask(): string           // Task description
$event->getTimestamp(): float       // Unix timestamp with microseconds
```

**Example:**
```php
$event = new AgentStartedEvent('research_agent', 'Find papers on ML');

echo $event->getAgentName();  // 'research_agent'
echo $event->getTask();       // 'Find papers on ML'
echo $event->getTimestamp();  // 1702994400.123456
```

### AgentCompletedEvent

Dispatched when an agent finishes successfully.

**Properties:**
```php
$event->getAgentName(): string      // Agent identifier
$event->getDuration(): float        // Execution time in seconds
$event->getIterations(): int        // Number of reasoning loops
$event->getResult(): mixed          // Agent result (optional)
```

**Example:**
```php
$event = new AgentCompletedEvent(
    'research_agent',
    duration: 5.234,
    iterations: 7,
    result: 'Found 10 papers'
);

echo $event->getAgentName();  // 'research_agent'
echo $event->getDuration();   // 5.234
echo $event->getIterations(); // 7
echo $event->getResult();     // 'Found 10 papers'
```

### AgentFailedEvent

Dispatched when an agent encounters an error.

**Properties:**
```php
$event->getAgentName(): string      // Agent identifier
$event->getError(): string          // Error message
$event->getException(): ?\Throwable // Exception instance (if available)
$event->getDuration(): float        // Time before failure
```

**Example:**
```php
$event = new AgentFailedEvent(
    'research_agent',
    error: 'API timeout',
    exception: $exception,
    duration: 2.5
);

echo $event->getAgentName();  // 'research_agent'
echo $event->getError();      // 'API timeout'
$exception = $event->getException();  // Exception|null
echo $event->getDuration();   // 2.5
```

## Subscribing to Events

### Closure Listeners

```php
$dispatcher->listen(AgentStartedEvent::class, function(AgentStartedEvent $event) {
    echo "Agent started: {$event->getAgentName()}\n";
});
```

### Callable Listeners

```php
function handleAgentStarted(AgentStartedEvent $event) {
    echo "Agent started: {$event->getAgentName()}\n";
}

$dispatcher->listen(AgentStartedEvent::class, 'handleAgentStarted');
```

### Method Listeners

```php
class EventLogger {
    public function onAgentStarted(AgentStartedEvent $event): void {
        file_put_contents('agent.log', "Started: {$event->getAgentName()}\n", FILE_APPEND);
    }
}

$logger = new EventLogger();
$dispatcher->listen(AgentStartedEvent::class, [$logger, 'onAgentStarted']);
```

### Multiple Listeners

You can attach multiple listeners to the same event:

```php
// Listener 1: Console output
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "Completed!\n";
});

// Listener 2: File logging
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    file_put_contents('log.txt', "Completed\n", FILE_APPEND);
});

// Listener 3: Metrics
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    $metrics->record($event->getDuration());
});

// All three are called when event is dispatched
```

### Unsubscribing

```php
$listener = function($event) {
    echo "Agent started\n";
};

// Subscribe
$dispatcher->listen(AgentStartedEvent::class, $listener);

// Unsubscribe
$dispatcher->forget(AgentStartedEvent::class, $listener);
```

## Event Handlers

### Metrics Collection

```php
class MetricsCollector {
    private array $metrics = [
        'total_runs' => 0,
        'successful_runs' => 0,
        'failed_runs' => 0,
        'total_duration' => 0.0,
    ];
    
    public function onAgentStarted(AgentStartedEvent $event): void {
        // Track start times if needed
    }
    
    public function onAgentCompleted(AgentCompletedEvent $event): void {
        $this->metrics['total_runs']++;
        $this->metrics['successful_runs']++;
        $this->metrics['total_duration'] += $event->getDuration();
    }
    
    public function onAgentFailed(AgentFailedEvent $event): void {
        $this->metrics['total_runs']++;
        $this->metrics['failed_runs']++;
    }
    
    public function getMetrics(): array {
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

// Register all handlers
$metrics = new MetricsCollector();
$dispatcher->listen(AgentStartedEvent::class, [$metrics, 'onAgentStarted']);
$dispatcher->listen(AgentCompletedEvent::class, [$metrics, 'onAgentCompleted']);
$dispatcher->listen(AgentFailedEvent::class, [$metrics, 'onAgentFailed']);
```

### Alert System

```php
class AlertSystem {
    private array $failureCount = [];
    private int $threshold = 3;
    
    public function onAgentFailed(AgentFailedEvent $event): void {
        $agent = $event->getAgentName();
        $this->failureCount[$agent] = ($this->failureCount[$agent] ?? 0) + 1;
        
        if ($this->failureCount[$agent] >= $this->threshold) {
            $this->sendAlert($agent, $event->getError());
        }
    }
    
    public function onAgentCompleted(AgentCompletedEvent $event): void {
        // Reset failure count on success
        $agent = $event->getAgentName();
        if (isset($this->failureCount[$agent])) {
            unset($this->failureCount[$agent]);
        }
        
        // Alert on slow performance
        if ($event->getDuration() > 10.0) {
            $this->sendPerformanceAlert($agent, $event->getDuration());
        }
    }
    
    private function sendAlert(string $agent, string $error): void {
        // Send to Slack, email, PagerDuty, etc.
        error_log("🚨 ALERT: Agent '{$agent}' has failed {$this->failureCount[$agent]} times! Last error: {$error}");
    }
    
    private function sendPerformanceAlert(string $agent, float $duration): void {
        error_log("⚠️ PERFORMANCE: Agent '{$agent}' took {$duration}s (threshold: 10s)");
    }
}

$alerts = new AlertSystem();
$dispatcher->listen(AgentFailedEvent::class, [$alerts, 'onAgentFailed']);
$dispatcher->listen(AgentCompletedEvent::class, [$alerts, 'onAgentCompleted']);
```

### Audit Logging

```php
class AuditLogger {
    private string $logFile;
    
    public function __construct(string $logFile) {
        $this->logFile = $logFile;
    }
    
    public function onAgentStarted(AgentStartedEvent $event): void {
        $this->log('STARTED', [
            'agent' => $event->getAgentName(),
            'task' => $event->getTask(),
            'timestamp' => date('c', (int)$event->getTimestamp()),
        ]);
    }
    
    public function onAgentCompleted(AgentCompletedEvent $event): void {
        $this->log('COMPLETED', [
            'agent' => $event->getAgentName(),
            'duration' => round($event->getDuration(), 3),
            'iterations' => $event->getIterations(),
        ]);
    }
    
    public function onAgentFailed(AgentFailedEvent $event): void {
        $this->log('FAILED', [
            'agent' => $event->getAgentName(),
            'error' => $event->getError(),
            'duration' => round($event->getDuration(), 3),
        ]);
    }
    
    private function log(string $type, array $data): void {
        file_put_contents(
            $this->logFile,
            json_encode(['type' => $type, 'data' => $data, 'time' => time()]) . "\n",
            FILE_APPEND
        );
    }
}

$audit = new AuditLogger('audit.log');
$dispatcher->listen(AgentStartedEvent::class, [$audit, 'onAgentStarted']);
$dispatcher->listen(AgentCompletedEvent::class, [$audit, 'onAgentCompleted']);
$dispatcher->listen(AgentFailedEvent::class, [$audit, 'onAgentFailed']);
```

## Use Cases

### 1. Performance Monitoring

```php
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    if ($event->getDuration() > 5.0) {
        error_log("Slow agent: {$event->getAgentName()} took {$event->getDuration()}s");
    }
    
    if ($event->getIterations() > 15) {
        error_log("High iteration count: {$event->getAgentName()} used {$event->getIterations()} iterations");
    }
});
```

### 2. Cost Tracking

```php
class CostTracker {
    private float $totalCost = 0.0;
    
    public function onAgentCompleted(AgentCompletedEvent $event): void {
        // Estimate cost based on iterations
        $estimatedCost = $event->getIterations() * 0.001;  // Example
        $this->totalCost += $estimatedCost;
        
        if ($this->totalCost > 10.0) {
            throw new \RuntimeException("Budget exceeded!");
        }
    }
}
```

### 3. Real-Time Dashboard

```php
$dispatcher->listen(AgentStartedEvent::class, function($event) {
    $redis->hset('agent_status', $event->getAgentName(), 'running');
    $redis->publish('agent_events', json_encode([
        'type' => 'started',
        'agent' => $event->getAgentName(),
    ]));
});

$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    $redis->hset('agent_status', $event->getAgentName(), 'completed');
    $redis->publish('agent_events', json_encode([
        'type' => 'completed',
        'agent' => $event->getAgentName(),
        'duration' => $event->getDuration(),
    ]));
});
```

### 4. Testing and Debugging

```php
// In tests: capture events for assertions
$capturedEvents = [];

$dispatcher->listen(AgentCompletedEvent::class, function($event) use (&$capturedEvents) {
    $capturedEvents[] = $event;
});

// Run test
$agent->run('Test task');

// Assert
$this->assertCount(1, $capturedEvents);
$this->assertEquals('test_agent', $capturedEvents[0]->getAgentName());
```

### 5. A/B Testing

```php
$dispatcher->listen(AgentCompletedEvent::class, function($event) use ($abTesting) {
    $abTesting->recordResult([
        'variant' => $event->getAgentName(),
        'duration' => $event->getDuration(),
        'iterations' => $event->getIterations(),
        'success' => true,
    ]);
});
```

## Best Practices

### 1. Keep Handlers Fast

Event handlers should be quick. For heavy work, queue it.

**❌ Don't:**
```php
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    // Slow operation blocks agent
    sendEmailReport($event);  // Takes 2 seconds
    updateDatabase($event);    // Takes 500ms
});
```

**✅ Do:**
```php
$dispatcher->listen(AgentCompletedEvent::class, function($event) use ($queue) {
    // Quick: just queue it
    $queue->push('send-email', $event->toArray());
    $queue->push('update-db', $event->toArray());
});
```

### 2. Handle Exceptions

Don't let listener exceptions crash the agent.

**❌ Don't:**
```php
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    // If this fails, agent execution fails
    sendToExternalService($event);
});
```

**✅ Do:**
```php
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    try {
        sendToExternalService($event);
    } catch (\Exception $e) {
        error_log("Failed to send event: " . $e->getMessage());
    }
});
```

### 3. Use Classes for Complex Logic

For non-trivial handlers, use classes.

**❌ Don't:**
```php
$dispatcher->listen(AgentCompletedEvent::class, function($event) use ($metrics, $cost, $logger, $alerts) {
    // Complex logic in closure
    $metrics->record($event->getDuration());
    $cost->add($event->getIterations() * 0.001);
    $logger->info("Completed", ['agent' => $event->getAgentName()]);
    if ($event->getDuration() > 10) {
        $alerts->send("Slow agent");
    }
});
```

**✅ Do:**
```php
class ComprehensiveMonitor {
    public function __construct(
        private MetricsCollector $metrics,
        private CostTracker $cost,
        private Logger $logger,
        private AlertSystem $alerts
    ) {}
    
    public function onAgentCompleted(AgentCompletedEvent $event): void {
        $this->metrics->record($event->getDuration());
        $this->cost->add($event->getIterations() * 0.001);
        $this->logger->info("Completed", ['agent' => $event->getAgentName()]);
        
        if ($event->getDuration() > 10) {
            $this->alerts->send("Slow agent: {$event->getAgentName()}");
        }
    }
}

$monitor = new ComprehensiveMonitor($metrics, $cost, $logger, $alerts);
$dispatcher->listen(AgentCompletedEvent::class, [$monitor, 'onAgentCompleted']);
```

### 4. Unsubscribe When Done

```php
$listener = function($event) { /* ... */ };
$dispatcher->listen(AgentStartedEvent::class, $listener);

// When no longer needed
$dispatcher->forget(AgentStartedEvent::class, $listener);
```

### 5. Type Hint Event Parameters

```php
// ✅ Good: Type hinted
$dispatcher->listen(AgentCompletedEvent::class, function(AgentCompletedEvent $event) {
    // IDE autocomplete works
    $event->getDuration();
});

// ❌ Less good: No type hint
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    // No autocomplete
    $event->getDuration();
});
```

### 6. Use Dependency Injection

```php
class MyService {
    public function __construct(
        private EventDispatcher $dispatcher,
        private MetricsCollector $metrics
    ) {
        $this->dispatcher->listen(
            AgentCompletedEvent::class,
            [$this->metrics, 'onAgentCompleted']
        );
    }
}

// Easy to test
$mockDispatcher = $this->createMock(EventDispatcher::class);
$service = new MyService($mockDispatcher, $metrics);
```

## Examples

### Complete Monitoring System

```php
$dispatcher = new EventDispatcher();

// Set up comprehensive monitoring
$metrics = new MetricsCollector();
$alerts = new AlertSystem();
$audit = new AuditLogger('audit.log');
$cost = new CostTracker();

// Register all handlers
$dispatcher->listen(AgentStartedEvent::class, [$audit, 'onAgentStarted']);
$dispatcher->listen(AgentCompletedEvent::class, [$metrics, 'onAgentCompleted']);
$dispatcher->listen(AgentCompletedEvent::class, [$alerts, 'onAgentCompleted']);
$dispatcher->listen(AgentCompletedEvent::class, [$audit, 'onAgentCompleted']);
$dispatcher->listen(AgentCompletedEvent::class, [$cost, 'onAgentCompleted']);
$dispatcher->listen(AgentFailedEvent::class, [$metrics, 'onAgentFailed']);
$dispatcher->listen(AgentFailedEvent::class, [$alerts, 'onAgentFailed']);
$dispatcher->listen(AgentFailedEvent::class, [$audit, 'onAgentFailed']);

// Use with factory
$factory = new AgentFactory($client, $logger, $dispatcher);

// All agents are monitored
$agent = $factory->create('react');
$result = $agent->run('Do something');
```

## See Also

- [Observer Pattern](DesignPatterns.md#observer-pattern) - Design pattern details
- [Observability](Observability.md) - Event system in observability context
- [Best Practices](BestPractices.md) - Event-driven monitoring
- [Examples](../examples/event_system_example.php) - Working code

## API Reference

### EventDispatcher

```php
class EventDispatcher
{
    // Subscribe to an event
    public function listen(string $eventClass, callable $listener): void;
    
    // Unsubscribe from an event
    public function forget(string $eventClass, callable $listener): void;
    
    // Dispatch an event to all listeners
    public function dispatch(object $event): void;
    
    // Check if event has listeners
    public function hasListeners(string $eventClass): bool;
    
    // Get all listeners for an event
    public function getListeners(string $eventClass): array;
}
```

### Event Classes

```php
class AgentStartedEvent
{
    public function __construct(
        string $agentName,
        string $task,
        ?float $timestamp = null
    );
    
    public function getAgentName(): string;
    public function getTask(): string;
    public function getTimestamp(): float;
}

class AgentCompletedEvent
{
    public function __construct(
        string $agentName,
        float $duration,
        int $iterations,
        mixed $result = null
    );
    
    public function getAgentName(): string;
    public function getDuration(): float;
    public function getIterations(): int;
    public function getResult(): mixed;
}

class AgentFailedEvent
{
    public function __construct(
        string $agentName,
        string $error,
        ?\Throwable $exception = null,
        float $duration = 0.0
    );
    
    public function getAgentName(): string;
    public function getError(): string;
    public function getException(): ?\Throwable;
    public function getDuration(): float;
}
```

