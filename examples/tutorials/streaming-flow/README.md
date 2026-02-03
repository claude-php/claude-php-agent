# Streaming Flow Execution - Tutorial Examples

Complete tutorial series for mastering real-time streaming flow execution.

## Overview

This tutorial series teaches you how to build real-time streaming applications using the Streaming Flow Execution system, inspired by Langflow's event-driven architecture.

**Total Time:** ~90 minutes  
**Skill Level:** Intermediate  
**Prerequisites:** Basic PHP and familiarity with claude-php-agent

## Prerequisites

### 1. Install Dependencies

```bash
composer install
```

### 2. Set API Key

```bash
export ANTHROPIC_API_KEY='your-anthropic-api-key-here'
```

### 3. Verify Setup

```bash
php -v  # Should show PHP 8.1+
echo $ANTHROPIC_API_KEY  # Should show your key
```

## Tutorial Series

### Tutorial 1: Basic Streaming (10 minutes)

**File:** `01-basic-streaming.php`

Learn the fundamentals of streaming flow execution.

```bash
php 01-basic-streaming.php
```

**Topics:**
- Setting up StreamingFlowExecutor
- Handling event types (token, progress, tool, error)
- Real-time output display
- Execution statistics

**Key Concepts:**
- Generator-based streaming
- Event type matching
- ServiceManager integration

---

### Tutorial 2: Progress Tracking (10 minutes)

**File:** `02-progress-tracking.php`

Master real-time progress monitoring.

```bash
php 02-progress-tracking.php
```

**Topics:**
- ASCII progress bars
- Duration tracking
- Time remaining estimation
- Queue statistics

**Key Concepts:**
- FlowProgress class
- Progress events
- Performance monitoring

---

### Tutorial 3: Event Listeners (15 minutes)

**File:** `03-event-listeners.php`

Build multi-subscriber event systems.

```bash
php 03-event-listeners.php
```

**Topics:**
- Multiple listeners
- Custom handlers
- Event broadcasting
- Listener management

**Key Concepts:**
- Subscribe/unsubscribe pattern
- One-to-many broadcasting
- Aggregate statistics

---

### Tutorial 4: SSE Streaming (15 minutes)

**File:** `04-sse-streaming.php`

Create web-based streaming applications.

```bash
php 04-sse-streaming.php serve
# Open http://localhost:8080
```

**Topics:**
- Server-Sent Events (SSE)
- Browser streaming
- Event formatting
- Connection management

**Key Concepts:**
- SSE protocol
- HTML client
- Real-time UI updates

---

### Tutorial 5: Custom Events (15 minutes)

**File:** `05-custom-events.php`

Extend the event system for your domain.

```bash
php 05-custom-events.php
```

**Topics:**
- Custom event types
- Domain-specific events
- Event aggregation
- Specialized handlers

**Key Concepts:**
- Extending FlowEvent
- Custom event constants
- Factory methods

---

### Tutorial 6: Error Handling (15 minutes)

**File:** `06-error-handling.php`

Build robust error handling systems.

```bash
php 06-error-handling.php
```

**Topics:**
- Error detection
- Recovery strategies
- Retry logic
- Graceful degradation

**Key Concepts:**
- Error events
- Exception handling
- Exponential backoff
- Error logging

---

### Tutorial 7: Integration (20 minutes)

**File:** `07-integration.php`

Integrate with the complete service stack.

```bash
php 07-integration.php
```

**Topics:**
- ServiceManager integration
- Cache integration
- Telemetry integration
- StreamingLoop enhancement

**Key Concepts:**
- Full stack integration
- Service composition
- Production patterns

---

## Quick Reference

### Basic Pattern

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

$executor = ServiceManager::getInstance()->get(ServiceType::FLOW_EXECUTOR);

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    match ($event['type']) {
        'token' => echo $event['data']['token'],
        'progress' => updateProgress($event['data']),
        'end' => echo "Done!\n",
        default => null
    };
}
```

### Event Types

- `flow_started` - Flow begins
- `token` - Token received
- `iteration_start` - New iteration
- `iteration_end` - Iteration complete
- `tool_start` - Tool execution begins
- `tool_end` - Tool execution complete
- `progress` - Progress update
- `error` - Error occurred
- `end` - Flow complete

### Service Setup

```php
use ClaudeAgents\Services\Execution\FlowEventManagerServiceFactory;
use ClaudeAgents\Services\Execution\StreamingFlowExecutorServiceFactory;

$serviceManager = ServiceManager::getInstance();
$serviceManager
    ->registerFactory(new FlowEventManagerServiceFactory())
    ->registerFactory(new StreamingFlowExecutorServiceFactory());
```

## Common Patterns

### Pattern 1: Token Streaming

```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'token') {
        echo $event['data']['token'];
        flush();
    }
}
```

### Pattern 2: Progress Bar

```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'progress') {
        $percent = $event['data']['progress_percent'];
        echo "\rProgress: " . round($percent) . "%";
    }
}
```

### Pattern 3: Error Tracking

```php
$errors = [];

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'error') {
        $errors[] = $event['data']['error'];
    }
}

if (!empty($errors)) {
    handleErrors($errors);
}
```

## Troubleshooting

### API Key Not Set

```
❌ Error: ANTHROPIC_API_KEY environment variable not set.
```

**Solution:**
```bash
export ANTHROPIC_API_KEY='your-key-here'
```

### Composer Dependencies Missing

```
Fatal error: Class 'ClaudeAgents\...' not found
```

**Solution:**
```bash
composer install
composer dump-autoload
```

### Port Already in Use (SSE Tutorial)

```
Failed to listen on localhost:8080
```

**Solution:**
```bash
# Use different port
php -S localhost:8081 04-sse-streaming.php
```

## Next Steps

After completing the tutorials:

1. **Read comprehensive docs:**
   - [Execution Guide](../../docs/execution/README.md)
   - [Event Reference](../../docs/execution/EVENTS.md)
   - [Streaming Patterns](../../docs/execution/STREAMING.md)

2. **Explore advanced examples:**
   - [Advanced Examples](../../examples/Execution/)

3. **Build your own:**
   - Real-time dashboard
   - CLI tools with progress
   - Web applications with SSE

## Support

- **Documentation**: [docs/execution/](../../docs/execution/)
- **Examples**: [examples/Execution/](../../examples/Execution/)
- **Tests**: See `tests/Unit/Events/` and `tests/Unit/Execution/`
- **Issues**: GitHub Issues

## Learning Path

```
01-basic-streaming.php
      ↓
02-progress-tracking.php
      ↓
03-event-listeners.php
      ↓
04-sse-streaming.php
      ↓
05-custom-events.php
      ↓
06-error-handling.php
      ↓
07-integration.php
      ↓
Build your own application! 🚀
```

---

**Happy Streaming!** 🌊
