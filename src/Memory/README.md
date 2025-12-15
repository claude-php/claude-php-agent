# Memory System

## Overview

The Memory system provides various strategies for managing conversation history, state, and knowledge in AI agents. From simple key-value storage to sophisticated knowledge graphs, these components help agents maintain context efficiently.

## Directory Structure

```
Memory/
├── Memory.php                              # In-memory key-value store
├── FileMemory.php                          # Persistent file storage
├── ConversationMemory.php                  # Full conversation history
├── ConversationBufferWindowMemory.php      # Sliding window of recent messages
├── ConversationSummaryMemory.php           # Summarized old + recent messages
├── ConversationSummaryBufferMemory.php     # Hybrid summary + buffer
├── EntityMemory.php                        # Entity tracking
├── ConversationKGMemory.php                # Knowledge graph
├── Summarizers/
│   ├── SummarizerInterface.php             # Summarizer contract
│   ├── LLMSummarizer.php                   # Claude-powered summarization
│   └── ExtractiveSummarizer.php            # Keyword-based summarization
└── Entities/
    ├── EntityExtractor.php                 # Extract entities from text
    └── EntityStore.php                     # Store and query entities
```

## Quick Start

### Basic State Management

```php
use ClaudeAgents\Memory\Memory;

$memory = new Memory();
$memory->set('key', 'value');
$memory->increment('counter');
$memory->push('items', 'new_item');
```

### Persistent Storage

```php
use ClaudeAgents\Memory\FileMemory;

$memory = new FileMemory('/path/to/state.json');
$memory->set('config', ['setting' => 'value']);
// Auto-saved to file
```

### Conversation with Token Management

```php
use ClaudeAgents\Memory\ConversationSummaryBufferMemory;
use ClaudeAgents\Memory\Summarizers\LLMSummarizer;

$summarizer = new LLMSummarizer($client);
$memory = new ConversationSummaryBufferMemory($summarizer, maxTokens: 2000);

$memory->add(['role' => 'user', 'content' => 'Hello']);
$memory->add(['role' => 'assistant', 'content' => 'Hi!']);

$context = $memory->getContext(); // For next LLM call
```

### Entity Tracking

```php
use ClaudeAgents\Memory\EntityMemory;
use ClaudeAgents\Memory\Entities\{EntityExtractor, EntityStore};

$extractor = new EntityExtractor($client);
$store = new EntityStore();
$memory = new EntityMemory($extractor, $store);

$memory->add(['role' => 'user', 'content' => 'John works at Acme Corp']);
$entity = $memory->getEntity('John');
```

## Memory Type Selection Guide

### Choose `Memory` when:
- ✅ You need simple key-value storage
- ✅ State doesn't need to persist between runs
- ✅ You want utility methods (increment, push, etc.)

### Choose `FileMemory` when:
- ✅ State must persist across sessions
- ✅ You're building autonomous agents
- ✅ You need configuration storage

### Choose `ConversationMemory` when:
- ✅ Conversations are short (< 20 messages)
- ✅ You need full conversation history
- ✅ Token limits aren't a concern

### Choose `ConversationBufferWindowMemory` when:
- ✅ You need predictable token usage
- ✅ Recent context is most important
- ✅ You want zero LLM overhead

### Choose `ConversationSummaryMemory` when:
- ✅ Conversations are long
- ✅ Historical context matters
- ✅ You can accept summarization latency

### Choose `ConversationSummaryBufferMemory` when:
- ✅ You want production-ready balance
- ✅ Token budget is constrained
- ✅ Both history and recent context matter
- ✅ **This is the recommended default for most applications**

### Choose `EntityMemory` when:
- ✅ Conversations reference people/places/things
- ✅ You need entity-based recall
- ✅ Multi-turn conversations about entities

### Choose `ConversationKGMemory` when:
- ✅ You need complex knowledge representation
- ✅ Relationships between entities matter
- ✅ You're building knowledge-intensive agents
- ⚠️ Most complex option

## Implementation Patterns

### Pattern 1: Hybrid Memory

Combine multiple memory types for comprehensive context:

```php
class ProductionAgent
{
    private FileMemory $persistent;
    private ConversationSummaryBufferMemory $conversation;
    private EntityMemory $entities;
    
    public function getContext(): array
    {
        return [
            'system' => $this->persistent->all(),
            'conversation' => $this->conversation->getContext(),
            'entities' => $this->entities->getContext(),
        ];
    }
}
```

### Pattern 2: Fallback Summarization

Use extractive summarization as fallback:

```php
$llmSummarizer = new LLMSummarizer($client);
$extractiveSummarizer = new ExtractiveSummarizer();

try {
    $summary = $llmSummarizer->summarize($messages);
} catch (\Exception $e) {
    $summary = $extractiveSummarizer->summarize($messages);
}
```

### Pattern 3: Memory Tiering

Short-term and long-term memory:

```php
$shortTerm = new ConversationBufferWindowMemory(10);
$longTerm = new FileMemory('/data/knowledge.json');

// Periodically promote important information
if ($importance > 0.8) {
    $longTerm->set($key, $value);
}
```

## Testing

Comprehensive tests are provided for all memory types:

```bash
vendor/bin/phpunit tests/Unit/Memory/
```

Test files:
- `MemoryTest.php`
- `FileMemoryTest.php`
- `ConversationMemoryTest.php`
- `ConversationBufferWindowMemoryTest.php`
- `ConversationSummaryMemoryTest.php`
- `ConversationSummaryBufferMemoryTest.php`
- `EntityMemoryTest.php`
- `ConversationKGMemoryTest.php`
- `Summarizers/LLMSummarizerTest.php`
- `Summarizers/ExtractiveSummarizerTest.php`
- `Entities/EntityStoreTest.php`

## API Reference

See [AdvancedMemoryTypes.md](../../docs/AdvancedMemoryTypes.md) for detailed API documentation.

## Contributing

When adding new memory types:

1. Implement appropriate interface (`MemoryInterface` for state, or custom interface for conversation memory)
2. Add comprehensive PHPUnit tests
3. Document in `AdvancedMemoryTypes.md`
4. Add example usage in `examples/`
5. Consider token efficiency and performance implications

## Performance Notes

### Token Usage
- **No API calls:** `ConversationBufferWindowMemory`, `ExtractiveSummarizer`
- **Periodic API calls:** `ConversationSummaryMemory`, `EntityMemory`
- **Adaptive API calls:** `ConversationSummaryBufferMemory`
- **Frequent API calls:** `ConversationKGMemory`

### Latency
- **Instant:** Window memory, extractive summarization
- **Low:** Cached summaries
- **Medium:** On-demand summarization, entity extraction
- **Higher:** Knowledge graph extraction

### Memory (RAM)
All types store data in PHP arrays. For long-running processes, consider:
- Periodic export/clear cycles
- Database integration
- External caching layers

## See Also

- [Memory Documentation](../../docs/AdvancedMemoryTypes.md)
- [MemoryInterface](../Contracts/MemoryInterface.php)
- [MemoryManagerAgent](../Agents/MemoryManagerAgent.php)
- [Examples](../../examples/)

