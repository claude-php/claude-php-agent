# Contract Interfaces Documentation

This document provides an overview of all contract interfaces in the claude-php-agent library.

## Core Contracts

### AgentInterface
**Location:** `src/Contracts/AgentInterface.php`

Base interface for all agent implementations.

**Methods:**
- `run(string $task): AgentResult` - Execute agent with a task
- `getName(): string` - Get agent identifier

---

### ToolInterface
**Location:** `src/Contracts/ToolInterface.php`

Interface for tool implementations that agents can use.

**Methods:**
- `getName(): string` - Get tool name
- `getDescription(): string` - Get tool description
- `getInputSchema(): array` - Get JSON schema for tool inputs
- `execute(array $input): ToolResultInterface` - Execute the tool
- `toDefinition(): array` - Convert to API-compatible format

---

### ToolResultInterface
**Location:** `src/Contracts/ToolResultInterface.php`

Interface for tool execution results.

**Methods:**
- `getContent(): string` - Get result content
- `isError(): bool` - Check if result is an error
- `toApiFormat(string $toolUseId): array` - Convert to API format

---

## State Management

### MemoryInterface
**Location:** `src/Contracts/MemoryInterface.php`

Interface for agent memory and state management.

**Methods:**
- `set(string $key, mixed $value): void` - Store value
- `get(string $key, mixed $default = null): mixed` - Retrieve value
- `has(string $key): bool` - Check if key exists
- `forget(string $key): void` - Remove key
- `all(): array` - Get all data
- `clear(): void` - Clear all memory

---

### SerializableInterface
**Location:** `src/Contracts/SerializableInterface.php`

Interface for agent state serialization and persistence.

**Methods:**
- `toArray(): array` - Serialize state to array
- `fromArray(array $data): void` - Restore from array
- `toJson(): string` - Serialize to JSON
- `fromJson(string $json): void` - Restore from JSON
- `getStateId(): string` - Get unique state identifier
- `getVersion(): string` - Get serialization format version

**Use Cases:**
- Long-running tasks spanning multiple requests
- Checkpointing and recovery
- State sharing across distributed systems

---

### CacheInterface
**Location:** `src/Contracts/CacheInterface.php`

Interface for caching implementations.

**Methods:**
- `get(string $key): mixed` - Get cached value
- `set(string $key, mixed $value, int $ttl = 0): void` - Cache value with TTL
- `delete(string $key): void` - Delete cached value
- `has(string $key): bool` - Check if key exists
- `clear(): void` - Clear all cache

---

## Agent Patterns

### LoopStrategyInterface
**Location:** `src/Contracts/LoopStrategyInterface.php`

Interface for different agentic loop patterns (ReAct, Plan-Execute, Reflection, etc.).

**Methods:**
- `execute(AgentContext $context): AgentContext` - Execute the strategy
- `getName(): string` - Get strategy name

**Supported Patterns:**
- ReAct: Reason-Act-Observe loop
- PlanExecute: Plan then execute steps
- Reflection: Generate-Reflect-Refine loop

---

### ChainInterface
**Location:** `src/Chains/Contracts/ChainInterface.php`

Interface for composable chain implementations.

**Methods:**
- `run(ChainInput $input): ChainOutput` - Run with typed objects
- `invoke(array $input): array` - Run with raw arrays
- `getInputSchema(): array` - Get input schema
- `getOutputSchema(): array` - Get output schema
- `validateInput(ChainInput $input): bool` - Validate input

**Chain Types:**
- LLMChain: Direct LLM calls
- SequentialChain: Step-by-step execution
- ParallelChain: Concurrent execution
- RouterChain: Conditional routing
- TransformChain: Data transformation

---

## Behavioral Contracts

### ConversationalInterface
**Location:** `src/Contracts/ConversationalInterface.php`

Extends `AgentInterface` for dialog management.

**Methods:**
- `startConversation(?string $sessionId = null): Session` - Start new conversation
- `turn(string $userInput, ?string $sessionId = null): string` - Process dialog turn
- `getSession(string $sessionId): ?Session` - Get conversation session

---

### CollaborativeInterface
**Location:** `src/Contracts/CollaborativeInterface.php`

Extends `AgentInterface` for multi-agent collaboration.

**Methods:**
- `sendMessage(Message $message): void` - Send message to another agent
- `receiveMessage(Message $message): void` - Receive message from another agent
- `getAgentId(): string` - Get unique agent identifier
- `getCapabilities(): array` - Get agent capabilities/skills

---

### MonitorableInterface
**Location:** `src/Contracts/MonitorableInterface.php`

Interface for components that can be monitored.

**Methods:**
- `getMetrics(): array<Metric>` - Get current metrics
- `getName(): string` - Get monitorable name/identifier

---

### SchedulableInterface
**Location:** `src/Contracts/SchedulableInterface.php`

Interface for schedulable tasks.

**Methods:**
- `execute(): AgentResult` - Execute scheduled task
- `getTaskId(): string` - Get task identifier

---

### ObserverInterface
**Location:** `src/Contracts/ObserverInterface.php`

Interface for the Observer pattern (event handling).

**Methods:**
- `update(string $event, array $data = []): void` - Handle event notification
- `getId(): string` - Get observer identifier
- `getEvents(): array` - Get events to observe (empty = all events)

**Use Cases:**
- Logging and monitoring
- Event-driven workflows
- Reactive behavior

---

## RAG & Document Processing

### RetrieverInterface
**Location:** `src/Contracts/RetrieverInterface.php`

Interface for document retrieval strategies.

**Methods:**
- `retrieve(string $query, int $topK = 3): array` - Retrieve relevant documents
- `setChunks(array $chunks): void` - Set document store to search

---

### DocumentStoreInterface
**Location:** `src/Contracts/DocumentStoreInterface.php`

Interface for document storage.

**Methods:**
- `add(string $id, string $title, string $content, array $metadata = []): void` - Add document
- `get(string $id): ?array` - Get document by ID
- `has(string $id): bool` - Check if document exists
- `remove(string $id): void` - Remove document
- `all(): array` - Get all documents
- `count(): int` - Get document count
- `clear(): void` - Clear all documents

---

### ChunkerInterface
**Location:** `src/Contracts/ChunkerInterface.php`

Interface for text chunking strategies.

**Methods:**
- `chunk(string $text): array` - Split text into chunks
- `getChunkSize(): int` - Get chunk size
- `getOverlap(): int` - Get overlap size

---

### EmbeddingInterface
**Location:** `src/Contracts/EmbeddingInterface.php`

Interface for vector embedding generation.

**Methods:**
- `embed(string $text): array` - Generate single embedding
- `embedBatch(array $texts): array` - Generate multiple embeddings
- `similarity(array $embedding1, array $embedding2): float` - Calculate cosine similarity
- `getDimension(): int` - Get embedding dimension
- `getModel(): string` - Get model identifier

**Use Cases:**
- Semantic search
- Similarity comparison
- RAG (Retrieval-Augmented Generation)

---

## Multi-Agent Systems

### DebateAgentInterface
**Location:** `src/Contracts/DebateAgentInterface.php`

Interface for agents participating in debates.

**Methods:**
- `getName(): string` - Get agent name/role
- `getPerspective(): string` - Get agent's stance
- `getSystemPrompt(): string` - Get system prompt
- `speak(string $topic, string $context = '', string $instruction = ''): string` - Provide statement

---

## Validation & Streaming

### ValidatorInterface
**Location:** `src/Contracts/ValidatorInterface.php`

Interface for input/output validation.

**Methods:**
- `validate(mixed $data, array $rules = []): bool` - Validate data (throws on error)
- `getErrors(): array` - Get validation errors
- `isValid(mixed $data, array $rules = []): bool` - Check validity without exceptions
- `addRule(string $name, callable $callback): void` - Add custom rule
- `getSchema(): array` - Get validation schema

**Use Cases:**
- Input sanitization
- Schema validation
- Business rule enforcement

---

### StreamHandlerInterface
**Location:** `src/Contracts/StreamHandlerInterface.php`

Interface for streaming event handlers.

**Methods:**
- `handle(StreamEvent $event): void` - Handle stream event
- `getName(): string` - Get handler name

---

## Interface Hierarchy

```
AgentInterface
├── ConversationalInterface (extends AgentInterface)
└── CollaborativeInterface (extends AgentInterface)

Independent Interfaces:
├── ToolInterface
├── ToolResultInterface
├── MemoryInterface
├── SerializableInterface
├── CacheInterface
├── LoopStrategyInterface
├── ChainInterface
├── MonitorableInterface
├── SchedulableInterface
├── ObserverInterface
├── RetrieverInterface
├── DocumentStoreInterface
├── ChunkerInterface
├── EmbeddingInterface
├── DebateAgentInterface
├── ValidatorInterface
└── StreamHandlerInterface
```

## Implementation Guidelines

### SOLID Principles
All interfaces follow SOLID principles:
- **Single Responsibility**: Each interface has one clear purpose
- **Open/Closed**: Extensible without modification
- **Liskov Substitution**: Implementations are interchangeable
- **Interface Segregation**: Focused, minimal interfaces
- **Dependency Inversion**: Depend on abstractions, not implementations

### Type Safety
- All methods use PHP type hints
- Return types are explicitly declared
- Array types are documented with PHPDoc annotations

### Documentation
- All interfaces have class-level docblocks
- All methods have parameter and return documentation
- Use cases and patterns are described where relevant

## Usage Examples

### Creating a Custom Agent

```php
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\AgentResult;

class MyCustomAgent implements AgentInterface
{
    public function run(string $task): AgentResult
    {
        // Your implementation
    }

    public function getName(): string
    {
        return 'my-custom-agent';
    }
}
```

### Creating a Custom Tool

```php
use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Contracts\ToolResultInterface;

class MyTool implements ToolInterface
{
    public function execute(array $input): ToolResultInterface
    {
        // Your implementation
    }
    
    // ... other required methods
}
```

### Creating an Observable Agent

```php
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Contracts\ObserverInterface;

class ObservableAgent implements AgentInterface
{
    private array $observers = [];
    
    public function attach(ObserverInterface $observer): void
    {
        $this->observers[] = $observer;
    }
    
    protected function notify(string $event, array $data = []): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($event, $data);
        }
    }
}
```

## Contributing

When adding new interfaces:
1. Place in appropriate location (`src/Contracts/` or `src/Chains/Contracts/`)
2. Use strict types declaration
3. Add comprehensive docblocks
4. Document use cases in this file
5. Follow existing naming conventions
6. Ensure SOLID principles are maintained

## See Also

- [Agent Selection Guide](agent-selection-guide.md)
- [Chain Composition](../CHAIN_COMPOSITION_IMPLEMENTATION.md)
- [Adaptive Agent Service](adaptive-agent-service.md)

