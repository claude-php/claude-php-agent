# Streaming Flow Execution - Complete Index

Complete reference for all documentation, examples, and resources related to the Streaming Flow Execution system.

## Documentation Structure

### Main Documentation (docs/execution/)

```
docs/execution/
├── README.md          - Main guide (400+ lines)
├── EVENTS.md          - Event reference (500+ lines)
├── STREAMING.md       - Streaming patterns (600+ lines)
└── INDEX.md           - This file
```

### Tutorial (docs/tutorials/)

```
docs/tutorials/
└── StreamingFlow_Tutorial.md  - 60-minute comprehensive tutorial
```

### Advanced Examples (examples/Execution/)

```
examples/Execution/
├── README.md                  - Examples guide
├── basic-streaming.php        - Basic streaming demonstration
├── progress-tracking.php      - Progress monitoring
├── multiple-listeners.php     - Multi-subscriber pattern
└── sse-server.php            - SSE endpoint with HTML client
```

### Tutorial Examples (examples/tutorials/streaming-flow/)

```
examples/tutorials/streaming-flow/
├── README.md                    - Tutorial series guide
├── 01-basic-streaming.php      - Basic streaming (10 min)
├── 02-progress-tracking.php    - Progress tracking (10 min)
├── 03-event-listeners.php      - Event listeners (15 min)
├── 04-sse-streaming.php        - SSE streaming (15 min)
├── 05-custom-events.php        - Custom events (15 min)
├── 06-error-handling.php       - Error handling (15 min)
└── 07-integration.php          - Full integration (20 min)
```

## Quick Navigation

### By Topic

**Getting Started:**
- [README.md](README.md) - Start here
- [Tutorial 01](../../examples/tutorials/streaming-flow/01-basic-streaming.php) - First steps
- [Basic Example](../../examples/Execution/basic-streaming.php) - Simple usage

**Event System:**
- [EVENTS.md](EVENTS.md) - Complete event reference
- [Tutorial 03](../../examples/tutorials/streaming-flow/03-event-listeners.php) - Listeners
- [Tutorial 05](../../examples/tutorials/streaming-flow/05-custom-events.php) - Custom events

**Streaming Patterns:**
- [STREAMING.md](STREAMING.md) - Advanced patterns
- [Tutorial 02](../../examples/tutorials/streaming-flow/02-progress-tracking.php) - Progress
- [Tutorial 04](../../examples/tutorials/streaming-flow/04-sse-streaming.php) - SSE

**Integration:**
- [Tutorial 07](../../examples/tutorials/streaming-flow/07-integration.php) - Full integration
- [Advanced Example](../../examples/Execution/multiple-listeners.php) - Multi-subscriber

**Error Handling:**
- [Tutorial 06](../../examples/tutorials/streaming-flow/06-error-handling.php) - Errors & recovery

### By Skill Level

**Beginner:**
1. [README.md](README.md) - Overview
2. [Tutorial 01](../../examples/tutorials/streaming-flow/01-basic-streaming.php) - Basic streaming
3. [Basic Example](../../examples/Execution/basic-streaming.php) - Simple example

**Intermediate:**
1. [Tutorial 02](../../examples/tutorials/streaming-flow/02-progress-tracking.php) - Progress
2. [Tutorial 03](../../examples/tutorials/streaming-flow/03-event-listeners.php) - Listeners
3. [EVENTS.md](EVENTS.md) - Event reference

**Advanced:**
1. [STREAMING.md](STREAMING.md) - Patterns
2. [Tutorial 04](../../examples/tutorials/streaming-flow/04-sse-streaming.php) - SSE
3. [Tutorial 07](../../examples/tutorials/streaming-flow/07-integration.php) - Integration

**Expert:**
1. [Tutorial 05](../../examples/tutorials/streaming-flow/05-custom-events.php) - Custom events
2. [Tutorial 06](../../examples/tutorials/streaming-flow/06-error-handling.php) - Error handling
3. [Advanced Examples](../../examples/Execution/) - Production patterns

### By Use Case

**CLI Applications:**
- [Basic Streaming](../../examples/Execution/basic-streaming.php)
- [Progress Tracking](../../examples/Execution/progress-tracking.php)
- [Tutorial 02](../../examples/tutorials/streaming-flow/02-progress-tracking.php)

**Web Applications:**
- [SSE Server](../../examples/Execution/sse-server.php)
- [Tutorial 04](../../examples/tutorials/streaming-flow/04-sse-streaming.php)
- [STREAMING.md - SSE Section](STREAMING.md#sse-implementation)

**Monitoring/Observability:**
- [Multiple Listeners](../../examples/Execution/multiple-listeners.php)
- [Tutorial 03](../../examples/tutorials/streaming-flow/03-event-listeners.php)
- [Tutorial 07](../../examples/tutorials/streaming-flow/07-integration.php)

**Custom Solutions:**
- [Tutorial 05](../../examples/tutorials/streaming-flow/05-custom-events.php)
- [STREAMING.md - Advanced Patterns](STREAMING.md#advanced-patterns)

## Learning Path

### Path 1: Quick Start (30 minutes)

1. Read [README.md](README.md) overview (10 min)
2. Run [Tutorial 01](../../examples/tutorials/streaming-flow/01-basic-streaming.php) (10 min)
3. Run [Basic Example](../../examples/Execution/basic-streaming.php) (10 min)

### Path 2: Complete Tutorial (90 minutes)

1. All 7 tutorials in `examples/tutorials/streaming-flow/` (90 min)
2. Read [EVENTS.md](EVENTS.md) for reference
3. Skim [STREAMING.md](STREAMING.md) for patterns

### Path 3: Production Ready (2 hours)

1. Complete Tutorial series (90 min)
2. Read [STREAMING.md](STREAMING.md) thoroughly (30 min)
3. Build a real application using patterns

## Component Reference

### Core Classes

- **EventQueue** - `src/Events/EventQueue.php`
- **FlowEvent** - `src/Events/FlowEvent.php`
- **FlowEventManager** - `src/Events/FlowEventManager.php`
- **FlowProgress** - `src/Execution/FlowProgress.php`
- **StreamingFlowExecutor** - `src/Execution/StreamingFlowExecutor.php`

### Interfaces

- **FlowExecutorInterface** - `src/Contracts/FlowExecutorInterface.php`
- **StreamableAgentInterface** - `src/Contracts/StreamableAgentInterface.php`

### Services

- **FlowEventManagerServiceFactory** - `src/Services/Execution/`
- **StreamingFlowExecutorServiceFactory** - `src/Services/Execution/`
- **ServiceType** - Updated with `FLOW_EXECUTOR` and `EVENT_MANAGER`

### Tests

- **EventQueueTest** - `tests/Unit/Events/EventQueueTest.php` (11 tests)
- **FlowEventManagerTest** - `tests/Unit/Events/FlowEventManagerTest.php` (15 tests)
- **StreamingFlowExecutorTest** - `tests/Unit/Execution/StreamingFlowExecutorTest.php` (10 tests)

## File Statistics

| Category | Files | Lines |
|----------|-------|-------|
| Core Components | 7 | ~3,500 |
| Service Integration | 3 | ~200 |
| Documentation | 5 | ~2,000 |
| Tutorial Examples | 7 | ~800 |
| Advanced Examples | 4 | ~400 |
| Tests | 3 | ~800 |
| **Total** | **29** | **~7,700** |

## Event Type Reference

Quick reference for all 25+ event types:

**Flow:** `FLOW_STARTED`, `FLOW_COMPLETED`, `FLOW_FAILED`  
**Token:** `TOKEN_RECEIVED`, `TOKEN_CHUNK`  
**Iteration:** `ITERATION_STARTED`, `ITERATION_COMPLETED`, `ITERATION_FAILED`  
**Tool:** `TOOL_EXECUTION_STARTED`, `TOOL_EXECUTION_COMPLETED`, `TOOL_EXECUTION_FAILED`  
**Progress:** `PROGRESS_UPDATE`, `STEP_STARTED`, `STEP_COMPLETED`  
**Langflow:** `MESSAGE_ADDED`, `VERTEX_COMPLETED`, `BUILD_STARTED`, etc.

See [EVENTS.md](EVENTS.md) for complete reference.

## Code Snippets

### Minimal Setup

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

$executor = ServiceManager::getInstance()->get(ServiceType::FLOW_EXECUTOR);

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'token') {
        echo $event['data']['token'];
    }
}
```

### With Progress

```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    match ($event['type']) {
        'token' => print($event['data']['token']),
        'progress' => printf("\rProgress: %.1f%%", $event['data']['progress_percent']),
        default => null
    };
}
```

### SSE Endpoint

```php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

foreach ($executor->streamSSE($agent, $task) as $sseData) {
    echo $sseData;
    flush();
}
```

## External Resources

- **Langflow** - Original inspiration: https://github.com/langflow-ai/langflow
- **PHP Generators** - https://www.php.net/manual/en/language.generators.php
- **SSE Specification** - https://html.spec.whatwg.org/multipage/server-sent-events.html
- **SplQueue** - https://www.php.net/manual/en/class.splqueue.php

## Changelog

- **2026-02-04** - Initial release
  - Complete streaming system
  - 7 tutorial examples
  - 4 advanced examples
  - Comprehensive documentation

## Support

- **Documentation Issues**: Check this index for navigation
- **Code Issues**: See [Troubleshooting](README.md#troubleshooting)
- **Questions**: GitHub Issues
- **Examples Not Working**: Verify API key is set

---

*Complete index for Streaming Flow Execution system*
