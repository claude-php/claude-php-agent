# Event Reference

Complete reference for all flow event types in the Streaming Flow Execution system.

## Event Structure

All events follow this structure:

```php
class FlowEvent
{
    public readonly string $type;              // Event type constant
    public readonly array $data;               // Event payload
    public readonly float $timestamp;          // Unix timestamp with microseconds
    public readonly ?string $id;               // Optional event ID
}
```

## Event Categories

### Flow Lifecycle Events

Events for overall flow execution lifecycle.

#### `FLOW_STARTED`

Emitted when flow execution begins.

**Type:** `'flow.started'`

**Data:**
```php
[
    'agent' => 'agent_name',     // Agent identifier
    'input' => 'task...',        // Input task
    'options' => [...]           // Execution options
]
```

**Example:**
```php
FlowEvent::flowStarted([
    'agent' => 'react_agent',
    'input' => 'Calculate 2+2'
]);
```

---

#### `FLOW_COMPLETED`

Emitted when flow execution completes successfully.

**Type:** `'flow.completed'`

**Data:**
```php
[
    'agent' => 'agent_name',     // Agent identifier
    'duration' => 2.5,           // Execution duration in seconds
    'iterations' => 3            // Total iterations
]
```

---

#### `FLOW_FAILED`

Emitted when flow execution fails.

**Type:** `'flow.failed'`

**Data:**
```php
[
    'error' => 'error message',  // Error description
    'agent' => 'agent_name',     // Agent identifier
    'trace' => '...'             // Optional stack trace
]
```

**Example:**
```php
FlowEvent::flowFailed('Max iterations exceeded', [
    'agent' => 'react_agent'
]);
```

---

### Token Streaming Events

Events for real-time token streaming from LLMs.

#### `TOKEN_RECEIVED`

Emitted for each token received from the LLM.

**Type:** `'token.received'`

**Data:**
```php
[
    'token' => 'text',          // Token text
    'iteration' => 1            // Current iteration number
]
```

**Example:**
```php
FlowEvent::token('Hello', ['iteration' => 1]);
```

**Usage:**
```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if ($event['type'] === 'token') {
        echo $event['data']['token'];  // Stream to output
    }
}
```

---

#### `TOKEN_CHUNK`

Emitted for batched token chunks (when buffering is used).

**Type:** `'token.chunk'`

**Data:**
```php
[
    'chunk' => 'multiple tokens',  // Chunk text
    'size' => 15,                  // Chunk size
    'iteration' => 1               // Current iteration
]
```

---

### Iteration Events

Events for agent loop iterations.

#### `ITERATION_STARTED`

Emitted when a new iteration begins.

**Type:** `'iteration.started'`

**Data:**
```php
[
    'iteration' => 1            // Iteration number (1-indexed)
]
```

**Example:**
```php
FlowEvent::iterationStarted(1);
```

---

#### `ITERATION_COMPLETED`

Emitted when an iteration completes.

**Type:** `'iteration.completed'`

**Data:**
```php
[
    'iteration' => 1,           // Iteration number
    'tokens' => [               // Token usage
        'input' => 150,
        'output' => 80
    ],
    'duration' => 1.2          // Iteration duration in seconds
]
```

**Example:**
```php
FlowEvent::iterationCompleted(1, [
    'tokens' => ['input' => 150, 'output' => 80]
]);
```

---

#### `ITERATION_FAILED`

Emitted when an iteration fails.

**Type:** `'iteration.failed'`

**Data:**
```php
[
    'iteration' => 1,           // Iteration number
    'error' => 'error message'  // Error description
]
```

---

### Tool Execution Events

Events for tool/function execution.

#### `TOOL_EXECUTION_STARTED`

Emitted when tool execution begins.

**Type:** `'tool.started'`

**Data:**
```php
[
    'tool' => 'calculator',     // Tool name
    'input' => [                // Tool input parameters
        'operation' => 'add',
        'a' => 5,
        'b' => 3
    ]
]
```

**Example:**
```php
FlowEvent::toolStarted('calculator', [
    'operation' => 'add',
    'a' => 5,
    'b' => 3
]);
```

---

#### `TOOL_EXECUTION_COMPLETED`

Emitted when tool execution completes.

**Type:** `'tool.completed'`

**Data:**
```php
[
    'tool' => 'calculator',     // Tool name
    'result' => 'Result: 8',    // Tool output
    'is_error' => false,        // Whether execution failed
    'duration' => 0.5           // Execution duration
]
```

**Example:**
```php
FlowEvent::toolCompleted('calculator', 'Result: 8', [
    'duration' => 0.5
]);
```

---

#### `TOOL_EXECUTION_FAILED`

Emitted when tool execution fails.

**Type:** `'tool.failed'`

**Data:**
```php
[
    'tool' => 'calculator',     // Tool name
    'error' => 'error message', // Error description
    'input' => [...]            // Original input
]
```

---

### Progress Events

Events for execution progress tracking.

#### `PROGRESS_UPDATE`

Emitted periodically with progress information.

**Type:** `'progress.update'`

**Data:**
```php
[
    'percent' => 45.5,                    // Progress percentage (0-100)
    'current_iteration' => 5,             // Current iteration
    'total_iterations' => 11,             // Total expected iterations
    'current_step' => 'tool_execution',   // Current step name
    'duration' => 5.2,                    // Elapsed time in seconds
    'estimated_remaining' => 6.3          // Estimated remaining time
]
```

**Example:**
```php
FlowEvent::progress(45.5, [
    'current_iteration' => 5,
    'total_iterations' => 11
]);
```

---

#### `STEP_STARTED` / `STEP_COMPLETED`

Emitted when named steps start and complete.

**Type:** `'step.started'` / `'step.completed'`

**Data:**
```php
[
    'step' => 'step_name',      // Step identifier
    'timestamp' => 1234567.89   // Timestamp
]
```

---

### Error and Info Events

#### `ERROR`

Emitted for error conditions.

**Type:** `'error'`

**Data:**
```php
[
    'message' => 'error message',  // Error message
    'code' => 'ERROR_CODE',        // Optional error code
    'context' => [...]             // Optional context
]
```

**Example:**
```php
FlowEvent::error('Tool execution failed', [
    'tool' => 'calculator'
]);
```

---

#### `WARNING`

Emitted for warning conditions.

**Type:** `'warning'`

**Data:**
```php
[
    'message' => 'warning message',  // Warning message
    'context' => [...]               // Optional context
]
```

---

#### `INFO`

Emitted for informational messages.

**Type:** `'info'`

**Data:**
```php
[
    'message' => 'info message',  // Info message
    'context' => [...]            // Optional context
]
```

---

### Langflow Compatibility Events

Events compatible with Langflow's event system.

#### `MESSAGE_ADDED`

Emitted when a message is added to the conversation.

**Type:** `'add_message'`

**Data:**
```php
[
    'role' => 'assistant',      // Message role
    'content' => 'message...',  // Message content
    'timestamp' => 1234567.89   // Timestamp
]
```

---

#### `MESSAGE_REMOVED`

Emitted when a message is removed.

**Type:** `'remove_message'`

**Data:**
```php
[
    'message_id' => 'msg_123',  // Message identifier
    'timestamp' => 1234567.89   // Timestamp
]
```

---

#### `VERTEX_STARTED` / `VERTEX_COMPLETED`

Emitted for graph vertex/node execution (Langflow compatibility).

**Type:** `'vertex.started'` / `'end_vertex'`

**Data:**
```php
[
    'vertex_id' => 'v_123',     // Vertex identifier
    'vertex_name' => 'LLM',     // Vertex name
    'data' => [...]             // Vertex data
]
```

---

#### `BUILD_STARTED` / `BUILD_COMPLETED`

Emitted for build lifecycle (Langflow compatibility).

**Type:** `'build_start'` / `'build_end'`

**Data:**
```php
[
    'build_id' => 'b_123',      // Build identifier
    'timestamp' => 1234567.89   // Timestamp
]
```

---

## Event Type Checking

FlowEvent provides helper methods for type checking:

```php
$event->isToken();       // true for TOKEN_RECEIVED, TOKEN_CHUNK
$event->isFlowEvent();   // true for FLOW_STARTED, FLOW_COMPLETED, etc.
$event->isError();       // true for ERROR, FLOW_FAILED
$event->isProgress();    // true for PROGRESS_UPDATE
$event->isToolEvent();   // true for TOOL_EXECUTION_*
```

## Output Formats

### Array Format

```php
$event->toArray();
// Returns:
[
    'type' => 'token.received',
    'data' => ['token' => 'Hello'],
    'timestamp' => 1234567.89,
    'id' => 'token.received-abc123'
]
```

### JSON Format

```php
$event->toJson();
// Returns: '{"type":"token.received","data":{"token":"Hello"},...}'
```

### SSE Format

```php
$event->toSSE();
// Returns:
// event: token.received
// data: {"type":"token.received","data":{"token":"Hello"},...}
//
```

## Usage Patterns

### Filtering Events

```php
foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    // Filter by type
    if ($event['type'] === 'token') {
        handleToken($event['data']);
    }
    
    // Filter by category
    if (str_starts_with($event['type'], 'tool.')) {
        handleToolEvent($event);
    }
}
```

### Event Handlers

```php
$handlers = [
    'token' => fn($data) => echo $data['token'],
    'progress' => fn($data) => updateProgressBar($data['percent']),
    'error' => fn($data) => logError($data['error']),
];

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    if (isset($handlers[$event['type']])) {
        $handlers[$event['type']]($event['data']);
    }
}
```

### Event Collection

```php
$tokenEvents = [];
$toolEvents = [];

foreach ($executor->executeWithStreaming($agent, $task) as $event) {
    match ($event['type']) {
        'token' => $tokenEvents[] = $event,
        'tool.started', 'tool.completed' => $toolEvents[] = $event,
        default => null
    };
}
```

## Best Practices

1. **Always check event type** before accessing data
2. **Use type constants** instead of string literals
3. **Handle error events** explicitly
4. **Don't block on events** - keep handlers fast
5. **Log unexpected events** for debugging
6. **Use helper methods** for type checking

## See Also

- [Streaming Patterns](STREAMING.md)
- [Main Documentation](README.md)
- [Examples](../../examples/Execution/)
