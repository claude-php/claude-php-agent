# Streaming Flow Execution

Comprehensive guide to real-time streaming flow execution in claude-php-agent, inspired by Langflow's sophisticated event-driven architecture.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [Architecture](#architecture)
- [Usage Examples](#usage-examples)
- [API Reference](#api-reference)
- [Advanced Topics](#advanced-topics)

## Overview

The Streaming Flow Execution system provides:

- **Real-time Token Streaming**: Token-by-token LLM responses as they're generated
- **Event-Driven Architecture**: Queue-based event management inspired by Langflow
- **Progress Tracking**: Detailed execution progress and timing information
- **Multiple Listeners**: One-to-many event broadcasting
- **SSE Support**: Server-Sent Events for web-based streaming
- **Service Integration**: Full integration with the Service Layer

### Key Features

✅ Generator-based streaming (PHP adaptation of Python's async/await)  
✅ Non-blocking event queues with configurable size limits  
✅ Comprehensive event types (flow, iteration, tool, progress, error)  
✅ Real-time progress tracking with duration estimates  
✅ SSE-ready output format for web applications  
✅ Multiple subscriber pattern for event broadcasting  
✅ Full backward compatibility with existing agents  

## Quick Start

### 1. Basic Streaming Execution

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Execution\FlowEventManagerServiceFactory;
use ClaudeAgents\Services\Execution\StreamingFlowExecutorServiceFactory;
use ClaudeSDK\ClaudeClient;

// Register services
$serviceManager = ServiceManager::getInstance();
$serviceManager
    ->registerFactory(new FlowEventManagerServiceFactory())
    ->registerFactory(new StreamingFlowExecutorServiceFactory());

// Get executor
$executor = $serviceManager->get(ServiceType::FLOW_EXECUTOR);

// Create agent
$client = new ClaudeClient($apiKey);
$config = new AgentConfig(model: 'claude-3-5-sonnet-20241022');
$agent = new Agent($client, $config);

// Execute with streaming
foreach ($executor->executeWithStreaming($agent, "Task") as $event) {
    match ($event['type']) {
        'token' => print($event['data']['token']),
        'progress' => printf("Progress: %.1f%%\n", $event['data']['progress_percent']),
        'end' => print("\nDone!\n"),
        default => null
    };
}
```

### 2. Manual Setup (Without Service Manager)

```php
use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Execution\StreamingFlowExecutor;

// Create event system
$eventQueue = new EventQueue(maxSize: 100);
$eventManager = new FlowEventManager($eventQueue);
$eventManager->registerDefaultEvents();

// Create executor
$executor = new StreamingFlowExecutor($eventManager, $eventQueue);

// Use executor
foreach ($executor->executeWithStreaming($agent, "Task") as $event) {
    // Handle events
}
```

## Core Concepts

### Event Queue

The `EventQueue` is a FIFO (First-In-First-Out) queue based on PHP's `SplQueue`:

```php
$queue = new EventQueue(maxSize: 100);

// Enqueue events
$event = FlowEvent::token('Hello');
$queue->enqueue($event);

// Dequeue events
$event = $queue->dequeue(); // Returns FlowEvent or null

// Check status
$queue->isEmpty();    // bool
$queue->size();       // int
$queue->peek();       // FlowEvent|null (non-destructive)
```

**Key Features:**
- Non-blocking operations
- Configurable max size (default: 100)
- Tracks dropped events when full
- Statistics and utilization metrics

### Flow Events

`FlowEvent` represents a single event in the execution lifecycle:

```php
// Create events
$event = FlowEvent::flowStarted(['input' => 'Task']);
$event = FlowEvent::token('Hello');
$event = FlowEvent::iterationCompleted(1, ['duration' => 1.5]);
$event = FlowEvent::toolStarted('calculator', ['a' => 1, 'b' => 2]);
$event = FlowEvent::progress(50.0, ['step' => 'processing']);

// Event properties
$event->type;       // string - Event type constant
$event->data;       // array - Event payload
$event->timestamp;  // float - Unix timestamp with microseconds
$event->id;         // string|null - Optional event ID

// Event type checking
$event->isToken();       // bool
$event->isFlowEvent();   // bool
$event->isError();       // bool
$event->isProgress();    // bool
$event->isToolEvent();   // bool

// Convert to formats
$event->toArray();  // array
$event->toJson();   // string (JSON)
$event->toSSE();    // string (SSE format)
```

### Event Types

The system provides comprehensive event types organized by category:

**Flow Lifecycle:**
- `FLOW_STARTED` - Flow execution begins
- `FLOW_COMPLETED` - Flow execution completes successfully
- `FLOW_FAILED` - Flow execution fails
- `FLOW_PAUSED` / `FLOW_RESUMED` - Flow pause/resume

**Token Streaming:**
- `TOKEN_RECEIVED` - Individual token from LLM
- `TOKEN_CHUNK` - Token chunk (batch)

**Iteration Events:**
- `ITERATION_STARTED` - New iteration begins
- `ITERATION_COMPLETED` - Iteration completes
- `ITERATION_FAILED` - Iteration fails

**Tool Execution:**
- `TOOL_EXECUTION_STARTED` - Tool execution begins
- `TOOL_EXECUTION_COMPLETED` - Tool execution completes
- `TOOL_EXECUTION_FAILED` - Tool execution fails

**Progress Events:**
- `PROGRESS_UPDATE` - Progress percentage update
- `STEP_STARTED` / `STEP_COMPLETED` - Step lifecycle

**Langflow Compatibility:**
- `MESSAGE_ADDED` / `MESSAGE_REMOVED` - Message events
- `VERTEX_STARTED` / `VERTEX_COMPLETED` - Node events
- `BUILD_STARTED` / `BUILD_COMPLETED` - Build events

### Flow Event Manager

The `FlowEventManager` manages event registration, emission, and broadcasting:

```php
$manager = new FlowEventManager($eventQueue);

// Register events
$manager->registerEvent('on_token', FlowEvent::TOKEN_RECEIVED);
$manager->registerEvent('on_error', FlowEvent::ERROR, function($event) {
    error_log($event->data['message']);
});

// Register preset events
$manager->registerDefaultEvents();      // Langflow-compatible events
$manager->registerStreamingEvents();    // Streaming-focused events

// Emit events
$manager->emit(FlowEvent::TOKEN_RECEIVED, ['token' => 'Hello']);

// Magic method emission
$manager->on_token(['token' => 'Hello']); // Calls emit()

// Subscribe listeners
$listenerId = $manager->subscribe(function($event) {
    echo "Event: {$event->type}\n";
});

// Unsubscribe
$manager->unsubscribe($listenerId);
```

### Streaming Flow Executor

The main executor for streaming agent execution:

```php
$executor = new StreamingFlowExecutor($eventManager, $eventQueue);

// Streaming execution (returns Generator)
foreach ($executor->executeWithStreaming($agent, $task, $options) as $event) {
    // Handle event
}

// Blocking execution (returns AgentResult)
$result = $executor->execute($agent, $task);

// SSE streaming (returns Generator of SSE-formatted strings)
foreach ($executor->streamSSE($agent, $task) as $sseData) {
    echo $sseData;
    flush();
}

// Status checks
$executor->isRunning();           // bool
$executor->getCurrentProgress();  // array|null
```

### Flow Progress

Track execution progress in real-time:

```php
$progress = new FlowProgress(totalIterations: 10);

$progress->start();
$progress->startIteration(1);
$progress->completeStep('tool_execution');

// Get progress info
$progress->getProgress();              // 10.0 (percentage)
$progress->getCurrentIteration();      // 1
$progress->getCurrentStep();           // 'tool_execution'
$progress->getDuration();              // 1.5 (seconds)
$progress->getFormattedDuration();     // '1.5s'
$progress->getEstimatedTimeRemaining(); // 13.5 (seconds)

// Metadata
$progress->setMetadata('key', 'value');
$progress->getMetadata('key');

// Export
$progress->toArray();    // Full progress data
$progress->getSummary(); // Human-readable summary
```

## Architecture

### Component Diagram

```
┌─────────────────────────────────────────────────────────┐
│                   StreamingFlowExecutor                  │
│  - executeWithStreaming()                                │
│  - execute()                                             │
│  - streamSSE()                                           │
└───────────────┬─────────────────────────┬───────────────┘
                │                         │
                │ uses                    │ uses
                ▼                         ▼
    ┌───────────────────────┐  ┌────────────────────┐
    │  FlowEventManager     │  │    EventQueue      │
    │  - registerEvent()    │◄─┤  - enqueue()       │
    │  - emit()             │  │  - dequeue()       │
    │  - subscribe()        │  │  - isEmpty()       │
    └───────────┬───────────┘  └────────────────────┘
                │
                │ manages
                ▼
        ┌───────────────┐
        │   FlowEvent   │
        │  - type       │
        │  - data       │
        │  - timestamp  │
        └───────────────┘
```

### Event Flow

```
Agent.run() triggered
        │
        ▼
FlowEventManager.emit(FLOW_STARTED)
        │
        ▼
EventQueue.enqueue(event)
        │
        ▼
StreamingFlowExecutor yields events
        │
        ▼
Consumer receives event
        │
        ▼
Event processed (UI update, logging, etc.)
```

## Usage Examples

See the comprehensive examples in `examples/Execution/`:

- **[basic-streaming.php](../../examples/Execution/basic-streaming.php)** - Basic streaming with event handling
- **[progress-tracking.php](../../examples/Execution/progress-tracking.php)** - Real-time progress monitoring
- **[multiple-listeners.php](../../examples/Execution/multiple-listeners.php)** - Multi-subscriber pattern
- **[sse-server.php](../../examples/Execution/sse-server.php)** - SSE endpoint for web streaming

## API Reference

See detailed API documentation:

- [Event Reference](EVENTS.md) - Complete event type documentation
- [Streaming Patterns](STREAMING.md) - Streaming implementation patterns
- [Service Integration](../services/README.md) - Service layer integration

## Advanced Topics

### Custom Event Types

Define custom events for domain-specific needs:

```php
class CustomFlowEvent extends FlowEvent
{
    public const CUSTOM_EVENT = 'custom.my_event';
}

$manager->registerEvent('on_custom', CustomFlowEvent::CUSTOM_EVENT);
$manager->emit(CustomFlowEvent::CUSTOM_EVENT, ['custom' => 'data']);
```

### Integration with StreamingLoop

The `StreamingLoop` is automatically enhanced with flow event emission:

```php
use ClaudeAgents\Streaming\StreamingLoop;

$loop = new StreamingLoop();
$loop->setFlowEventManager($eventManager);

// Loop now emits:
// - ITERATION_STARTED / ITERATION_COMPLETED
// - TOKEN_RECEIVED (for each token)
// - TOOL_EXECUTION_STARTED / TOOL_EXECUTION_COMPLETED
```

### Performance Optimization

**Queue Size Tuning:**

```php
// High-throughput: larger queue
$queue = new EventQueue(maxSize: 500);

// Memory-constrained: smaller queue
$queue = new EventQueue(maxSize: 50);
```

**Selective Event Registration:**

```php
// Only register events you need
$manager->registerEvent('on_token', FlowEvent::TOKEN_RECEIVED);
$manager->registerEvent('on_error', FlowEvent::ERROR);
// Skip unused events for better performance
```

### Error Handling

```php
try {
    foreach ($executor->executeWithStreaming($agent, $task) as $event) {
        if ($event['type'] === 'error') {
            // Handle error event
            logger->error($event['data']['error']);
        }
    }
} catch (\Throwable $e) {
    // Handle fatal errors
    logger->critical($e->getMessage());
}
```

## Best Practices

1. **Always consume the Generator** - Even if you don't need all events
2. **Set appropriate queue sizes** - Balance memory vs. event loss
3. **Use progress tracking** - Enable for better UX
4. **Handle errors gracefully** - Check for error events
5. **Unsubscribe listeners** - Prevent memory leaks
6. **Use SSE for web apps** - Native browser support
7. **Monitor queue statistics** - Track dropped events

## Troubleshooting

### Events Not Appearing

- Check queue is not full (`$queue->getStats()`)
- Verify event registration (`$manager->hasEvent()`)
- Ensure Generator is fully consumed

### Memory Issues

- Reduce queue max size
- Limit registered events
- Clear queue periodically (`$queue->clear()`)

### Performance Degradation

- Profile listener callbacks
- Reduce listener count
- Optimize event handler logic

## Migration Guide

For existing code using `StreamingLoop`:

**No changes required!** The system is fully backward compatible.

**To opt-in to enhanced tracking:**

```php
// Before
$loop = new StreamingLoop();

// After
$manager = ServiceManager::getInstance()
    ->get(ServiceType::EVENT_MANAGER);
$loop->setFlowEventManager($manager);
```

## Support

- **Examples**: See `examples/Execution/`
- **Tests**: See `tests/Unit/Events/` and `tests/Unit/Execution/`
- **Issues**: GitHub Issues

## License

Same as claude-php-agent main project.
