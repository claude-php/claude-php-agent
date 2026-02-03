# Streaming Patterns

Advanced patterns and implementation details for streaming flow execution.

## Table of Contents

- [PHP Async Adaptation](#php-async-adaptation)
- [Streaming Patterns](#streaming-patterns)
- [SSE Implementation](#sse-implementation)
- [Performance Optimization](#performance-optimization)
- [Advanced Patterns](#advanced-patterns)

## PHP Async Adaptation

### From Python async/await to PHP Generators

Langflow uses Python's native async/await for streaming. PHP doesn't have native async, so we use Generators as a functional equivalent.

#### Langflow (Python)

```python
async def execute_flow():
    async for event in stream:
        yield event
```

#### Claude-PHP-Agent (PHP)

```php
public function executeFlow(): Generator {
    while ($event = $this->queue->dequeue()) {
        yield $event;
    }
}
```

### Queue Management Comparison

| Langflow (Python) | Claude-PHP-Agent (PHP) |
|-------------------|------------------------|
| `asyncio.Queue` | `SplQueue` |
| `await queue.get()` | `$queue->dequeue()` |
| `queue.put_nowait()` | `$queue->enqueue()` |
| Async subscribers | Iterator pattern |

### Why Generators?

Generators provide several advantages for streaming:

1. **Memory Efficiency**: Values are produced on-demand
2. **Lazy Evaluation**: Only compute what's needed
3. **State Management**: Generator maintains execution state
4. **Iterable**: Works with `foreach` loops naturally

```php
// Generator function
function streamTokens(): Generator {
    foreach ($tokens as $token) {
        yield $token;  // Suspend execution, return value
    }
}

// Consumer
foreach (streamTokens() as $token) {
    echo $token;  // Values produced one at a time
}
```

## Streaming Patterns

### Pattern 1: Direct Token Streaming

Stream tokens directly as they're received:

```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'token') {
        echo $event['data']['token'];
        flush();  // Immediate output
    }
}
```

**Use Case:** CLI applications, real-time console output

**Pros:** Lowest latency, simple
**Cons:** No buffering, may be chatty

---

### Pattern 2: Buffered Streaming

Accumulate tokens before displaying:

```php
$buffer = '';
$bufferSize = 10;

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'token') {
        $buffer .= $event['data']['token'];
        
        if (strlen($buffer) >= $bufferSize) {
            echo $buffer;
            flush();
            $buffer = '';
        }
    }
}

echo $buffer; // Flush remaining
```

**Use Case:** Web applications with network latency

**Pros:** Fewer writes, better network efficiency
**Cons:** Slightly higher latency

---

### Pattern 3: Selective Event Streaming

Only stream specific event types:

```php
$streamTypes = ['token', 'progress', 'tool_start'];

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if (in_array($event['type'], $streamTypes)) {
        processEvent($event);
    }
}
```

**Use Case:** Filtered event streams, reduced bandwidth

**Pros:** Lower bandwidth, focused events
**Cons:** May miss important events

---

### Pattern 4: Event Transformation

Transform events before consumption:

```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    $transformed = transformEvent($event);
    yield $transformed;
}

function transformEvent(array $event): array {
    return match ($event['type']) {
        'token' => ['type' => 'text', 'content' => $event['data']['token']],
        'progress' => ['type' => 'status', 'percent' => $event['data']['percent']],
        default => $event
    };
}
```

**Use Case:** API format conversion, frontend adaptation

**Pros:** Decoupled formats, reusable transformations
**Cons:** Additional processing overhead

---

### Pattern 5: Multi-Consumer Broadcasting

Stream to multiple consumers simultaneously:

```php
class EventBroadcaster {
    private array $consumers = [];
    
    public function addConsumer(callable $consumer): void {
        $this->consumers[] = $consumer;
    }
    
    public function broadcast(array $event): void {
        foreach ($this->consumers as $consumer) {
            $consumer($event);
        }
    }
}

$broadcaster = new EventBroadcaster();
$broadcaster->addConsumer(fn($e) => logEvent($e));
$broadcaster->addConsumer(fn($e) => updateUI($e));
$broadcaster->addConsumer(fn($e) => saveToDatabase($e));

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    $broadcaster->broadcast($event);
}
```

**Use Case:** Multiple UI components, logging + display

**Pros:** Separation of concerns, extensible
**Cons:** Higher memory usage

---

## SSE Implementation

### Basic SSE Server

```php
<?php
// Set SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');  // Disable nginx buffering

// Disable PHP output buffering
if (ob_get_level()) ob_end_clean();

foreach ($executor->streamSSE($agent, $task) as $sseData) {
    echo $sseData;  // Already formatted as SSE
    flush();
}
```

### SSE Event Format

```
event: token.received
data: {"type":"token.received","data":{"token":"Hello"},"timestamp":1234567.89}

event: progress.update
data: {"type":"progress.update","data":{"percent":50.0},"timestamp":1234567.90}

event: end
data: {"type":"end","data":{"status":"completed"},"timestamp":1234567.91}
```

### JavaScript Client

```javascript
const eventSource = new EventSource('/stream?task=' + encodeURIComponent(task));

// Listen to specific events
eventSource.addEventListener('token', (e) => {
    const data = JSON.parse(e.data);
    appendToken(data.data.token);
});

eventSource.addEventListener('progress', (e) => {
    const data = JSON.parse(e.data);
    updateProgressBar(data.data.percent);
});

eventSource.addEventListener('end', (e) => {
    console.log('Stream complete');
    eventSource.close();
});

eventSource.onerror = (e) => {
    console.error('SSE error:', e);
    eventSource.close();
};
```

### SSE Best Practices

1. **Always set proper headers** - Including cache-control
2. **Disable output buffering** - Both PHP and web server
3. **Flush regularly** - After each event
4. **Handle client disconnects** - Check connection status
5. **Include heartbeats** - Prevent timeout (optional)
6. **Close connection properly** - Client and server side

### SSE with Heartbeat

```php
$lastHeartbeat = time();

foreach ($executor->streamSSE($agent, $task) as $sseData) {
    echo $sseData;
    flush();
    
    // Send heartbeat every 15 seconds
    if (time() - $lastHeartbeat > 15) {
        echo ": heartbeat\n\n";
        flush();
        $lastHeartbeat = time();
    }
    
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }
}
```

---

## Performance Optimization

### 1. Queue Size Tuning

```php
// High throughput (more memory)
$queue = new EventQueue(maxSize: 500);

// Memory constrained (may drop events)
$queue = new EventQueue(maxSize: 50);

// Balanced
$queue = new EventQueue(maxSize: 100);  // Default
```

**Monitor dropped events:**

```php
$stats = $queue->getStats();
if ($stats['dropped_events'] > 0) {
    // Consider increasing queue size
}
```

### 2. Selective Event Registration

Only register events you need:

```php
// All events (higher overhead)
$manager->registerDefaultEvents();

// Minimal events (lower overhead)
$manager->registerEvent('on_token', FlowEvent::TOKEN_RECEIVED);
$manager->registerEvent('on_end', FlowEvent::FLOW_COMPLETED);
```

### 3. Listener Optimization

Keep listeners lightweight:

```php
// ❌ Bad: Heavy processing in listener
$manager->subscribe(function($event) {
    processComplexData($event);  // Blocks event loop
    saveToDatabase($event);      // I/O operation
});

// ✅ Good: Lightweight listener
$manager->subscribe(function($event) {
    $eventQueue->enqueue($event);  // Fast enqueue
});

// Process in separate thread/process
while ($event = $eventQueue->dequeue()) {
    processComplexData($event);
}
```

### 4. Batch Processing

Process events in batches:

```php
$batch = [];
$batchSize = 10;

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    $batch[] = $event;
    
    if (count($batch) >= $batchSize) {
        processBatch($batch);
        $batch = [];
    }
}

processBatch($batch);  // Process remaining
```

### 5. Memory Management

Clear queue periodically:

```php
$eventCount = 0;

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    processEvent($event);
    $eventCount++;
    
    // Clear processed events from memory
    if ($eventCount % 100 === 0) {
        gc_collect_cycles();
    }
}
```

---

## Advanced Patterns

### Pattern: Progress Estimation

Estimate remaining time based on completed iterations:

```php
$startTime = microtime(true);
$totalIterations = 10;

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'iteration_end') {
        $current = $event['data']['iteration'];
        $elapsed = microtime(true) - $startTime;
        $avgTime = $elapsed / $current;
        $remaining = ($totalIterations - $current) * $avgTime;
        
        echo sprintf(
            "Progress: %d/%d - ETA: %.1fs\n",
            $current,
            $totalIterations,
            $remaining
        );
    }
}
```

### Pattern: Event Recording

Record events for replay:

```php
$recorder = new EventRecorder();

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    $recorder->record($event);
    processEvent($event);
}

// Replay later
foreach ($recorder->replay() as $event) {
    processEvent($event);
}

class EventRecorder {
    private array $events = [];
    
    public function record(array $event): void {
        $this->events[] = $event;
    }
    
    public function replay(): Generator {
        foreach ($this->events as $event) {
            yield $event;
        }
    }
    
    public function save(string $path): void {
        file_put_contents($path, json_encode($this->events));
    }
}
```

### Pattern: Conditional Streaming

Stream different content based on conditions:

```php
function conditionalStream(
    StreamingFlowExecutor $executor,
    AgentInterface $agent,
    string $task,
    bool $verbose
): Generator {
    foreach ($executor->executeWithStreaming($agent, $task) as $event) {
        if ($verbose) {
            yield $event;  // All events
        } else {
            // Only essential events
            if (in_array($event['type'], ['token', 'end'])) {
                yield $event;
            }
        }
    }
}
```

### Pattern: Event Aggregation

Aggregate multiple events into summaries:

```php
class EventAggregator {
    private int $tokenCount = 0;
    private array $toolCalls = [];
    
    public function aggregate(array $event): void {
        match ($event['type']) {
            'token' => $this->tokenCount++,
            'tool_start' => $this->toolCalls[] = $event['data']['tool'],
            default => null
        };
    }
    
    public function getSummary(): array {
        return [
            'tokens' => $this->tokenCount,
            'tools' => array_count_values($this->toolCalls)
        ];
    }
}

$aggregator = new EventAggregator();

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    $aggregator->aggregate($event);
}

$summary = $aggregator->getSummary();
// ['tokens' => 150, 'tools' => ['calculator' => 2, 'search' => 1]]
```

### Pattern: Error Recovery

Implement error recovery in streaming:

```php
$maxRetries = 3;
$retryCount = 0;

while ($retryCount < $maxRetries) {
    try {
        foreach ($executor->executeWithStreaming($agent, $task) as $event) {
            if ($event['type'] === 'error') {
                throw new RuntimeException($event['data']['error']);
            }
            processEvent($event);
        }
        break;  // Success
    } catch (RuntimeException $e) {
        $retryCount++;
        if ($retryCount >= $maxRetries) {
            throw $e;
        }
        sleep(1);  // Backoff
    }
}
```

---

## Debugging Tips

### Enable Debug Logging

```php
use Psr\Log\LogLevel;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('flow');
$logger->pushHandler(new StreamHandler('php://stdout', LogLevel::DEBUG));

$executor = new StreamingFlowExecutor($eventManager, $eventQueue, $logger);
```

### Monitor Queue Statistics

```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'progress') {
        $stats = $queue->getStats();
        if ($stats['utilization'] > 80) {
            error_log("Warning: Queue utilization at {$stats['utilization']}%");
        }
    }
}
```

### Event Timing Analysis

```php
$timings = [];

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    $timings[] = [
        'type' => $event['type'],
        'timestamp' => $event['data']['timestamp'] ?? microtime(true)
    ];
}

// Analyze timing gaps
for ($i = 1; $i < count($timings); $i++) {
    $gap = $timings[$i]['timestamp'] - $timings[$i-1]['timestamp'];
    if ($gap > 1.0) {  // More than 1 second
        echo "Large gap: {$gap}s between events\n";
    }
}
```

---

## See Also

- [Event Reference](EVENTS.md) - Complete event documentation
- [Main Documentation](README.md) - Overview and quick start
- [Examples](../../examples/Execution/) - Working code examples
