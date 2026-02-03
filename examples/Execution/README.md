# Streaming Flow Execution Examples

This directory contains comprehensive examples demonstrating the Streaming Flow Execution system.

## Examples

### 1. Basic Streaming (`basic-streaming.php`)

**What it demonstrates:**
- Setting up StreamingFlowExecutor with ServiceManager
- Executing an agent with real-time token streaming
- Handling different event types (flow, token, iteration, tool, progress)
- Progress tracking and statistics

**Run:**
```bash
php examples/Execution/basic-streaming.php
```

**Key Features:**
- Token-by-token streaming output
- Tool execution tracking
- Progress monitoring
- Final execution statistics

---

### 2. Progress Tracking (`progress-tracking.php`)

**What it demonstrates:**
- Real-time progress monitoring with progress bars
- Step-by-step execution tracking
- Duration estimates and time remaining
- Queue statistics monitoring

**Run:**
```bash
php examples/Execution/progress-tracking.php
```

**Key Features:**
- ASCII progress bar
- Iteration timing
- Estimated time remaining
- Queue utilization monitoring

---

### 3. Multiple Listeners (`multiple-listeners.php`)

**What it demonstrates:**
- Multiple subscribers to the same flow
- Event broadcasting to all listeners
- Custom event handlers for different purposes
- Listener management (subscribe/unsubscribe)

**Run:**
```bash
php examples/Execution/multiple-listeners.php
```

**Key Features:**
- Token counter listener
- Progress logger listener
- Tool tracker listener
- Error monitor listener

---

### 4. SSE Server (`sse-server.php`)

**What it demonstrates:**
- Server-Sent Events (SSE) endpoint
- Web-based streaming with live browser updates
- SSE event formatting
- Connection management

**Run:**
```bash
php examples/Execution/sse-server.php serve
```

Then open http://localhost:8000 in your browser.

**Key Features:**
- Built-in HTML client with real-time UI
- SSE streaming endpoint
- Progress bar visualization
- Event log display
- Connection status monitoring

---

## Quick Start

### Prerequisites

1. **PHP 8.1+** installed
2. **Composer dependencies** installed:
   ```bash
   composer install
   ```
3. **ANTHROPIC_API_KEY** environment variable set:
   ```bash
   export ANTHROPIC_API_KEY='your-api-key-here'
   ```

### Running Examples

Each example is self-contained and can be run directly:

```bash
# Basic streaming
php examples/Execution/basic-streaming.php

# Progress tracking
php examples/Execution/progress-tracking.php

# Multiple listeners
php examples/Execution/multiple-listeners.php

# SSE server (then open http://localhost:8000)
php examples/Execution/sse-server.php serve
```

## Common Patterns

### Pattern 1: Simple Token Streaming

```php
$executor = $serviceManager->get(ServiceType::FLOW_EXECUTOR);

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'token') {
        echo $event['data']['token'];
    }
}
```

### Pattern 2: Progress Monitoring

```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'progress') {
        $percent = $event['data']['progress_percent'];
        echo "Progress: {$percent}%\n";
    }
}
```

### Pattern 3: Event Filtering

```php
$importantTypes = ['token', 'tool_start', 'error', 'end'];

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if (in_array($event['type'], $importantTypes)) {
        handleEvent($event);
    }
}
```

### Pattern 4: SSE Streaming

```php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

foreach ($executor->streamSSE($agent, $task) as $sseData) {
    echo $sseData;
    flush();
}
```

## Troubleshooting

### No Output Appearing

**Problem:** Events are emitted but not appearing in output.

**Solution:**
1. Ensure you're consuming the Generator fully
2. Call `flush()` after output
3. Disable output buffering: `ob_end_clean()`

### High Memory Usage

**Problem:** Memory usage growing during execution.

**Solution:**
1. Reduce queue size: `new EventQueue(maxSize: 50)`
2. Limit registered events
3. Clear queue periodically
4. Process events immediately, don't store

### Dropped Events

**Problem:** Queue statistics show dropped events.

**Solution:**
1. Increase queue size
2. Process events faster
3. Reduce event emission rate
4. Use selective event registration

## Next Steps

- Read the [comprehensive documentation](../../docs/execution/README.md)
- Explore the [Event Reference](../../docs/execution/EVENTS.md)
- Learn about [Streaming Patterns](../../docs/execution/STREAMING.md)
- Review the [unit tests](../../tests/Unit/Events/) for more examples

## Support

For issues or questions:
- Check the [documentation](../../docs/execution/)
- Review existing [examples](.)
- Open a GitHub issue
