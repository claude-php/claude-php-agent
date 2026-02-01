# RAG Advanced Features

This document covers the advanced RAG features that extend beyond the basic implementation.

## Table of Contents

- [Text Splitters](#text-splitters)
- [Document Loaders](#document-loaders)
- [Metadata Filtering](#metadata-filtering)
- [Query Transformation](#query-transformation)
- [Re-ranking](#re-ranking)
- [Vector Stores](#vector-stores)
- [Persistent Storage](#persistent-storage)

---

## Text Splitters

Advanced text splitters that respect document structure and language syntax.

### Recursive Character Text Splitter

Splits text recursively using multiple separators to maintain natural boundaries.

```php
use ClaudeAgents\RAG\Splitters\RecursiveCharacterTextSplitter;

$splitter = new RecursiveCharacterTextSplitter(
    chunkSize: 2000,          // Characters per chunk
    overlap: 200,             // Overlap between chunks
    separators: ["\n\n", "\n", ". ", " ", ""]  // Custom separators
);

$chunks = $splitter->chunk($longDocument);
```

**When to use**: General purpose text splitting with natural boundaries.

### Code Text Splitter

Language-aware splitting that keeps functions and classes together.

```php
use ClaudeAgents\RAG\Splitters\CodeTextSplitter;

$splitter = new CodeTextSplitter(
    language: 'php',          // php, python, javascript, etc.
    chunkSize: 2000,
    overlap: 200
);

$chunks = $splitter->chunk($sourceCode);
```

**Supported languages**: PHP, Python, JavaScript, TypeScript, Java, C#, Go, Rust

**When to use**: Splitting source code while preserving function/class boundaries.

### Markdown Text Splitter

Respects Markdown structure (headers, code blocks, lists).

```php
use ClaudeAgents\RAG\Splitters\MarkdownTextSplitter;

$splitter = new MarkdownTextSplitter(
    chunkSize: 2000,
    overlap: 200
);

$chunks = $splitter->chunk($markdownDocument);
```

**When to use**: Documentation, README files, Markdown content.

### Token Text Splitter

Splits by approximate token count (useful for LLM context limits).

```php
use ClaudeAgents\RAG\Splitters\TokenTextSplitter;

$splitter = new TokenTextSplitter(
    chunkSize: 500,           // Tokens per chunk (approximate)
    overlap: 50
);

$chunks = $splitter->chunk($text);
$tokenCount = $splitter->countTokens($text);
```

**When to use**: Managing token limits, cost optimization.

---

## Document Loaders

Load documents from various sources.

### Text File Loader

```php
use ClaudeAgents\RAG\Loaders\TextFileLoader;

$loader = new TextFileLoader(
    filePath: '/path/to/file.txt',
    metadata: ['source' => 'documentation']
);

$documents = $loader->load();
```

### CSV Loader

```php
use ClaudeAgents\RAG\Loaders\CSVLoader;

$loader = new CSVLoader(
    filePath: '/path/to/data.csv',
    contentColumn: 'description',
    titleColumn: 'name',
    metadata: ['type' => 'product_catalog']
);

$documents = $loader->load();
```

### JSON Loader

```php
use ClaudeAgents\RAG\Loaders\JSONLoader;

$loader = new JSONLoader(
    filePath: '/path/to/data.json',
    contentField: 'content',
    titleField: 'title',
    jsonPointer: '/data/articles',  // Optional: navigate to nested array
    metadata: ['source' => 'api']
);

$documents = $loader->load();
```

### Directory Loader

```php
use ClaudeAgents\RAG\Loaders\DirectoryLoader;

$loader = new DirectoryLoader(
    directoryPath: '/path/to/docs',
    extensions: ['txt', 'md', 'rst'],
    recursive: true,
    metadata: ['collection' => 'user_docs']
);

$documents = $loader->load();
```

### Web Loader

```php
use ClaudeAgents\RAG\Loaders\WebLoader;

$loader = new WebLoader(
    url: 'https://example.com/article',
    stripTags: true,
    metadata: ['source_type' => 'web']
);

$documents = $loader->load();
```

---

## Metadata Filtering

Filter retrieval results by metadata fields.

### Basic Filtering

```php
$pipeline = RAGPipeline::create($client);

// Add documents with metadata
$pipeline->addDocument(
    'PHP 8.3 Guide',
    $content,
    ['language' => 'php', 'version' => '8.3', 'year' => 2023]
);

// Query with filters
$result = $pipeline->query(
    'What are the new features?',
    topK: 5,
    filters: [
        'language' => 'php',
        'year' => 2023
    ]
);
```

### Multiple Value Filters (OR Logic)

```php
$result = $pipeline->query(
    'Programming concepts',
    topK: 5,
    filters: [
        'language' => ['php', 'python', 'javascript'],  // Match any
        'category' => 'tutorial'
    ]
);
```

---

## Query Transformation

Transform queries to improve retrieval quality.

### Multi-Query Generation

Generate multiple variations of a query for diverse retrieval.

```php
use ClaudeAgents\RAG\QueryTransformation\MultiQueryGenerator;

$generator = new MultiQueryGenerator($client, numQueries: 3);

$queries = $generator->generate('What is object-oriented programming?');
// Returns: [
//   'What is object-oriented programming?',
//   'Can you explain OOP concepts?',
//   'How does object-oriented programming work?'
// ]

// Retrieve for each query and combine results
foreach ($queries as $query) {
    $results[] = $retriever->retrieve($query, topK: 2);
}
$combinedResults = array_merge(...$results);
```

### HyDE (Hypothetical Document Embeddings)

Generate a hypothetical answer for better semantic search.

```php
use ClaudeAgents\RAG\QueryTransformation\HyDEGenerator;

$hyde = new HyDEGenerator($client);

// Generate hypothetical document
$hypothetical = $hyde->generate('What is machine learning?');

// Use for semantic search
$augmented = $hyde->augmentQuery('What is machine learning?');
// Returns query + hypothetical answer for richer retrieval
```

### Query Decomposition

Break complex queries into simpler sub-queries.

```php
use ClaudeAgents\RAG\QueryTransformation\QueryDecomposer;

$decomposer = new QueryDecomposer($client);

if ($decomposer->shouldDecompose($complexQuery)) {
    $subQueries = $decomposer->decompose(
        'Compare Python and JavaScript for web development'
    );
    // Returns: [
    //   'What is Python used for in web development?',
    //   'What is JavaScript used for in web development?',
    //   'What are the differences between Python and JavaScript?'
    // ]
}
```

---

## Re-ranking

Improve retrieval quality by re-ordering results.

### Score-Based Re-ranker

```php
use ClaudeAgents\RAG\Reranking\ScoreReranker;

$reranker = new ScoreReranker(weights: [
    'keyword_density' => 1.0,
    'exact_match' => 2.0,
    'title_match' => 1.5,
    'recency' => 0.5,
]);

$initialResults = $retriever->retrieve($query, topK: 20);
$reranked = $reranker->rerank($query, $initialResults, topK: 5);
```

### LLM-Based Re-ranker

```php
use ClaudeAgents\RAG\Reranking\LLMReranker;

$reranker = new LLMReranker(
    client: $client,
    model: 'claude-haiku-4-5'
);

$reranked = $reranker->rerank($query, $initialResults, topK: 5);
```

**Note**: LLM re-ranking is more accurate but slower and more expensive.

---

## Vector Stores

Semantic search using embeddings.

### In-Memory Vector Store

```php
use ClaudeAgents\RAG\VectorStore\InMemoryVectorStore;
use ClaudeAgents\RAG\VectorStore\VectorRetriever;

$vectorStore = new InMemoryVectorStore();

// Define embedding function (example with hypothetical service)
$embeddingFunction = function(string $text): array {
    // Call your embedding service (OpenAI, Cohere, etc.)
    return $embeddingService->embed($text);
};

$retriever = new VectorRetriever($vectorStore, $embeddingFunction);

// Use with RAG pipeline
$pipeline->withRetriever($retriever);
```

### Vector Retriever with Filters

```php
$results = $vectorStore->search(
    queryEmbedding: $embedding,
    topK: 5,
    filters: ['category' => 'documentation']
);
```

---

## Persistent Storage

Persist documents across sessions.

### File-Based Storage

```php
use ClaudeAgents\RAG\Storage\FileDocumentStore;

$store = new FileDocumentStore(storagePath: '/path/to/storage');

$store->add('doc1', 'Title', 'Content', ['key' => 'value']);
$document = $store->get('doc1');
$allDocs = $store->all();
$store->clear();
```

### Redis Storage

```php
use ClaudeAgents\RAG\Storage\RedisDocumentStore;

$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$store = new RedisDocumentStore($redis);

$store->add('doc1', 'Title', 'Content', ['key' => 'value']);
```

**Note**: Requires `redis` PHP extension or `predis/predis` package.

---

## Complete Example

Combining multiple advanced features:

```php
use ClaudeAgents\RAG\RAGPipeline;
use ClaudeAgents\RAG\Splitters\RecursiveCharacterTextSplitter;
use ClaudeAgents\RAG\Loaders\DirectoryLoader;
use ClaudeAgents\RAG\QueryTransformation\MultiQueryGenerator;
use ClaudeAgents\RAG\Reranking\ScoreReranker;
use ClaudeAgents\RAG\Storage\FileDocumentStore;

// Load documents
$loader = new DirectoryLoader('/path/to/docs', recursive: true);
$documents = $loader->load();

// Create pipeline with custom splitter
$pipeline = RAGPipeline::create($client);
$splitter = new RecursiveCharacterTextSplitter(chunkSize: 1000, overlap: 100);
$pipeline->withChunker($splitter);

// Add documents
foreach ($documents as $doc) {
    $pipeline->addDocument($doc['title'], $doc['content'], $doc['metadata']);
}

// Query with transformations
$queryGenerator = new MultiQueryGenerator($client);
$queries = $queryGenerator->generate($userQuery);

// Retrieve and re-rank
$allResults = [];
foreach ($queries as $query) {
    $results = $pipeline->query($query, topK: 10);
    $allResults = array_merge($allResults, $results['sources']);
}

$reranker = new ScoreReranker();
$finalResults = $reranker->rerank($userQuery, $allResults, topK: 5);
```

---

## Performance Tips

1. **Choose appropriate chunk sizes**: Smaller chunks (200-500 words) for precise retrieval, larger chunks (500-1000) for context.

2. **Use metadata filters**: Reduce search space by filtering before retrieval.

3. **Combine retrieval methods**: Use keyword retrieval + vector search (hybrid search).

4. **Re-rank selectively**: Retrieve more documents (topK=20) then re-rank to topK=5.

5. **Cache embeddings**: Store embeddings to avoid re-computing.

6. **Batch operations**: Load and process documents in batches.

---

## See Also

- [RAGAgent Documentation](RAGAgent.md)
- [RAGAgent Tutorial](tutorials/RAGAgent_Tutorial.md)
- [Complete Examples](../examples/rag_advanced_example.php)

