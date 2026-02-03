# Streaming Flow Execution Tutorial

**Comprehensive 60-minute tutorial on real-time streaming flow execution**

Learn how to build real-time streaming applications with the Streaming Flow Execution system, inspired by Langflow's sophisticated event-driven architecture.

## Table of Contents

1. [Introduction](#introduction)
2. [Prerequisites](#prerequisites)
3. [Tutorial Series](#tutorial-series)
4. [Core Concepts](#core-concepts)
5. [Architecture Overview](#architecture-overview)
6. [Complete Examples](#complete-examples)
7. [Production Patterns](#production-patterns)
8. [Troubleshooting](#troubleshooting)

## Introduction

### What is Streaming Flow Execution?

The Streaming Flow Execution system provides real-time streaming capabilities for agent execution:

- **Token-by-Token Streaming**: See LLM responses as they're generated
- **Event-Driven Architecture**: Queue-based event management
- **Progress Tracking**: Know exactly what's happening at all times
- **Multiple Listeners**: Broadcast events to multiple consumers
- **SSE Support**: Native browser streaming with Server-Sent Events

### Why Use Streaming?

**Traditional execution:**
```php
$result = $agent->run($task);
echo $result->getOutput(); // Wait... then all at once
```

**Streaming execution:**
```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    echo $event['data']['token']; // Real-time, token-by-token
}
```

### Inspiration: Langflow

This system is inspired by [Langflow](https://github.com/langflow-ai/langflow)'s event-driven architecture, adapted from Python's async patterns to PHP using Generators.

| Python (Langflow) | PHP (This Framework) |
|-------------------|----------------------|
| `async/await` | `Generator/yield` |
| `asyncio.Queue` | `SplQueue` |
| Async subscribers | Observer + Iterator |

## Prerequisites

### Required Knowledge

- Basic PHP (variables, functions, classes)
- Understanding of generators (helpful but not required)
- Familiarity with claude-php-agent basics

### Setup

1. **PHP 8.1+** installed
2. **Composer dependencies** installed:
   ```bash
   cd /path/to/claude-php-agent
   composer install
   ```
3. **API Key** configured:
   ```bash
   export ANTHROPIC_API_KEY='your-api-key-here'
   ```

### Verify Setup

```bash
php -v  # Should show PHP 8.1+
echo $ANTHROPIC_API_KEY  # Should show your key
```

## Tutorial Series

Complete the tutorials in order. Each builds on the previous ones.

### Tutorial 1: Basic Streaming (10 minutes)

**File:** `examples/tutorials/streaming-flow/01-basic-streaming.php`

**Topics:**
- Setting up the streaming executor
- Streaming token-by-token responses
- Handling different event types
- Displaying real-time progress

**Run:**
```bash
php examples/tutorials/streaming-flow/01-basic-streaming.php
```

**What you'll see:**
```
Step 1: Setting up the streaming executor
------------------------------------------
✅ ServiceManager initialized
✅ Streaming services registered

Step 2: Getting the streaming executor
---------------------------------------
✅ Streaming executor ready

🚀 Flow started

[Token streaming appears here in real-time...]

🔧 Using tool: calculator
   Input: {"operation":"multiply","a":25,"b":4}
   ✅ Result: Result: 100

📊 Progress: 50.0%

✅ Flow completed
```

**Key Takeaways:**
- Streaming executor setup is simple with ServiceManager
- Events flow in real-time as they occur
- Different event types provide different information

---

### Tutorial 2: Progress Tracking (10 minutes)

**File:** `examples/tutorials/streaming-flow/02-progress-tracking.php`

**Topics:**
- Real-time progress bars
- Duration tracking
- Time remaining estimation
- Queue statistics monitoring

**Run:**
```bash
php examples/tutorials/streaming-flow/02-progress-tracking.php
```

**What you'll see:**
```
[========================================] 100.0%

📊 Progress: 3/3 iterations
⏱️  Duration: 2.5s
⏰ ETA: 0.0s

Queue Statistics:
  Events in queue: 0
  Max queue size: 100
  Queue utilization: 0.0%
  Dropped events: 0
```

**Key Takeaways:**
- Progress tracking provides user feedback
- Duration estimation helps set expectations
- Queue statistics help optimize performance

---

### Tutorial 3: Event Listeners (15 minutes)

**File:** `examples/tutorials/streaming-flow/03-event-listeners.php`

**Topics:**
- Multiple subscribers to events
- Custom event handlers
- Listener lifecycle management
- Event broadcasting

**Run:**
```bash
php examples/tutorials/streaming-flow/03-event-listeners.php
```

**What you'll see:**
```
✅ Listener 1: Token Counter (ID: listener-...)
✅ Listener 2: Performance Monitor (ID: listener-...)
✅ Listener 3: Error Tracker (ID: listener-...)
✅ Listener 4: Event Logger (ID: listener-...)

[All listeners receive events simultaneously]

Listener Results:
  Token Counter: 150 tokens
  Performance Monitor: 2 iterations, 1 tool
  Error Tracker: 0 errors
  Event Logger: 45 events
```

**Key Takeaways:**
- Multiple listeners can monitor the same execution
- Each listener can track different aspects
- Listener management (subscribe/unsubscribe) is flexible

---

### Tutorial 4: SSE Streaming (15 minutes)

**File:** `examples/tutorials/streaming-flow/04-sse-streaming.php`

**Topics:**
- Server-Sent Events endpoints
- Browser-based streaming
- Event formatting
- Connection management

**Run:**
```bash
php examples/tutorials/streaming-flow/04-sse-streaming.php serve
# Open http://localhost:8080 in browser
```

**What you'll see:**
- Interactive web interface
- Real-time streaming in browser
- Progress bars and event logs
- Connection status monitoring

**Key Takeaways:**
- SSE provides native browser streaming
- No WebSocket complexity needed
- Perfect for real-time web UIs

---

### Tutorial 5: Custom Events (15 minutes)

**File:** `examples/tutorials/streaming-flow/05-custom-events.php`

**Topics:**
- Defining custom event types
- Domain-specific events
- Custom event handlers
- Event aggregation

**Run:**
```bash
php examples/tutorials/streaming-flow/05-custom-events.php
```

**What you'll see:**
```
Custom event types defined:
   - TRADE_EXECUTED
   - PRICE_ALERT
   - RISK_WARNING
   - PORTFOLIO_UPDATE

💰 TRADE: AAPL 100.00 @ $175.50 = $17550.00
🔔 ALERT: TSLA at $245.75 (above target)
⚠️  RISK [MEDIUM]: Portfolio volatility increased
```

**Key Takeaways:**
- Extend FlowEvent for domain-specific needs
- Custom events enable specialized monitoring
- Event aggregation provides insights

---

### Tutorial 6: Error Handling (15 minutes)

**File:** `examples/tutorials/streaming-flow/06-error-handling.php`

**Topics:**
- Error handling in streaming
- Recovery strategies
- Error tracking and logging
- Graceful degradation

**Run:**
```bash
php examples/tutorials/streaming-flow/06-error-handling.php
```

**What you'll see:**
```
Test 1: Normal execution
▶️  Execution started...
✅ Tool completed: Result: 20
✅ Execution completed

Test 2: Error scenario (division by zero)
▶️  Execution started...
❌ Error caught in stream: Cannot divide by zero
✅ Error was properly caught and reported

Retry mechanism:
Attempt 1/3...
❌ Failed: Network timeout
   Retrying in 0.5s...
Attempt 2/3...
✅ Success on attempt 2
```

**Key Takeaways:**
- Always wrap streaming in try-catch
- Subscribe error listeners before execution
- Implement retry logic with exponential backoff
- Errors don't stop the stream

---

### Tutorial 7: Integration (20 minutes)

**File:** `examples/tutorials/streaming-flow/07-integration.php`

**Topics:**
- Integration with existing agents
- ServiceManager integration
- Combining with other services
- Building complete applications

**Run:**
```bash
php examples/tutorials/streaming-flow/07-integration.php
```

**What you'll see:**
```
Service manager configured with:
   - Cache service
   - Telemetry service
   - Flow event manager
   - Streaming executor

✅ StreamingLoop configured with FlowEventManager
✅ Telemetry listener registered
✅ Cache integration ready

Telemetry Metrics:
  streaming.tokens: 150
  streaming.iterations: 3
  streaming.tool_calls: 2

Cache Statistics:
  Active keys: 5
```

**Key Takeaways:**
- Streaming integrates seamlessly with services
- Combine with cache, telemetry, tracing
- Build production-ready applications
- Monitor full stack metrics

---

## Core Concepts

### 1. Event Queue

FIFO queue for event management:

```php
use ClaudeAgents\Events\EventQueue;

$queue = new EventQueue(maxSize: 100);

// Add events
$queue->enqueue($event);

// Remove events
$event = $queue->dequeue();

// Check status
$queue->isEmpty();
$queue->size();
```

**Configuration:**
- `maxSize`: Maximum events (default: 100)
- Tracks dropped events when full
- Provides utilization statistics

### 2. Flow Events

Events represent execution milestones:

```php
use ClaudeAgents\Events\FlowEvent;

// Flow lifecycle
$event = FlowEvent::flowStarted(['input' => 'task']);
$event = FlowEvent::flowCompleted(['output' => 'result']);

// Token streaming
$event = FlowEvent::token('Hello');

// Progress tracking
$event = FlowEvent::progress(50.0, ['step' => 'processing']);

// Tool execution
$event = FlowEvent::toolStarted('calculator', ['a' => 1, 'b' => 2]);
```

**Event Types (25+):**
- Flow: started, completed, failed
- Token: received, chunk
- Iteration: started, completed, failed
- Tool: started, completed, failed
- Progress: update, step events
- Error: error, warning, info

### 3. Event Manager

Manages event registration and broadcasting:

```php
use ClaudeAgents\Events\FlowEventManager;

$manager = new FlowEventManager($eventQueue);

// Register events
$manager->registerEvent('on_token', FlowEvent::TOKEN_RECEIVED);

// Register with callback
$manager->registerEvent('on_error', FlowEvent::ERROR, function($event) {
    error_log($event->data['message']);
});

// Emit events
$manager->emit(FlowEvent::TOKEN_RECEIVED, ['token' => 'Hi']);

// Subscribe listeners
$id = $manager->subscribe(function($event) {
    echo "Event: {$event->type}\n";
});
```

### 4. Streaming Executor

Main execution engine:

```php
use ClaudeAgents\Execution\StreamingFlowExecutor;

$executor = new StreamingFlowExecutor($eventManager, $eventQueue);

// Stream execution
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    handleEvent($event);
}

// SSE streaming
foreach ($executor->streamSSE($agent, $task) as $sseData) {
    echo $sseData;
    flush();
}
```

### 5. Progress Tracking

Track execution progress:

```php
use ClaudeAgents\Execution\FlowProgress;

$progress = new FlowProgress(totalIterations: 10);
$progress->start();
$progress->startIteration(1);

echo $progress->getProgress();              // 10.0 (percentage)
echo $progress->getFormattedDuration();     // "1.5s"
echo $progress->getEstimatedTimeRemaining(); // 13.5 (seconds)
```

## Architecture Overview

### Component Diagram

```
┌─────────────────────┐
│ StreamingFlowExecutor│
│  Generator-based    │
└──────┬──────┬───────┘
       │      │
       │      └─────────┐
       ▼                ▼
┌─────────────┐  ┌─────────────┐
│ EventManager│  │ EventQueue  │
│  Broadcast  │◄─┤   FIFO      │
└─────────────┘  └─────────────┘
       │
       ▼
┌─────────────┐
│ FlowEvent   │
│  25+ types  │
└─────────────┘
```

### Event Flow

```
Agent executes
      │
      ▼
Event emitted ──► EventQueue
      │
      ▼
StreamingFlowExecutor yields event
      │
      ├──► Consumer 1
      ├──► Consumer 2
      └──► Consumer N
```

## Complete Examples

### Example 1: CLI Progress Display

```php
<?php
require 'vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

$executor = ServiceManager::getInstance()->get(ServiceType::FLOW_EXECUTOR);

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'token') {
        echo $event['data']['token'];
    } elseif ($event['type'] === 'progress') {
        $percent = $event['data']['progress_percent'];
        echo "\rProgress: " . round($percent) . "%";
    }
}
```

### Example 2: Web API Endpoint

```php
<?php
// api/stream.php

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$task = $_GET['task'] ?? 'Hello';

foreach ($executor->streamSSE($agent, $task) as $sseData) {
    echo $sseData;
    flush();
}
```

### Example 3: Multiple Consumers

```php
<?php
$eventManager = ServiceManager::getInstance()->get(ServiceType::EVENT_MANAGER);

// Logger
$eventManager->subscribe(fn($e) => logToFile($e));

// Metrics
$eventManager->subscribe(fn($e) => recordMetric($e));

// UI Update
$eventManager->subscribe(fn($e) => updateUI($e));

// All receive events simultaneously
```

### Example 4: Progress Dashboard

```php
<?php
$dashboard = [
    'tokens' => 0,
    'iterations' => 0,
    'tools' => [],
    'duration' => 0,
];

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    match ($event['type']) {
        'token' => $dashboard['tokens']++,
        'iteration_end' => $dashboard['iterations']++,
        'tool_end' => $dashboard['tools'][] = $event['data']['tool'],
        'progress' => $dashboard['duration'] = $event['data']['duration'],
        default => null
    };
    
    renderDashboard($dashboard);
}
```

## Production Patterns

### Pattern 1: Buffered Streaming

Reduce network calls by buffering tokens:

```php
$buffer = '';
$bufferSize = 20;

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'token') {
        $buffer .= $event['data']['token'];
        
        if (strlen($buffer) >= $bufferSize) {
            sendToClient($buffer);
            $buffer = '';
        }
    }
}

sendToClient($buffer); // Flush remaining
```

### Pattern 2: Error Recovery

Implement robust error handling:

```php
$maxRetries = 3;
$attempt = 0;

while ($attempt < $maxRetries) {
    try {
        foreach ($executor->executeWithStreaming($agent, $task) as $event) {
            if ($event['type'] === 'error') {
                throw new RuntimeException($event['data']['error']);
            }
            processEvent($event);
        }
        break; // Success
        
    } catch (Exception $e) {
        $attempt++;
        if ($attempt >= $maxRetries) throw $e;
        sleep(pow(2, $attempt)); // Exponential backoff
    }
}
```

### Pattern 3: Event Recording

Record events for replay or analysis:

```php
class EventRecorder {
    private array $events = [];
    
    public function record(array $event): void {
        $this->events[] = $event;
    }
    
    public function save(string $path): void {
        file_put_contents($path, json_encode($this->events));
    }
    
    public function replay(): Generator {
        foreach ($this->events as $event) {
            yield $event;
        }
    }
}

$recorder = new EventRecorder();

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    $recorder->record($event);
    processEvent($event);
}

$recorder->save('execution_log.json');
```

### Pattern 4: Metrics Collection

Track detailed metrics during execution:

```php
$metrics = [
    'start_time' => microtime(true),
    'token_count' => 0,
    'iteration_count' => 0,
    'tool_calls' => [],
    'errors' => 0,
];

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    match ($event['type']) {
        'token' => $metrics['token_count']++,
        'iteration_end' => $metrics['iteration_count']++,
        'tool_end' => $metrics['tool_calls'][] = $event['data']['tool'],
        'error' => $metrics['errors']++,
        default => null
    };
}

$metrics['duration'] = microtime(true) - $metrics['start_time'];
$metrics['tokens_per_second'] = $metrics['token_count'] / max($metrics['duration'], 0.001);

saveMetrics($metrics);
```

### Pattern 5: Conditional Streaming

Stream different content based on conditions:

```php
function conditionalStream(
    StreamingFlowExecutor $executor,
    Agent $agent,
    string $task,
    bool $verbose
): Generator {
    foreach ($executor->executeWithStreaming($agent, $task) as $event) {
        if ($verbose) {
            yield $event; // All events
        } else {
            // Only essential events
            if (in_array($event['type'], ['token', 'end', 'error'])) {
                yield $event;
            }
        }
    }
}
```

## Advanced Topics

### Custom Event Types

Define domain-specific events:

```php
class CustomFlowEvent extends FlowEvent
{
    public const CUSTOM_MILESTONE = 'custom.milestone';
    public const CUSTOM_METRIC = 'custom.metric';
    
    public static function milestone(string $name, array $data = []): self
    {
        return new self(
            self::CUSTOM_MILESTONE,
            array_merge(['milestone' => $name], $data),
            microtime(true)
        );
    }
}

$manager->registerEvent('on_milestone', CustomFlowEvent::CUSTOM_MILESTONE);
$manager->emit(CustomFlowEvent::CUSTOM_MILESTONE, ['milestone' => 'halfway']);
```

### Integration with Existing Loops

Enhance existing StreamingLoop:

```php
use ClaudeAgents\Streaming\StreamingLoop;

$loop = new StreamingLoop();
$loop->setFlowEventManager($eventManager);

// Loop now automatically emits:
// - iteration events
// - token events
// - tool execution events

$context = new AgentContext($client, $config);
$context->setTask($task);
$context->setTools($tools);

$result = $loop->execute($context);
```

### Service Layer Integration

Use with full service stack:

```php
$manager = ServiceManager::getInstance();
$manager
    ->registerFactory(new CacheServiceFactory())
    ->registerFactory(new TelemetryServiceFactory())
    ->registerFactory(new TracingServiceFactory())
    ->registerFactory(new FlowEventManagerServiceFactory())
    ->registerFactory(new StreamingFlowExecutorServiceFactory());

// All services work together
$cache = $manager->get(ServiceType::CACHE);
$telemetry = $manager->get(ServiceType::TELEMETRY);
$tracing = $manager->get(ServiceType::TRACING);
$executor = $manager->get(ServiceType::FLOW_EXECUTOR);
```

## Troubleshooting

### Issue: No Events Appearing

**Symptoms:** Stream executes but no events appear

**Solutions:**
1. Ensure Generator is fully consumed:
   ```php
   foreach ($executor->executeWithStreaming(...) as $event) {
       // Must iterate through all events
   }
   ```

2. Check queue isn't full:
   ```php
   $stats = $eventQueue->getStats();
   if ($stats['dropped_events'] > 0) {
       // Increase queue size
       $queue = new EventQueue(maxSize: 500);
   }
   ```

### Issue: High Memory Usage

**Symptoms:** Memory consumption grows during execution

**Solutions:**
1. Reduce queue size:
   ```php
   $queue = new EventQueue(maxSize: 50);
   ```

2. Process events immediately:
   ```php
   foreach ($executor->executeWithStreaming(...) as $event) {
       processEvent($event); // Don't store
   }
   ```

3. Clear queue periodically:
   ```php
   if ($eventCount % 100 === 0) {
       gc_collect_cycles();
   }
   ```

### Issue: SSE Not Working

**Symptoms:** Browser doesn't receive events

**Solutions:**
1. Disable output buffering:
   ```php
   if (ob_get_level()) ob_end_clean();
   ```

2. Set correct headers:
   ```php
   header('Content-Type: text/event-stream');
   header('Cache-Control: no-cache');
   header('X-Accel-Buffering: no'); // For nginx
   ```

3. Flush regularly:
   ```php
   echo $sseData;
   flush();
   ```

### Issue: Events Out of Order

**Symptoms:** Events arrive in unexpected order

**Solutions:**
- EventQueue is FIFO - check emission order
- Verify listeners aren't modifying queue
- Check for multiple event managers

### Issue: Dropped Events

**Symptoms:** Queue shows dropped events in stats

**Solutions:**
1. Increase queue size
2. Process events faster
3. Reduce emission rate
4. Use selective event registration

## Best Practices

### 1. Always Consume Generators Fully

```php
// ✅ Good: Full consumption
foreach ($executor->executeWithStreaming(...) as $event) {
    processEvent($event);
}

// ❌ Bad: Partial consumption
$generator = $executor->executeWithStreaming(...);
$firstEvent = $generator->current(); // Only gets first event
```

### 2. Handle Errors Explicitly

```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'error') {
        logError($event['data']);
        // Don't ignore errors!
    }
}
```

### 3. Monitor Queue Health

```php
$stats = $eventQueue->getStats();

if ($stats['utilization'] > 80) {
    logger->warning('Queue nearly full');
}

if ($stats['dropped_events'] > 0) {
    logger->error("Lost {$stats['dropped_events']} events");
}
```

### 4. Use Service Manager

```php
// ✅ Good: Use ServiceManager
$executor = ServiceManager::getInstance()->get(ServiceType::FLOW_EXECUTOR);

// ❌ Bad: Manual instantiation (no DI)
$queue = new EventQueue();
$manager = new FlowEventManager($queue);
$executor = new StreamingFlowExecutor($manager, $queue);
```

### 5. Unsubscribe Listeners

```php
class StreamingService {
    private array $listenerIds = [];
    
    public function start() {
        $this->listenerIds[] = $eventManager->subscribe(...);
    }
    
    public function stop() {
        foreach ($this->listenerIds as $id) {
            $eventManager->unsubscribe($id);
        }
    }
}
```

## Performance Tips

1. **Queue Size**: Balance memory vs event loss (100-500 typical)
2. **Selective Events**: Only register events you need
3. **Lightweight Listeners**: Keep handlers fast
4. **Batch Processing**: Process events in batches for efficiency
5. **Clear Queue**: Periodically clear processed events

## Next Steps

After completing this tutorial series:

1. **Read the docs:**
   - [Execution Guide](../execution/README.md)
   - [Event Reference](../execution/EVENTS.md)
   - [Streaming Patterns](../execution/STREAMING.md)

2. **Explore examples:**
   - [Basic Examples](../../examples/Execution/)
   - [Advanced Patterns](../../examples/Execution/)

3. **Build something:**
   - Real-time dashboard
   - CLI with progress bars
   - Web application with SSE
   - Multi-agent monitoring system

## Resources

- **API Reference**: See individual component documentation
- **Examples**: `examples/tutorials/streaming-flow/`
- **Tests**: `tests/Unit/Events/` and `tests/Unit/Execution/`
- **GitHub Issues**: Report bugs or ask questions

## Summary

You now know how to:

✅ Set up streaming flow execution  
✅ Handle real-time events  
✅ Track progress and duration  
✅ Build SSE endpoints  
✅ Create custom events  
✅ Handle errors gracefully  
✅ Integrate with services  

**Total time:** ~90 minutes  
**Skill level:** Intermediate  
**Completion:** Full-stack streaming applications

---

*Last updated: February 4, 2026*
