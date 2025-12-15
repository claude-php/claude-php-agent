# Advanced Memory Types

## Overview

The Claude PHP Agent framework provides a comprehensive suite of memory types for managing conversation history and state. These memory types help optimize token usage, maintain context, and enable sophisticated AI agent behaviors.

## Memory Type Comparison

| Memory Type | Use Case | Token Efficiency | Complexity | Best For |
|-------------|----------|------------------|------------|----------|
| **Memory** | Simple key-value state | N/A | Low | Agent state, flags, counters |
| **FileMemory** | Persistent state across sessions | N/A | Low | Autonomous agents, long-running processes |
| **ConversationMemory** | Full conversation history | Low | Low | Short conversations |
| **ConversationBufferWindowMemory** | Recent N messages | Medium | Low | Predictable token usage |
| **ConversationSummaryMemory** | Summarized old + recent messages | High | Medium | Long conversations |
| **ConversationSummaryBufferMemory** | Hybrid summary + buffer | Very High | Medium | Best balance for production |
| **EntityMemory** | Entity tracking | Medium | Medium | Multi-turn with entities |
| **ConversationKGMemory** | Knowledge graph | High | High | Complex knowledge management |

## Core Memory Types

### Memory

Simple in-memory key-value storage for agent state.

```php
use ClaudeAgents\Memory\Memory;

$memory = new Memory();

// Basic operations
$memory->set('user_name', 'John');
$memory->get('user_name'); // 'John'
$memory->has('user_name'); // true
$memory->forget('user_name');

// Utility methods
$memory->increment('counter'); // 1
$memory->increment('counter'); // 2
$memory->push('items', 'apple');
$memory->push('items', 'banana');
$memory->get('items'); // ['apple', 'banana']

// History tracking
$history = $memory->getHistory();
```

**Features:**
- ✅ Fast in-memory storage
- ✅ History tracking
- ✅ Utility methods (increment, push, pull)
- ✅ Lifetime = agent execution

### FileMemory

Persistent file-based storage for state across sessions.

```php
use ClaudeAgents\Memory\FileMemory;

$memory = new FileMemory('/path/to/state.json', autoSave: true);

$memory->set('last_run', time());
$memory->save(); // Manual save if autoSave = false

// File operations
$memory->exists(); // true
$memory->getLastModified(); // timestamp
$memory->delete(); // Remove file
```

**Features:**
- ✅ JSON file persistence
- ✅ Auto-save option
- ✅ Directory creation
- ✅ Survives process restarts

## Conversation Memory Types

### ConversationMemory

Keeps full conversation history with token-aware truncation.

```php
use ClaudeAgents\Memory\ConversationMemory;

$memory = new ConversationMemory(maxMessages: 20);

$memory->add(['role' => 'user', 'content' => 'Hello']);
$memory->add(['role' => 'assistant', 'content' => 'Hi there!']);

$messages = $memory->getMessages();
$tokens = $memory->getEstimatedTokens();
$summary = $memory->summarize();
```

**Features:**
- ✅ Message pair trimming
- ✅ Token estimation
- ✅ Conversation summarization
- ❌ Can exceed token limits in long conversations

**When to use:**
- Short conversations (< 20 message pairs)
- When full context is critical
- Development and testing

### ConversationBufferWindowMemory

Maintains a sliding window of the most recent N messages.

```php
use ClaudeAgents\Memory\ConversationBufferWindowMemory;

$memory = new ConversationBufferWindowMemory(windowSize: 10);

// Add messages - automatically maintains window
for ($i = 0; $i < 20; $i++) {
    $memory->add(['role' => 'user', 'content' => "Message $i"]);
}

// Only last 10 messages kept
$messages = $memory->getMessages(); // count: 10
$context = $memory->getContext();

// Window management
$memory->isFull(); // true
$memory->getOldest(); // First message in window
$memory->getNewest(); // Last message in window
```

**Features:**
- ✅ Predictable token usage
- ✅ Simple and fast
- ✅ No LLM calls needed
- ❌ Loses older context

**When to use:**
- Conversations with recent context focus
- Token budget constraints
- Real-time applications

### ConversationSummaryMemory

Summarizes old messages while keeping recent ones in full.

```php
use ClaudeAgents\Memory\ConversationSummaryMemory;
use ClaudeAgents\Memory\Summarizers\LLMSummarizer;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-key');
$summarizer = new LLMSummarizer($client, [
    'max_tokens' => 500,
    'focus' => 'key_points',
]);

$memory = new ConversationSummaryMemory(
    $summarizer,
    summaryThreshold: 10,  // Summarize after 10 messages
    keepRecentCount: 3      // Keep last 3 messages in full
);

// Automatic summarization
for ($i = 0; $i < 15; $i++) {
    $memory->add(['role' => 'user', 'content' => "Message $i"]);
}

// Get context (summary + recent messages)
$context = $memory->getContext();
$summary = $memory->getSummary();
$recent = $memory->getRecentMessages(); // Last 3 messages

// Manual summarization
$memory->forceSummarize();
```

**Features:**
- ✅ Preserves key information
- ✅ Reduces token usage
- ✅ Maintains recent context
- ⚠️ Requires LLM calls for summarization

**When to use:**
- Long conversations (> 20 message pairs)
- When historical context matters
- Acceptable latency for summarization

### ConversationSummaryBufferMemory

Hybrid approach: summarizes old messages when exceeding token limit, keeps buffer of recent messages.

```php
use ClaudeAgents\Memory\ConversationSummaryBufferMemory;
use ClaudeAgents\Memory\Summarizers\LLMSummarizer;

$summarizer = new LLMSummarizer($client, ['max_tokens' => 200]);

$memory = new ConversationSummaryBufferMemory(
    $summarizer,
    maxTokens: 2000  // Total token budget
);

// Automatically manages token budget
$memory->add(['role' => 'user', 'content' => $longMessage]);

// Token tracking
$memory->getTotalTokens();    // Summary + buffer tokens
$memory->getBufferTokens();   // Recent messages tokens
$memory->getSummaryTokens();  // Summary tokens

// Status checks
$memory->isNearLimit();  // true if > 90% of maxTokens
$memory->hasSummary();   // true if summarization occurred
```

**Features:**
- ✅ Best balance of context and efficiency
- ✅ Adaptive token management
- ✅ Predictable costs
- ✅ Production-ready

**When to use:**
- Production applications
- Long conversations with token budgets
- When you want the best of both worlds

## Entity and Knowledge-Based Memory

### EntityMemory

Extracts and tracks entities (people, places, things) across conversations.

```php
use ClaudeAgents\Memory\EntityMemory;
use ClaudeAgents\Memory\Entities\EntityExtractor;
use ClaudeAgents\Memory\Entities\EntityStore;

$extractor = new EntityExtractor($client);
$store = new EntityStore();

$memory = new EntityMemory(
    $extractor,
    $store,
    extractionInterval: 5  // Extract every 5 messages
);

// Entities extracted automatically
$memory->add(['role' => 'user', 'content' => 'John works at Acme Corp']);
$memory->add(['role' => 'user', 'content' => 'He lives in Paris']);

// Query entities
$entity = $memory->getEntity('John');
// ['type' => 'person', 'attributes' => ['workplace' => 'Acme Corp'], ...]

$people = $memory->getEntitiesByType('person');
$results = $memory->searchEntities('john');

// Context includes entity information
$context = $memory->getContext();

// Statistics
$stats = $memory->getStats();
```

**Features:**
- ✅ Automatic entity extraction
- ✅ Entity type classification
- ✅ Attribute tracking
- ✅ Mention counting
- ⚠️ Requires LLM calls for extraction

**When to use:**
- Multi-turn conversations about people/places/things
- Customer service agents
- Personal assistants
- Knowledge management

### ConversationKGMemory

Builds a knowledge graph of entities and relationships.

```php
use ClaudeAgents\Memory\ConversationKGMemory;

$memory = new ConversationKGMemory(
    $client,
    $extractor,
    ['extraction_interval' => 5]
);

// Knowledge extracted automatically
$memory->add(['role' => 'user', 'content' => 'Alice manages the engineering team']);
$memory->add(['role' => 'user', 'content' => 'Bob reports to Alice']);

// Query knowledge graph
$entities = $memory->getEntities();
// ['Alice' => [...], 'Bob' => [...], 'engineering team' => [...]]

$relationships = $memory->getRelationships();
// [
//   ['subject' => 'Alice', 'predicate' => 'manages', 'object' => 'engineering team'],
//   ['subject' => 'Bob', 'predicate' => 'reports_to', 'object' => 'Alice']
// ]

// Query patterns
$rels = $memory->query('Alice', 'manages', null); // What does Alice manage?
$rels = $memory->query(null, 'reports_to', 'Alice'); // Who reports to Alice?

$entityRels = $memory->getEntityRelationships('Alice'); // All Alice's relationships

// Context includes knowledge graph
$context = $memory->getContext();
```

**Features:**
- ✅ Entity + relationship extraction
- ✅ Graph-based queries
- ✅ Complex knowledge representation
- ✅ Relationship tracking over time
- ⚠️ Most complex memory type
- ⚠️ Multiple LLM calls

**When to use:**
- Complex domain knowledge
- Organizational structures
- Research and analysis agents
- Advanced reasoning requirements

## Summarizers

### LLMSummarizer

Uses Claude to generate high-quality summaries.

```php
use ClaudeAgents\Memory\Summarizers\LLMSummarizer;

$summarizer = new LLMSummarizer($client, [
    'max_tokens' => 500,
    'focus' => 'key_points',  // or 'entities', 'decisions', 'chronological'
    'model' => 'claude-3-5-haiku-20241022',
]);

$summary = $summarizer->summarize($messages, $existingSummary);
$summary = $summarizer->summarizeMessages($messages);
```

**Focus Types:**
- `key_points` - Main points and important information
- `entities` - People, places, organizations mentioned
- `decisions` - Decisions, action items, commitments
- `chronological` - Timeline of events

### ExtractiveSummarizer

Fast, deterministic summarization without LLM calls.

```php
use ClaudeAgents\Memory\Summarizers\ExtractiveSummarizer;

$summarizer = new ExtractiveSummarizer([
    'max_sentences' => 5,
    'max_tokens' => 200,
]);

$summary = $summarizer->summarizeMessages($messages);
```

**Features:**
- ✅ No API calls
- ✅ Instant results
- ✅ Keyword-based scoring
- ❌ Lower quality than LLM
- ✅ Good fallback option

## Best Practices

### 1. Choose the Right Memory Type

```php
// Short conversations
$memory = new ConversationMemory(maxMessages: 20);

// Long conversations, recent focus
$memory = new ConversationBufferWindowMemory(windowSize: 10);

// Long conversations, need history
$memory = new ConversationSummaryBufferMemory($summarizer, maxTokens: 2000);

// Entity-heavy conversations
$memory = new EntityMemory($extractor, $store);
```

### 2. Monitor Token Usage

```php
if ($memory->getTotalTokens() > 5000) {
    // Consider increasing summarization or reducing window
}

if ($memory->isNearLimit(0.8)) {
    // 80% of limit - take action
}
```

### 3. Combine Memory Types

```php
class HybridMemoryAgent
{
    private Memory $state;
    private ConversationSummaryBufferMemory $conversation;
    private EntityMemory $entities;
    
    public function getFullContext(): array
    {
        return array_merge(
            $this->conversation->getContext(),
            $this->entities->getContext()
        );
    }
}
```

### 4. Handle Summarization Errors

```php
try {
    $summary = $llmSummarizer->summarize($messages);
} catch (\Exception $e) {
    // Fallback to extractive summarizer
    $summary = $extractiveSummarizer->summarize($messages);
}
```

### 5. Persist Important Memories

```php
// Short-term conversation memory
$conversation = new ConversationSummaryBufferMemory($summarizer, maxTokens: 2000);

// Long-term persistent facts
$longTerm = new FileMemory('/data/agent_memory.json');

// After conversation, save key facts
$longTerm->set('last_conversation_summary', $conversation->getSummary());
$longTerm->set('entities_learned', $entityMemory->getEntityStore()->all());
```

## Performance Considerations

### Token Costs

| Memory Type | API Calls | Cost Impact |
|-------------|-----------|-------------|
| ConversationBufferWindowMemory | None | None |
| ConversationSummaryMemory | Periodic (summarization) | Low-Medium |
| ConversationSummaryBufferMemory | Adaptive (when needed) | Low |
| EntityMemory | Periodic (extraction) | Medium |
| ConversationKGMemory | Periodic (extraction + relationships) | High |

### Latency

- **Lowest:** `ConversationBufferWindowMemory`, `ExtractiveSummarizer`
- **Low:** `ConversationSummaryBufferMemory` (with cached summaries)
- **Medium:** `ConversationSummaryMemory`, `EntityMemory`
- **Higher:** `ConversationKGMemory`

### Memory Usage

All memory types store messages in PHP arrays. For very long-running agents:

```php
// Periodically export and clear
$data = $memory->export();
saveToDatabase($data);
$memory->clear();
```

## Examples

See the [examples directory](../examples/) for complete working examples:
- `examples/memory_types.php` - All memory types demo
- `examples/entity_memory.php` - Entity tracking demo
- `examples/knowledge_graph.php` - Knowledge graph demo
- `examples/production_memory.php` - Production setup

## See Also

- [Memory Interface](../src/Contracts/MemoryInterface.php)
- [MemoryManagerAgent](./MemoryManagerAgent.md)
- [Test Suite](../tests/Unit/Memory/)

