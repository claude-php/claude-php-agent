# RAGAgent

## Overview

The **RAGAgent** (Retrieval-Augmented Generation Agent) is a specialized agent that grounds responses in external knowledge by combining document retrieval with Claude's generation capabilities. It enables you to build AI systems that can answer questions based on your own documents, providing cited, accurate responses.

## Key Features

- **Document Management**: Add and organize documents in a knowledge base
- **Intelligent Chunking**: Automatically splits documents into searchable chunks
- **Smart Retrieval**: Finds the most relevant content for each query
- **Source Citations**: Provides references to source documents
- **Fluent Interface**: Chainable methods for easy configuration
- **Token Tracking**: Monitors API usage
- **Error Handling**: Robust error management with detailed feedback

## Architecture

The RAGAgent orchestrates several components:

```
┌─────────────┐
│  RAGAgent   │
└──────┬──────┘
       │
       ├─► RAGPipeline ─┬─► DocumentStore
       │                ├─► Chunker
       │                └─► Retriever
       │
       └─► Claude API
```

### Components

1. **RAGAgent**: Main interface for document-based question answering
2. **RAGPipeline**: Orchestrates the RAG workflow
3. **DocumentStore**: Manages document storage and retrieval
4. **Chunker**: Splits documents into overlapping chunks
5. **Retriever**: Finds relevant chunks using keyword or semantic search

## Installation

The RAGAgent is included in the claude-php-agent package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

### Creating a RAG Agent

```php
use ClaudeAgents\Agents\RAGAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$agent = new RAGAgent($client, ['name' => 'knowledge_assistant']);
```

### Adding Documents

```php
// Add a single document
$agent->addDocument(
    title: 'PHP Basics',
    content: 'PHP is a server-side scripting language...',
    metadata: ['category' => 'programming', 'author' => 'John Doe']
);

// Add multiple documents
$agent->addDocuments([
    [
        'title' => 'Web Development',
        'content' => 'HTML, CSS, and JavaScript form...',
    ],
    [
        'title' => 'Database Design',
        'content' => 'Relational databases use tables...',
        'metadata' => ['difficulty' => 'intermediate']
    ]
]);
```

### Querying the Knowledge Base

```php
$result = $agent->run('What is PHP?');

if ($result->isSuccess()) {
    echo "Answer: " . $result->getAnswer() . "\n";
    
    // Get metadata
    $metadata = $result->getMetadata();
    
    // View sources
    foreach ($metadata['sources'] as $source) {
        echo "Source: {$source['source']}\n";
        echo "Preview: {$source['text_preview']}\n";
    }
    
    // View citations
    echo "Cited sources: " . implode(', ', $metadata['citations']) . "\n";
    
    // Track token usage
    echo "Tokens: {$metadata['tokens']['input']} in, {$metadata['tokens']['output']} out\n";
}
```

## Advanced Usage

### Custom Chunking Strategy

```php
use ClaudeAgents\RAG\Chunker;

$agent = new RAGAgent($client);

// Configure chunker with custom settings
$chunker = new Chunker(
    chunkSize: 300,  // Words per chunk
    overlap: 30      // Overlapping words between chunks
);

$agent->getRag()->withChunker($chunker);
```

### Custom Retrieval Strategy

```php
use ClaudeAgents\RAG\KeywordRetriever;

$retriever = new KeywordRetriever();
$agent->getRag()->withRetriever($retriever);

// For semantic search (requires embeddings)
// use ClaudeAgents\RAG\SemanticRetriever;
// $retriever = new SemanticRetriever($embeddingClient);
// $agent->getRag()->withRetriever($retriever);
```

### Accessing the Pipeline

```php
$pipeline = $agent->getRag();

// Get statistics
$docCount = $pipeline->getDocumentCount();
$chunkCount = $pipeline->getChunkCount();

// Access document store
$store = $pipeline->getDocumentStore();
$allDocs = $store->all();

// Clear knowledge base
$pipeline->clear();
```

### With Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('rag');
$logger->pushHandler(new StreamHandler('rag.log', Logger::DEBUG));

$agent = new RAGAgent($client, [
    'name' => 'rag_agent',
    'logger' => $logger
]);
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'rag_agent'` | Agent identifier |
| `logger` | LoggerInterface | NullLogger | PSR-3 logger instance |

## Query Result Structure

The `AgentResult` returned by `run()` contains:

```php
[
    'answer' => string,           // The generated answer
    'sources' => [                // Retrieved source documents
        [
            'index' => int,       // Source index
            'source' => string,   // Document title
            'text_preview' => string,  // Preview of chunk text
            'metadata' => array   // Document metadata
        ],
        // ...
    ],
    'citations' => [int],         // Array of cited source indices
    'tokens' => [
        'input' => int,           // Input tokens used
        'output' => int           // Output tokens used
    ],
    'document_count' => int,      // Total documents in KB
    'chunk_count' => int          // Total chunks in KB
]
```

## Use Cases

### Technical Documentation Assistant

```php
$agent = new RAGAgent($client, ['name' => 'docs_assistant']);

// Load documentation
$agent->addDocuments([
    ['title' => 'Getting Started', 'content' => $gettingStartedDoc],
    ['title' => 'API Reference', 'content' => $apiReferenceDoc],
    ['title' => 'Examples', 'content' => $examplesDoc],
]);

// Answer user questions
$result = $agent->run('How do I authenticate?');
```

### Product Knowledge Base

```php
$agent = new RAGAgent($client, ['name' => 'product_kb']);

// Add product information
foreach ($products as $product) {
    $agent->addDocument(
        $product['name'],
        $product['description'] . ' ' . $product['specifications'],
        ['category' => $product['category'], 'price' => $product['price']]
    );
}

// Search products
$result = $agent->run('What laptops do you have under $1000?');
```

### Research Assistant

```php
$agent = new RAGAgent($client, ['name' => 'research_assistant']);

// Load research papers
foreach ($papers as $paper) {
    $agent->addDocument(
        $paper['title'],
        $paper['abstract'] . ' ' . $paper['content'],
        ['authors' => $paper['authors'], 'year' => $paper['year']]
    );
}

// Query research
$result = $agent->run('What are the latest findings on quantum computing?');
```

### Customer Support Bot

```php
$agent = new RAGAgent($client, ['name' => 'support_bot']);

// Load support articles
$agent->addDocuments([
    ['title' => 'Password Reset', 'content' => $passwordResetGuide],
    ['title' => 'Billing FAQ', 'content' => $billingFAQ],
    ['title' => 'Troubleshooting', 'content' => $troubleshootingGuide],
]);

// Answer customer questions
$result = $agent->run('How do I reset my password?');
```

## Best Practices

### 1. Document Structure

- Use descriptive titles for easy source identification
- Include relevant context in each document
- Keep documents focused on specific topics
- Add metadata for filtering and organization

### 2. Chunk Size Tuning

- **Smaller chunks** (200-300 words): Better for precise information retrieval
- **Larger chunks** (500-700 words): Better for contextual understanding
- Adjust overlap to maintain context between chunks

### 3. Query Optimization

- Ask specific, focused questions
- Provide context when needed
- Use natural language queries
- Review source citations for accuracy

### 4. Knowledge Base Management

- Regularly update documents
- Remove outdated information
- Monitor chunk counts for performance
- Use metadata for organization

### 5. Error Handling

```php
$result = $agent->run($query);

if (!$result->isSuccess()) {
    $error = $result->getError();
    
    if (str_contains($error, 'No documents')) {
        // Handle empty knowledge base
    } else {
        // Handle other errors
        error_log("RAG Error: " . $error);
    }
}
```

## Performance Considerations

### Token Usage

RAG queries consume tokens for both context and generation:
- Input tokens include query + retrieved chunks
- More chunks = higher input token count
- Use `topK` parameter to control retrieved chunks

```php
// Retrieve fewer chunks to reduce token usage
$result = $agent->getRag()->query($question, topK: 2);
```

### Scaling

For large knowledge bases:
1. Consider external vector databases
2. Implement caching for frequent queries
3. Use semantic retrieval for better accuracy
4. Batch document additions

### Memory Management

```php
// For large document sets, clear periodically
if ($agent->getRag()->getChunkCount() > 10000) {
    $agent->getRag()->clear();
    // Reload only relevant documents
}
```

## Limitations

1. **In-Memory Storage**: Default DocumentStore keeps everything in memory
2. **Keyword Retrieval**: Basic keyword matching may miss semantic matches
3. **Context Window**: Limited by Claude's context window
4. **No Persistence**: Knowledge base is not saved between runs

## Extending RAGAgent

### Custom Document Store

```php
use ClaudeAgents\Contracts\DocumentStoreInterface;

class RedisDocumentStore implements DocumentStoreInterface {
    // Implement interface methods
}

// Use with RAGPipeline
$store = new RedisDocumentStore($redis);
// Inject via constructor or setter
```

### Custom Retriever

```php
use ClaudeAgents\Contracts\RetrieverInterface;

class ElasticsearchRetriever implements RetrieverInterface {
    public function retrieve(string $query, int $topK = 3): array {
        // Custom retrieval logic
    }
    
    public function setChunks(array $chunks): void {
        // Index chunks in Elasticsearch
    }
}

$agent->getRag()->withRetriever(new ElasticsearchRetriever());
```

## Testing

See the test suite for comprehensive examples:
- `tests/Unit/Agents/RAGAgentTest.php` - Unit tests
- `tests/Integration/Agents/RAGAgentIntegrationTest.php` - Integration tests
- `examples/rag_example.php` - Working example

## Related Documentation

- [RAGAgent Tutorial](tutorials/RAGAgent_Tutorial.md) - Step-by-step guide
- [Agent Selection Guide](agent-selection-guide.md) - When to use RAGAgent
- [API Reference](../README.md) - Full API documentation

## Troubleshooting

### No Results Returned

```php
// Check if documents are loaded
if ($agent->getRag()->getDocumentCount() === 0) {
    echo "No documents in knowledge base\n";
}

// Check chunk count
if ($agent->getRag()->getChunkCount() === 0) {
    echo "No searchable chunks created\n";
}
```

### Poor Retrieval Quality

- Increase chunk overlap for better context
- Use more descriptive document titles
- Add relevant metadata
- Consider semantic retrieval instead of keyword

### High Token Usage

- Reduce `topK` parameter
- Use smaller chunk sizes
- Filter documents before adding
- Implement result caching

## Examples

See `examples/rag_example.php` for a complete working example.

## License

This agent is part of the claude-php-agent package. See LICENSE for details.

