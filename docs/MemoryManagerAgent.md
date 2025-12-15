# MemoryManagerAgent Documentation

## Overview

The `MemoryManagerAgent` is an intelligent memory management system that provides persistent storage, semantic search, and retrieval capabilities for AI agents. It uses Claude AI for semantic search while maintaining fast keyword-based indexing as a fallback, enabling agents to store and recall information efficiently.

## Features

- 🧠 **Intelligent Storage**: Store text-based memories with metadata and tags
- 🔍 **Semantic Search**: Uses Claude AI to find relevant memories by meaning
- ⚡ **Fast Keyword Search**: Automatic keyword indexing for instant fallback search
- 🏷️ **Tag-Based Organization**: Categorize and filter memories with tags
- ⏰ **Memory Expiration**: Set TTL (Time To Live) for automatic cleanup
- 📊 **Analytics**: Track access patterns and memory statistics
- 💾 **Import/Export**: Persistent storage with JSON export/import
- 🔄 **Multi-Agent Sharing**: Share knowledge between different agents
- 🎯 **Capacity Management**: Automatic eviction of oldest memories when limit reached

## Installation

The MemoryManagerAgent is included in the `claude-php-agent` package. Ensure you have the package installed:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\MemoryManagerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$memoryAgent = new MemoryManagerAgent($client);

// Store a memory
$id = $memoryAgent->store('PHP is a server-side scripting language');

// Retrieve by ID
$content = $memoryAgent->retrieve($id);

// Search semantically
$results = $memoryAgent->search('programming languages');
```

## Configuration

The MemoryManagerAgent accepts configuration options in its constructor:

```php
$memoryAgent = new MemoryManagerAgent($client, [
    'name' => 'my_memory_agent',      // Agent name
    'max_memories' => 10000,           // Maximum memories to store
    'default_ttl' => 3600.0,           // Default expiration in seconds (null = never)
    'logger' => $logger,               // PSR-3 logger instance
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'memory_manager'` | Unique name for the agent |
| `max_memories` | int | `10000` | Maximum number of memories before auto-eviction |
| `default_ttl` | float\|null | `null` | Default time-to-live in seconds (null = no expiration) |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

## Storing Memories

### Basic Storage

```php
// Simple storage
$id = $memoryAgent->store('Important information here');

// With metadata
$id = $memoryAgent->store(
    'User preferences updated',
    ['user_id' => 123, 'action' => 'update']
);

// With tags
$id = $memoryAgent->store(
    'PHP 8.3 released with new features',
    ['source' => 'news', 'date' => '2024-01-01'],
    ['php', 'release', 'programming']
);
```

### Memory Structure

Each stored memory contains:

- `id`: Unique identifier (e.g., `mem_abc123...`)
- `content`: The text content
- `metadata`: Array of key-value pairs
- `tags`: Array of string tags
- `timestamp`: Creation time
- `expires_at`: Expiration time (if TTL set)
- `access_count`: Number of times accessed
- `last_accessed`: Last access timestamp

## Retrieving Memories

### By ID

```php
// Get content only
$content = $memoryAgent->retrieve($id);
// Returns: string|null

// Get full memory data
$memory = $memoryAgent->retrieveFull($id);
// Returns: array with all fields
```

### By Tag

```php
$phpMemories = $memoryAgent->findByTag('php');
// Returns array of memory objects

foreach ($phpMemories as $memory) {
    echo $memory['content'];
    echo implode(', ', $memory['tags']);
}
```

### Semantic Search

```php
// Search with natural language
$results = $memoryAgent->search('How do I connect to a database?', 5);

foreach ($results as $memory) {
    echo "{$memory['id']}: {$memory['content']}\n";
}
```

The search method:
1. First attempts semantic search using Claude AI
2. Falls back to keyword search if LLM fails
3. Returns array of memory objects sorted by relevance

## Natural Language Interface

Use the `run()` method for natural language commands:

```php
// Store
$result = $memoryAgent->run('store: Important meeting notes from today');

// Retrieve
$result = $memoryAgent->run('retrieve: mem_abc123...');

// Search
$result = $memoryAgent->run('find information about databases');

// Tag search
$result = $memoryAgent->run('tag: php');

// Get statistics
$result = $memoryAgent->run('stats');

// List all tags
$result = $memoryAgent->run('tags');

// Forget a memory
$result = $memoryAgent->run('forget: mem_abc123...');
```

All commands return an `AgentResult` object with metadata:

```php
if ($result->isSuccess()) {
    echo $result->getAnswer();
    $metadata = $result->getMetadata();
}
```

## Memory Management

### Forgetting Memories

```php
$success = $memoryAgent->forget($id);

if ($success) {
    echo "Memory forgotten\n";
}
```

### Clearing All Memories

```php
$memoryAgent->clear();
```

### Memory Expiration

Set TTL at agent level:

```php
$agent = new MemoryManagerAgent($client, [
    'default_ttl' => 3600.0  // 1 hour
]);
```

Memories will automatically expire and be cleaned up when accessed.

### Capacity Management

When `max_memories` is reached, the oldest memory is automatically evicted:

```php
$agent = new MemoryManagerAgent($client, [
    'max_memories' => 100
]);

// Storing the 101st memory will evict the oldest
for ($i = 0; $i < 101; $i++) {
    $agent->store("Memory {$i}");
}
```

## Tags and Organization

### Working with Tags

```php
// Store with multiple tags
$agent->store('Content', [], ['tag1', 'tag2', 'tag3']);

// Find by tag
$tagged = $agent->findByTag('tag1');

// Get all unique tags
$allTags = $agent->getTags();
// Returns: ['tag1', 'tag2', 'tag3', ...]
```

### Tag Best Practices

- Use lowercase for consistency
- Use descriptive, specific tags
- Combine broad and specific tags
- Avoid too many tags per memory (3-5 is optimal)

Examples:
```php
$agent->store(
    'Laravel authentication tutorial',
    ['difficulty' => 'intermediate'],
    ['php', 'laravel', 'auth', 'tutorial', 'web']
);
```

## Import and Export

### Exporting Memories

```php
$memories = $agent->export();

// Save to JSON file
file_put_contents('memories.json', json_encode($memories, JSON_PRETTY_PRINT));
```

### Importing Memories

```php
// Load from JSON file
$data = json_decode(file_get_contents('memories.json'), true);

$count = $agent->import($data);
echo "Imported {$count} memories\n";
```

### Multi-Agent Knowledge Sharing

```php
// Agent 1 gathers information
$researchAgent = new MemoryManagerAgent($client);
$researchAgent->store('Research finding 1');
$researchAgent->store('Research finding 2');

// Export from Agent 1
$data = $researchAgent->export();

// Agent 2 imports the knowledge
$analysisAgent = new MemoryManagerAgent($client);
$analysisAgent->import($data);

// Agent 2 now has all of Agent 1's memories
```

## Statistics and Analytics

### Getting Statistics

```php
$stats = $agent->getStats();

print_r($stats);
/* Output:
[
    'total_memories' => 42,
    'total_size_bytes' => 12450,
    'total_access_count' => 156,
    'unique_tags' => 15,
    'memories_with_metadata' => 38,
    'memories_with_tags' => 40,
    'index_size' => 234,
]
*/
```

### Interpreting Statistics

- **total_memories**: Current number of stored memories
- **total_size_bytes**: Sum of all content lengths
- **total_access_count**: Total times memories have been accessed
- **unique_tags**: Number of distinct tags
- **memories_with_metadata**: Count with non-empty metadata
- **memories_with_tags**: Count with tags assigned
- **index_size**: Number of indexed keywords

### Performance Monitoring

```php
$startTime = microtime(true);
$agent->store('Test content');
$storeTime = microtime(true) - $startTime;

$startTime = microtime(true);
$agent->search('test query');
$searchTime = microtime(true) - $startTime;

echo "Store: " . ($storeTime * 1000) . "ms\n";
echo "Search: " . ($searchTime * 1000) . "ms\n";
```

## Use Cases

### 1. Conversational Context

```php
class ConversationManager
{
    public function __construct(
        private MemoryManagerAgent $memory
    ) {}
    
    public function remember(string $userId, string $message): void
    {
        $this->memory->store(
            $message,
            ['user_id' => $userId, 'type' => 'conversation'],
            ['conversation', "user_{$userId}"]
        );
    }
    
    public function recall(string $userId, int $limit = 10): array
    {
        return $this->memory->findByTag("user_{$userId}");
    }
}
```

### 2. Knowledge Base

```php
class KnowledgeBase
{
    private MemoryManagerAgent $memory;
    
    public function addArticle(string $title, string $content, array $topics): void
    {
        $this->memory->store(
            $content,
            ['title' => $title, 'type' => 'article'],
            array_merge(['article'], $topics)
        );
    }
    
    public function search(string $query): array
    {
        return $this->memory->search($query, 5);
    }
    
    public function findByTopic(string $topic): array
    {
        return $this->memory->findByTag($topic);
    }
}
```

### 3. Agent Learning System

```php
class LearningAgent
{
    private MemoryManagerAgent $shortTerm;
    private MemoryManagerAgent $longTerm;
    
    public function __construct(ClaudePhp $client)
    {
        // Short-term memory expires after 1 hour
        $this->shortTerm = new MemoryManagerAgent($client, [
            'name' => 'short_term',
            'default_ttl' => 3600.0,
            'max_memories' => 100,
        ]);
        
        // Long-term memory persists
        $this->longTerm = new MemoryManagerAgent($client, [
            'name' => 'long_term',
            'max_memories' => 10000,
        ]);
    }
    
    public function learn(string $experience, bool $important = false): void
    {
        $tags = ['experience'];
        
        // Store in short-term always
        $this->shortTerm->store($experience, [], $tags);
        
        // Important experiences go to long-term
        if ($important) {
            $this->longTerm->store($experience, ['important' => true], $tags);
        }
    }
    
    public function recall(string $query): array
    {
        // Search both memories
        $short = $this->shortTerm->search($query, 3);
        $long = $this->longTerm->search($query, 3);
        
        return array_merge($long, $short);
    }
    
    public function consolidate(): void
    {
        // Move frequently accessed short-term to long-term
        $memories = $this->shortTerm->export();
        
        foreach ($memories as $memory) {
            if ($memory['access_count'] > 5) {
                $this->longTerm->store(
                    $memory['content'],
                    $memory['metadata'],
                    $memory['tags']
                );
            }
        }
    }
}
```

### 4. Session State Management

```php
class SessionMemory
{
    private MemoryManagerAgent $memory;
    private string $sessionId;
    
    public function __construct(MemoryManagerAgent $memory, string $sessionId)
    {
        $this->memory = $memory;
        $this->sessionId = $sessionId;
    }
    
    public function setState(string $key, string $value): void
    {
        $this->memory->store(
            $value,
            ['session' => $this->sessionId, 'key' => $key],
            ['session', $this->sessionId]
        );
    }
    
    public function getState(): array
    {
        $memories = $this->memory->findByTag($this->sessionId);
        
        $state = [];
        foreach ($memories as $memory) {
            $key = $memory['metadata']['key'] ?? null;
            if ($key) {
                $state[$key] = $memory['content'];
            }
        }
        
        return $state;
    }
    
    public function clear(): void
    {
        $memories = $this->memory->findByTag($this->sessionId);
        
        foreach ($memories as $memory) {
            $this->memory->forget($memory['id']);
        }
    }
}
```

## Best Practices

### 1. Use Descriptive Content

```php
// ❌ Too vague
$agent->store('data');

// ✅ Clear and specific
$agent->store('User login event: user_id=123, timestamp=2024-01-15, ip=192.168.1.1');
```

### 2. Leverage Metadata

```php
// ✅ Good use of metadata
$agent->store(
    'Payment processed successfully',
    [
        'order_id' => '12345',
        'amount' => 99.99,
        'currency' => 'USD',
        'timestamp' => time(),
    ],
    ['payment', 'success', 'order']
);
```

### 3. Tag Strategically

```php
// ✅ Mix of broad and specific tags
$agent->store(
    'Tutorial on PHP dependency injection',
    ['level' => 'advanced'],
    ['php', 'tutorial', 'dependency-injection', 'oop', 'design-patterns']
);
```

### 4. Set Appropriate Limits

```php
// For high-volume systems
$agent = new MemoryManagerAgent($client, [
    'max_memories' => 50000,
    'default_ttl' => 86400.0,  // 24 hours
]);

// For constrained environments
$agent = new MemoryManagerAgent($client, [
    'max_memories' => 1000,
    'default_ttl' => 3600.0,  // 1 hour
]);
```

### 5. Regular Maintenance

```php
// Periodic stats checking
if ($agent->getStats()['total_memories'] > 8000) {
    // Consider export and archive
    $old = $agent->export();
    file_put_contents("archive_" . date('Y-m-d') . ".json", json_encode($old));
    $agent->clear();
}
```

### 6. Handle Search Gracefully

```php
$results = $agent->search($query, 5);

if (empty($results)) {
    // Try with different terms or fallback
    $results = $agent->search($alternativeQuery, 5);
}

if (empty($results)) {
    echo "No relevant memories found\n";
}
```

## API Reference

### Constructor

#### `__construct(ClaudePhp $client, array $options = [])`
Create a new MemoryManagerAgent instance.

### Storage Methods

#### `store(string $content, array $metadata = [], array $tags = []): string`
Store a memory and return its ID.

#### `forget(string $id): bool`
Remove a memory by ID. Returns true if successful.

#### `clear(): void`
Remove all memories.

### Retrieval Methods

#### `retrieve(string $id): ?string`
Get memory content by ID. Returns null if not found.

#### `retrieveFull(string $id): ?array`
Get complete memory data including metadata. Returns null if not found.

#### `findByTag(string $tag): array`
Get all memories with a specific tag.

#### `search(string $query, int $limit = 10): array`
Semantically search memories. Returns array of memory objects.

### Organization Methods

#### `getTags(): array`
Get all unique tags.

#### `getStats(): array`
Get memory statistics.

#### `getMemoryCount(): int`
Get current number of stored memories.

### Import/Export Methods

#### `export(): array`
Export all memories to an array.

#### `import(array $memories): int`
Import memories from an array. Returns count imported.

### Agent Interface

#### `run(string $task): AgentResult`
Execute a command using natural language.

#### `getName(): string`
Get the agent name.

## Troubleshooting

### Memory Not Found

```php
$content = $agent->retrieve($id);

if ($content === null) {
    // Check if expired or evicted
    $stats = $agent->getStats();
    error_log("Current memory count: {$stats['total_memories']}");
}
```

### Search Returns No Results

1. **Check memory count**: `$agent->getMemoryCount()`
2. **Verify LLM connection**: Check logs for API errors
3. **Try keyword search**: Use specific terms from content
4. **Use tag search**: `$agent->findByTag('specific-tag')`

### Performance Issues

```php
// For high-volume systems, use tags instead of search
$results = $agent->findByTag('specific-topic');  // Fast
// vs
$results = $agent->search('specific topic');     // Slower (LLM call)
```

### Memory Limit Reached

```php
// Export before clearing
$backup = $agent->export();
file_put_contents('backup.json', json_encode($backup));

$agent->clear();

// Re-import important memories
$important = array_filter($backup, fn($m) => ($m['metadata']['priority'] ?? null) === 'high');
$agent->import($important);
```

## See Also

- [MemoryManagerAgent Tutorial](tutorials/MemoryManagerAgent_Tutorial.md)
- [Examples](../examples/memory_manager.php)
- [Advanced Examples](../examples/advanced_memory_manager.php)
- [Integration Tests](../tests/Integration/Agents/MemoryManagerAgentIntegrationTest.php)

