# Streaming Support

Real-time token streaming for progressive agent responses.

## Overview

The streaming system enables real-time processing of Claude's responses as they're generated, providing immediate feedback to users and enabling progressive display of agent output.

**Key Features:**
- ✅ Real-time token streaming with multiple handlers
- ✅ Automatic fallback to non-streaming on errors
- ✅ Configurable event handlers (Console, File, Log, Custom)
- ✅ Stream statistics and performance metrics
- ✅ Tool execution during streaming
- ✅ Error handling and recovery

## Quick Start

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Streaming\StreamingLoop;
use ClaudeAgents\Streaming\Handlers\ConsoleHandler;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create agent with streaming
$agent = Agent::create($client)
    ->withTool($yourTool)
    ->maxIterations(5);

// Add streaming loop with console handler
$streamingLoop = new StreamingLoop();
$streamingLoop->addHandler(new ConsoleHandler(newline: true));

$agent->withLoopStrategy($streamingLoop);

// Run and watch real-time output
$result = $agent->run('Your task here');
```

## Architecture

### Core Components

```
┌─────────────────┐
│  StreamingLoop  │ ← Main loop strategy with streaming support
└────────┬────────┘
         │
         ├─► StreamBuffer    ← Accumulates streamed content
         ├─► StreamEvent     ← Represents individual stream events
         └─► Handlers/       ← Process stream events
             ├─ ConsoleHandler
             ├─ FileHandler
             ├─ LogHandler
             └─ CallbackHandler
```

### StreamingLoop

The main loop strategy that handles streaming responses from Claude API.

**Features:**
- Implements `LoopStrategyInterface`
- Automatic fallback to non-streaming
- Multiple handler support
- Iteration and tool execution callbacks
- Token usage tracking
- Error handling with logging

**Usage:**

```php
$loop = new StreamingLoop($logger);

// Add handlers
$loop->addHandler(new ConsoleHandler());
$loop->addHandler(new FileHandler('/tmp/stream.log'));

// Add callbacks
$loop->onStream(function($event) {
    // Handle each stream event
    echo $event->getText();
});

$loop->onIteration(function($iteration, $response, $context) {
    // Called after each iteration
    echo "Iteration {$iteration} complete\n";
});

$loop->onToolExecution(function($tool, $input, $result) {
    // Called when tools are executed
    echo "Tool '{$tool}' executed\n";
});
```

### StreamEvent

Represents a single streaming event.

**Event Types:**
- `TYPE_TEXT` - Text content
- `TYPE_CONTENT_BLOCK_START` - Content block started
- `TYPE_CONTENT_BLOCK_DELTA` - Content delta received
- `TYPE_CONTENT_BLOCK_STOP` - Content block finished
- `TYPE_MESSAGE_START` - Message started
- `TYPE_MESSAGE_DELTA` - Message metadata updated
- `TYPE_MESSAGE_STOP` - Message finished
- `TYPE_TOOL_USE` - Tool invocation
- `TYPE_ERROR` - Error occurred
- `TYPE_PING` - Heartbeat/keepalive
- `TYPE_METADATA` - Additional metadata

**Factory Methods:**

```php
// Text event
$event = StreamEvent::text('Hello world');

// Delta event (streaming chunk)
$event = StreamEvent::delta('chunk');

// Tool use event
$event = StreamEvent::toolUse(['name' => 'calculator', 'input' => ['a' => 5]]);

// Error event
$event = StreamEvent::error('Connection failed', ['code' => 500]);

// Metadata event
$event = StreamEvent::metadata(['model' => 'claude-3', 'tokens' => 150]);

// Ping event
$event = StreamEvent::ping();
```

**Type Checking:**

```php
if ($event->isText()) {
    echo $event->getText();
}

if ($event->isError()) {
    $errorData = $event->getData();
    // Handle error
}

if ($event->isToolUse()) {
    $toolData = $event->getToolUse();
    // Process tool use
}

if ($event->isPing()) {
    // Heartbeat received
}
```

### StreamBuffer

Accumulates streamed content into coherent blocks with statistics.

**Basic Usage:**

```php
$buffer = new StreamBuffer();

// Add text chunks
$buffer->addText('Hello ');
$buffer->addText('World');

// Get accumulated text
echo $buffer->getText(); // "Hello World"

// Finish current block
$buffer->finishBlock();

// Add non-text blocks
$buffer->addBlock([
    'type' => 'tool_use',
    'name' => 'calculator',
    'input' => ['a' => 5, 'b' => 3]
]);

// Get all blocks
$blocks = $buffer->getBlocks();

// Get statistics
$stats = $buffer->getStatistics();
/*
[
    'total_chunks' => 2,
    'total_bytes' => 11,
    'duration_seconds' => 0.123,
    'bytes_per_second' => 89.43,
    'chunks_per_second' => 16.26,
    'average_chunk_size' => 5.5
]
*/

// Clear buffer
$buffer->clear();
```

## Handlers

Handlers process stream events as they arrive. Multiple handlers can be active simultaneously.

### ConsoleHandler

Outputs stream to console (stdout).

```php
use ClaudeAgents\Streaming\Handlers\ConsoleHandler;

// Basic usage
$handler = new ConsoleHandler();

// With prefix
$handler = new ConsoleHandler(prefix: '>> ');

// With newlines on periods
$handler = new ConsoleHandler(newline: true);

// Both
$handler = new ConsoleHandler(
    newline: true,
    prefix: '[AI] '
);
```

### FileHandler

Writes stream to a file.

```php
use ClaudeAgents\Streaming\Handlers\FileHandler;

// Basic usage (overwrite)
$handler = new FileHandler('/path/to/output.txt');

// Append mode
$handler = new FileHandler('/path/to/output.txt', append: true);

// With timestamps
$handler = new FileHandler(
    '/path/to/output.txt',
    append: true,
    includeTimestamps: true
);

// With event types
$handler = new FileHandler(
    '/path/to/output.txt',
    append: true,
    includeTimestamps: true,
    includeEventTypes: true
);
```

**Output Format:**
```
[2024-12-15 10:30:45] [text] Hello world
[2024-12-15 10:30:46] [tool_use] TOOL_USE: {"name":"calculator"...}
[2024-12-15 10:30:47] [error] ERROR: Connection timeout
```

### LogHandler

Logs stream events using PSR-3 logger.

```php
use ClaudeAgents\Streaming\Handlers\LogHandler;
use Psr\Log\LogLevel;

$logger = /* your PSR-3 logger */;

// Basic usage
$handler = new LogHandler($logger);

// Custom log levels
$handler = new LogHandler(
    $logger,
    textLogLevel: LogLevel::INFO,
    errorLogLevel: LogLevel::CRITICAL,
    toolUseLogLevel: LogLevel::DEBUG
);

// Log each text chunk
$handler = new LogHandler(
    $logger,
    logEachChunk: true
);
```

### CallbackHandler

Custom callback for each event.

```php
use ClaudeAgents\Streaming\Handlers\CallbackHandler;

$handler = new CallbackHandler(function($event) {
    if ($event->isText()) {
        // Send to WebSocket
        $websocket->send($event->getText());
    }
    
    if ($event->isError()) {
        // Log error
        error_log($event->getText());
    }
});
```

### Custom Handlers

Create your own by implementing `StreamHandlerInterface`:

```php
use ClaudeAgents\Contracts\StreamHandlerInterface;
use ClaudeAgents\Streaming\StreamEvent;

class WebSocketHandler implements StreamHandlerInterface
{
    public function __construct(private $connection) {}
    
    public function handle(StreamEvent $event): void
    {
        if ($event->isText()) {
            $this->connection->send(json_encode([
                'type' => 'text',
                'content' => $event->getText(),
                'timestamp' => $event->getTimestamp()
            ]));
        }
    }
    
    public function getName(): string
    {
        return 'websocket';
    }
}

// Use it
$handler = new WebSocketHandler($wsConnection);
$streamingLoop->addHandler($handler);
```

## Advanced Usage

### Multiple Handlers

Chain multiple handlers for different outputs:

```php
$loop = new StreamingLoop();

// Console for immediate feedback
$loop->addHandler(new ConsoleHandler(prefix: '🤖 '));

// File for persistence
$loop->addHandler(new FileHandler('/tmp/conversation.log', append: true));

// Logger for monitoring
$loop->addHandler(new LogHandler($logger, logEachChunk: true));

// Custom WebSocket for real-time UI
$loop->addHandler(new WebSocketHandler($ws));
```

### Stream Statistics

Track performance metrics:

```php
$buffer = new StreamBuffer();

// ... streaming happens ...

$stats = $buffer->getStatistics();

echo "Received {$stats['total_chunks']} chunks\n";
echo "Total: {$stats['total_bytes']} bytes\n";
echo "Speed: {$stats['bytes_per_second']} bytes/sec\n";
echo "Duration: {$stats['duration_seconds']} seconds\n";
```

### Error Handling

Streaming automatically falls back to non-streaming on errors:

```php
$loop = new StreamingLoop($logger);

try {
    $context = $loop->execute($context);
} catch (\Exception $e) {
    // Stream failed and fallback also failed
    $logger->error('Complete failure: ' . $e->getMessage());
}
```

### Conditional Streaming

Enable streaming based on conditions:

```php
$useStreaming = $isInteractive && !$isBatchJob;

if ($useStreaming) {
    $loop = new StreamingLoop();
    $loop->addHandler(new ConsoleHandler(newline: true));
    $agent->withLoopStrategy($loop);
} else {
    // Use default ReActLoop
}
```

## Performance Considerations

### Network Latency

Streaming shows first tokens faster but may have slightly higher total latency:

```
Non-streaming: [  wait...  ][all tokens]
Streaming:     [token][token][token]...
```

**Best for:**
- Interactive applications
- Long responses
- User engagement

**Avoid for:**
- Batch processing
- Short responses
- High-frequency requests

### Buffer Management

```php
// Process in chunks
$buffer = new StreamBuffer();

$loop->onStream(function($event) use ($buffer) {
    $buffer->addText($event->getText());
    
    // Process every 100 bytes
    if (strlen($buffer->getText()) >= 100) {
        processChunk($buffer->getText());
        $buffer->clear();
    }
});
```

### Handler Performance

Handlers are called for **every event**. Keep handlers fast:

```php
// ❌ BAD - slow handler
$handler = new CallbackHandler(function($event) {
    sleep(1); // Blocks streaming!
    // Heavy processing...
});

// ✅ GOOD - async processing
$queue = new Queue();
$handler = new CallbackHandler(function($event) use ($queue) {
    $queue->push($event); // Fast enqueue
});

// Process queue separately
```

## Testing

### Unit Tests

```php
use ClaudeAgents\Streaming\StreamEvent;
use ClaudeAgents\Streaming\StreamBuffer;

public function testStreaming(): void
{
    $buffer = new StreamBuffer();
    
    $event1 = StreamEvent::text('Hello ');
    $event2 = StreamEvent::text('World');
    
    $buffer->addText($event1->getText());
    $buffer->addText($event2->getText());
    
    $this->assertEquals('Hello World', $buffer->getText());
}
```

### Mock Streaming

```php
// Create mock stream events
$events = [
    StreamEvent::text('Hello '),
    StreamEvent::text('World'),
    StreamEvent::toolUse(['name' => 'calculator']),
    StreamEvent::text('The answer is 42'),
];

// Test handler
$output = [];
$handler = new CallbackHandler(function($event) use (&$output) {
    $output[] = $event->getText();
});

foreach ($events as $event) {
    $handler->handle($event);
}
```

## Best Practices

### 1. Choose Appropriate Handlers

```php
// Development: Console
$loop->addHandler(new ConsoleHandler());

// Production: File + Logger
$loop->addHandler(new FileHandler('/var/log/agent.log', append: true));
$loop->addHandler(new LogHandler($logger));

// Web: Custom WebSocket/SSE
$loop->addHandler(new WebSocketHandler($ws));
```

### 2. Handle Errors Gracefully

```php
$loop->onStream(function($event) {
    if ($event->isError()) {
        // Log but don't crash
        error_log('Stream error: ' . $event->getText());
        // Optionally notify user
        notifyUser('Temporary issue, retrying...');
    }
});
```

### 3. Monitor Performance

```php
$loop->onIteration(function($iteration, $response, $context) {
    $usage = $context->getTokenUsage();
    
    if ($usage['total'] > 50000) {
        trigger_error('High token usage detected', E_USER_WARNING);
    }
});
```

### 4. Clean Up Resources

```php
// Handlers with resources should clean up
class DatabaseHandler implements StreamHandlerInterface
{
    private $connection;
    
    public function __destruct()
    {
        // Close connection when done
        $this->connection->close();
    }
}
```

## Examples

### Real-time Chat Interface

```php
$loop = new StreamingLoop();

$loop->addHandler(new CallbackHandler(function($event) {
    if ($event->isText()) {
        // Send to browser via SSE
        echo "data: " . json_encode([
            'type' => 'message',
            'content' => $event->getText()
        ]) . "\n\n";
        flush();
    }
}));

$agent->withLoopStrategy($loop);
$result = $agent->run($userInput);
```

### Progress Tracking

```php
$buffer = new StreamBuffer();

$loop = new StreamingLoop();
$loop->onStream(function($event) use ($buffer) {
    $buffer->addText($event->getText());
    
    // Update progress bar
    $stats = $buffer->getStatistics();
    updateProgress([
        'bytes' => $stats['total_bytes'],
        'speed' => $stats['bytes_per_second'],
        'chunks' => $stats['total_chunks']
    ]);
});
```

### Multi-format Logging

```php
$loop = new StreamingLoop();

// Human-readable console
$loop->addHandler(new ConsoleHandler(
    newline: true,
    prefix: '[Agent] '
));

// Structured file log
$loop->addHandler(new FileHandler(
    '/var/log/agent.jsonl',
    append: true,
    includeTimestamps: true,
    includeEventTypes: true
));

// System logger
$loop->addHandler(new LogHandler(
    $systemLogger,
    logEachChunk: false // Only log important events
));
```

## Troubleshooting

### Stream Not Working

1. **Check API support**: Ensure Claude API endpoint supports streaming
2. **Verify client version**: Update Claude PHP SDK to latest version
3. **Check fallback**: Streaming automatically falls back - check logs

```php
$loop = new StreamingLoop($logger);
// Check logs for "Streaming failed, falling back to non-streaming"
```

### Slow Streaming

1. **Handler performance**: Profile handler execution time
2. **Network latency**: Check connection to Claude API
3. **Buffer size**: Reduce buffer processing frequency

### Missing Events

1. **Event types**: Ensure handling all relevant event types
2. **Error events**: Check for `TYPE_ERROR` events
3. **Buffer flushing**: Call `finishBlock()` to finalize

## See Also

- [ReAct Loop](./ReActLoop.md) - Standard non-streaming loop
- [Agent Configuration](./Configuration.md) - Agent setup
- [Tools](./Tools.md) - Tool integration
- [Examples](../examples/streaming_example.php) - Working code

## API Reference

### StreamingLoop

```php
class StreamingLoop implements LoopStrategyInterface
{
    public function __construct(?LoggerInterface $logger = null);
    public function addHandler(StreamHandlerInterface $handler): self;
    public function onStream(callable $callback): self;
    public function onIteration(callable $callback): self;
    public function onToolExecution(callable $callback): self;
    public function execute(AgentContext $context): AgentContext;
    public function getName(): string;
}
```

### StreamEvent

```php
class StreamEvent
{
    // Constants
    public const TYPE_TEXT = 'text';
    public const TYPE_CONTENT_BLOCK_DELTA = 'content_block_delta';
    public const TYPE_TOOL_USE = 'tool_use';
    public const TYPE_ERROR = 'error';
    public const TYPE_METADATA = 'metadata';
    public const TYPE_PING = 'ping';
    // ... more types
    
    // Factory methods
    public static function text(string $text): self;
    public static function delta(string $text): self;
    public static function toolUse(array $toolData): self;
    public static function error(string $message, array $errorData = []): self;
    public static function metadata(array $metadata): self;
    public static function ping(): self;
    
    // Type checking
    public function isText(): bool;
    public function isToolUse(): bool;
    public function isError(): bool;
    public function isMetadata(): bool;
    public function isPing(): bool;
    
    // Accessors
    public function getType(): string;
    public function getText(): string;
    public function getData(): array;
    public function getTimestamp(): int;
    public function getToolUse(): ?array;
    public function toArray(): array;
}
```

### StreamBuffer

```php
class StreamBuffer
{
    public function addText(string $text): void;
    public function finishBlock(): void;
    public function addBlock(array $block): void;
    public function getText(): string;
    public function getBlocks(): array;
    public function getBlockCount(): int;
    public function clear(): void;
    public function isEmpty(): bool;
    public function getStatistics(): array;
}
```

### StreamHandlerInterface

```php
interface StreamHandlerInterface
{
    public function handle(StreamEvent $event): void;
    public function getName(): string;
}
```

