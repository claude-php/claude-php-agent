# Configuration Classes

This folder contains type-safe configuration classes for the Claude PHP Agent framework. All configuration classes follow a consistent pattern with:

- **Immutability**: All properties are `readonly`
- **Named constants**: Default values as class constants
- **Factory methods**: `fromArray()` for easy hydration
- **Fluent API**: `with()` method for modifications
- **Validation**: Constructor validation with clear error messages
- **Type safety**: Strict typing throughout

---

## AgentConfig

Configuration for agent execution behavior.

### Usage

```php
use ClaudeAgents\Config\AgentConfig;

// Using defaults
$config = new AgentConfig();

// From array
$config = AgentConfig::fromArray([
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 4096,
    'max_iterations' => 10,
    'timeout' => 30.0,
    'temperature' => 0.7,
    'system_prompt' => 'You are a helpful assistant',
    'thinking' => ['budget_tokens' => 10000],
    'retry' => [
        'max_attempts' => 3,
        'delay_ms' => 1000,
    ],
]);

// Modify existing config
$newConfig = $config->with([
    'temperature' => 0.9,
    'max_tokens' => 8192,
]);
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `model` | string | `claude-sonnet-4-5` | Claude model to use |
| `maxTokens` | int | `4096` | Maximum tokens per response |
| `maxIterations` | int | `10` | Maximum loop iterations |
| `timeout` | float | `30.0` | Request timeout in seconds |
| `temperature` | float\|null | `null` | Response temperature (0.0-1.0) |
| `systemPrompt` | string\|null | `null` | System prompt for the agent |
| `retry` | RetryConfig | `new RetryConfig()` | Retry configuration |
| `thinking` | array | `[]` | Extended thinking configuration |

---

## RetryConfig

Configuration for retry behavior with exponential backoff.

### Usage

```php
use ClaudeAgents\Config\RetryConfig;

$config = RetryConfig::fromArray([
    'max_attempts' => 5,
    'delay_ms' => 2000,
    'multiplier' => 2.0,
    'max_delay_ms' => 60000,
]);

// Calculate delay for attempt
$delay = $config->getDelayForAttempt(3); // Returns: 8000ms
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `maxAttempts` | int | `3` | Maximum retry attempts |
| `delayMs` | int | `1000` | Initial delay in milliseconds |
| `multiplier` | float | `2.0` | Exponential backoff multiplier |
| `maxDelayMs` | int | `30000` | Maximum delay cap in milliseconds |

---

## ChainConfig

Configuration for chain execution and composition.

### Usage

```php
use ClaudeAgents\Config\ChainConfig;

$config = ChainConfig::fromArray([
    'timeout_ms' => 60000,
    'aggregation' => 'merge',  // 'merge', 'first', 'all'
    'error_policy' => 'continue',  // 'stop', 'continue', 'collect'
    'stop_on_first_success' => true,
    'validate_inputs' => true,
    'validate_outputs' => true,
    'max_retries' => 2,
    'metadata' => ['version' => '1.0'],
]);

// Check policies
if ($config->shouldContinueOnError()) {
    // Handle gracefully
}
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `timeoutMs` | int | `30000` | Timeout in milliseconds |
| `aggregation` | string | `merge` | Result aggregation: `merge`, `first`, `all` |
| `errorPolicy` | string | `stop` | Error handling: `stop`, `continue`, `collect` |
| `stopOnFirstSuccess` | bool | `false` | Stop on first success (parallel) |
| `validateInputs` | bool | `true` | Validate inputs against schema |
| `validateOutputs` | bool | `true` | Validate outputs against schema |
| `maxRetries` | int | `0` | Maximum retries for failed chains |
| `metadata` | array | `[]` | Additional metadata |

---

## RAGConfig

Configuration for Retrieval-Augmented Generation.

### Usage

```php
use ClaudeAgents\Config\RAGConfig;

$config = RAGConfig::fromArray([
    'chunk_size' => 512,
    'chunk_overlap' => 50,
    'top_k' => 5,
    'similarity_threshold' => 0.7,
    'retrieval_strategy' => 'hybrid',  // 'keyword', 'semantic', 'hybrid'
    'rerank' => true,
    'include_sources' => true,
    'max_context_length' => 4096,
    'chunking_method' => 'sentence',  // 'sentence', 'paragraph', 'token', 'fixed'
    'embedding_config' => [
        'model' => 'text-embedding-ada-002',
        'dimensions' => 1536,
    ],
]);

// Check retrieval strategy
if ($config->isSemanticRetrieval()) {
    // Use embeddings
}
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `chunkSize` | int | `512` | Size of text chunks |
| `chunkOverlap` | int | `50` | Overlap between chunks |
| `topK` | int | `5` | Number of results to retrieve |
| `similarityThreshold` | float | `0.7` | Minimum similarity (0.0-1.0) |
| `retrievalStrategy` | string | `keyword` | Strategy: `keyword`, `semantic`, `hybrid` |
| `rerank` | bool | `false` | Rerank results |
| `includeSources` | bool | `true` | Include source metadata |
| `maxContextLength` | int | `4096` | Max context for LLM |
| `chunkingMethod` | string | `sentence` | Method: `sentence`, `paragraph`, `token`, `fixed` |
| `embeddingConfig` | array | `[]` | Embedding model config |

---

## StreamConfig

Configuration for streaming responses.

### Usage

```php
use ClaudeAgents\Config\StreamConfig;
use ClaudeAgents\Streaming\Handlers\ConsoleHandler;

$config = StreamConfig::fromArray([
    'buffer_size' => 2048,
    'flush_interval_ms' => 50,
    'chunk_size' => 512,
    'auto_flush' => true,
    'include_metadata' => true,
    'include_usage' => true,
    'format_json' => false,
    'event_prefix' => 'data: ',
    'handlers' => [ConsoleHandler::class],
    'handler_config' => [
        ConsoleHandler::class => ['verbose' => true],
    ],
]);

// Add a handler
$config = $config->withHandler(CustomHandler::class, [
    'option' => 'value',
]);
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `bufferSize` | int | `1024` | Buffer size in bytes |
| `flushIntervalMs` | int | `100` | Flush interval in milliseconds |
| `chunkSize` | int | `512` | Chunk size to process |
| `autoFlush` | bool | `true` | Auto-flush on newlines |
| `includeMetadata` | bool | `true` | Include metadata events |
| `includeUsage` | bool | `true` | Include token usage |
| `formatJson` | bool | `false` | Format as JSON events |
| `eventPrefix` | string | `''` | Prefix for events |
| `handlers` | array | `[]` | Handler class names |
| `handlerConfig` | array | `[]` | Handler configurations |

---

## CacheConfig

Configuration for caching policies and storage.

### Usage

```php
use ClaudeAgents\Config\CacheConfig;

$config = CacheConfig::fromArray([
    'ttl_seconds' => 3600,  // 1 hour (0 = no expiration)
    'max_size' => 1000,
    'storage_backend' => 'redis',  // 'memory', 'file', 'redis', 'memcached'
    'key_strategy' => 'hash',  // 'hash', 'full', 'custom'
    'enabled' => true,
    'auto_cleanup' => true,
    'cleanup_interval_seconds' => 300,
    'storage_path' => '/tmp/cache',
    'backend_config' => [
        'host' => 'localhost',
        'port' => 6379,
    ],
    'key_generator' => fn($input) => md5(json_encode($input)),
]);

// Check settings
if ($config->isRedisStorage() && $config->hasExpiration()) {
    // Setup Redis with TTL
}
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `ttlSeconds` | int | `3600` | Time-to-live (0 = no expiration) |
| `maxSize` | int | `1000` | Maximum cache entries |
| `storageBackend` | string | `memory` | Backend: `memory`, `file`, `redis`, `memcached` |
| `keyStrategy` | string | `hash` | Key generation: `hash`, `full`, `custom` |
| `enabled` | bool | `true` | Enable caching |
| `autoCleanup` | bool | `true` | Auto-cleanup expired entries |
| `cleanupIntervalSeconds` | int | `300` | Cleanup interval |
| `storagePath` | string\|null | `null` | Path for file storage |
| `backendConfig` | array | `[]` | Backend-specific config |
| `keyGenerator` | callable\|null | `null` | Custom key generator |

---

## Common Patterns

### Configuration Inheritance

```php
// Base config
$baseConfig = AgentConfig::fromArray([
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 4096,
]);

// Specialized configs
$fastConfig = $baseConfig->with(['temperature' => 0.3]);
$creativeConfig = $baseConfig->with(['temperature' => 0.9]);
```

### Environment-Based Configuration

```php
$config = AgentConfig::fromArray([
    'model' => $_ENV['CLAUDE_MODEL'] ?? AgentConfig::DEFAULT_MODEL,
    'timeout' => (float)($_ENV['TIMEOUT'] ?? AgentConfig::DEFAULT_TIMEOUT),
]);
```

### Configuration Validation

All configuration classes validate their inputs on construction:

```php
try {
    $config = ChainConfig::fromArray([
        'aggregation' => 'invalid',  // Will throw
    ]);
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage();
    // "Invalid aggregation strategy: invalid. Must be one of: merge, first, all"
}
```

### Combining Configurations

```php
use ClaudeAgents\Agent;

$agent = Agent::create()
    ->withConfig(AgentConfig::fromArray([
        'model' => 'claude-sonnet-4-5',
        'retry' => ['max_attempts' => 5],
    ]))
    ->withCache(CacheConfig::fromArray([
        'storage_backend' => 'redis',
    ]));
```

---

## Design Principles

1. **Immutability**: All configs are immutable after creation
2. **Type Safety**: Strict types prevent runtime errors
3. **Validation**: Early validation catches configuration errors
4. **Defaults**: Sensible defaults for quick setup
5. **Flexibility**: Easy to override any setting
6. **Composability**: Configs work together seamlessly
7. **Documentation**: Self-documenting through type hints and constants

---

## Testing

All configuration classes include validation and can be tested:

```php
use PHPUnit\Framework\TestCase;
use ClaudeAgents\Config\AgentConfig;

class AgentConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new AgentConfig();
        $this->assertEquals('claude-sonnet-4-5', $config->getModel());
        $this->assertEquals(4096, $config->getMaxTokens());
    }

    public function testFromArray(): void
    {
        $config = AgentConfig::fromArray(['model' => 'claude-opus-4']);
        $this->assertEquals('claude-opus-4', $config->getModel());
    }

    public function testWith(): void
    {
        $config = new AgentConfig();
        $modified = $config->with(['temperature' => 0.8]);
        $this->assertEquals(0.8, $modified->getTemperature());
        $this->assertNull($config->getTemperature()); // Original unchanged
    }
}
```

---

## See Also

- [Agent Documentation](../../docs/)
- [Chain Documentation](../Chains/)
- [RAG Documentation](../RAG/)
- [Streaming Documentation](../Streaming/)
- [Cache Documentation](../Cache/)

