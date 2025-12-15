# RAGAgent Tutorial: Building a Knowledge-Based AI Assistant

## Introduction

Welcome to the RAGAgent tutorial! In this guide, you'll learn how to build a knowledge-based AI assistant that can answer questions by retrieving and synthesizing information from your own documents.

**What You'll Learn:**
- How RAG (Retrieval-Augmented Generation) works
- Setting up a RAGAgent
- Adding and organizing documents
- Querying your knowledge base
- Working with citations and sources
- Advanced techniques and optimization

**Prerequisites:**
- PHP 8.1 or higher
- Composer installed
- An Anthropic API key
- Basic understanding of PHP

**Time to Complete:** 30-45 minutes

## Part 1: Understanding RAG

### What is RAG?

RAG (Retrieval-Augmented Generation) combines two powerful concepts:

1. **Retrieval**: Finding relevant information from a knowledge base
2. **Generation**: Using an LLM to synthesize a coherent answer

This approach allows AI to answer questions based on your specific documents, reducing hallucinations and providing citeable responses.

### How RAGAgent Works

```
User Question → Retrieve Relevant Chunks → Build Context → Generate Answer
     ↓                    ↓                      ↓              ↓
  "What is PHP?"    [Chunk 1: "PHP is..."]   Combine    "PHP is a server-
                    [Chunk 2: "Variables..."]  chunks     side language..."
                    [Chunk 3: "Functions..."]  + query   [Source 0]
```

## Part 2: Setting Up Your First RAG Agent

### Step 1: Installation

First, ensure you have the package installed:

```bash
composer require your-org/claude-php-agent
```

### Step 2: Basic Setup

Create a new file `my_rag_agent.php`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agents\RAGAgent;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create RAG agent
$agent = new RAGAgent($client, [
    'name' => 'my_assistant'
]);

echo "RAG Agent initialized!\n";
```

Run it:

```bash
php my_rag_agent.php
```

### Step 3: Adding Your First Document

Let's add some knowledge:

```php
$agent->addDocument(
    title: 'Introduction to PHP',
    content: 'PHP (Hypertext Preprocessor) is a popular general-purpose ' .
             'scripting language that is especially suited to web development. ' .
             'It was created by Rasmus Lerdorf in 1994. PHP code is executed ' .
             'on the server, generating HTML which is then sent to the client.',
    metadata: [
        'category' => 'programming',
        'difficulty' => 'beginner'
    ]
);

echo "Document added!\n";
```

**Key Points:**
- `title`: Should be descriptive for source citation
- `content`: The actual knowledge to search
- `metadata`: Optional additional information

### Step 4: Your First Query

Now let's ask a question:

```php
$result = $agent->run('Who created PHP?');

if ($result->isSuccess()) {
    echo "Answer: " . $result->getAnswer() . "\n";
} else {
    echo "Error: " . $result->getError() . "\n";
}
```

**Expected Output:**
```
Answer: PHP was created by Rasmus Lerdorf in 1994. [Source 0]
```

## Part 3: Building a Real Knowledge Base

### Example: PHP Learning Assistant

Let's build a more complete example:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agents\RAGAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$agent = new RAGAgent($client, ['name' => 'php_tutor']);

// Add multiple related documents
$documents = [
    [
        'title' => 'PHP Variables',
        'content' => 'Variables in PHP start with the $ symbol, followed by the name. ' .
                    'Variable names are case-sensitive. They can store strings, integers, ' .
                    'floats, booleans, arrays, and objects. PHP is loosely typed, meaning ' .
                    'you don\'t need to declare variable types explicitly.'
    ],
    [
        'title' => 'PHP Functions',
        'content' => 'Functions in PHP are declared using the function keyword. ' .
                    'They can accept parameters and return values. Functions help organize ' .
                    'code into reusable blocks. PHP also supports anonymous functions ' .
                    '(closures) and arrow functions for concise syntax.'
    ],
    [
        'title' => 'PHP Arrays',
        'content' => 'PHP arrays can store multiple values in a single variable. ' .
                    'There are three types: indexed arrays (numeric keys), associative ' .
                    'arrays (named keys), and multidimensional arrays. Common array ' .
                    'functions include count(), array_push(), array_pop(), and array_map().'
    ],
    [
        'title' => 'PHP Classes',
        'content' => 'Object-oriented programming in PHP uses classes and objects. ' .
                    'Classes are defined with the class keyword. They can have properties ' .
                    '(variables) and methods (functions). PHP supports inheritance, ' .
                    'interfaces, traits, and abstract classes.'
    ],
];

// Add all documents at once
$agent->addDocuments($documents);

echo "Added {$agent->getRag()->getDocumentCount()} documents\n";
echo "Created {$agent->getRag()->getChunkCount()} searchable chunks\n\n";

// Interactive query loop
$questions = [
    'How do I declare a variable in PHP?',
    'What are the types of arrays in PHP?',
    'Can you explain PHP functions?',
];

foreach ($questions as $question) {
    echo "Q: $question\n";
    
    $result = $agent->run($question);
    
    if ($result->isSuccess()) {
        echo "A: " . $result->getAnswer() . "\n";
        
        // Show which sources were used
        $metadata = $result->getMetadata();
        $sources = array_map(fn($s) => $s['source'], $metadata['sources']);
        echo "Sources: " . implode(', ', $sources) . "\n";
    }
    
    echo "\n" . str_repeat('-', 60) . "\n\n";
}
```

## Part 4: Working with Sources and Citations

### Understanding Source Metadata

Every query result includes detailed source information:

```php
$result = $agent->run('What is a class?');
$metadata = $result->getMetadata();

foreach ($metadata['sources'] as $source) {
    echo "Document: {$source['source']}\n";
    echo "Preview: {$source['text_preview']}\n";
    echo "Metadata: " . json_encode($source['metadata']) . "\n";
    echo "\n";
}
```

### Extracting Citations

The agent automatically identifies which sources were cited:

```php
$citations = $metadata['citations'];
echo "This answer cited " . count($citations) . " sources\n";

foreach ($citations as $index) {
    $source = $metadata['sources'][$index];
    echo "- {$source['source']}\n";
}
```

### Full Example with Rich Output

```php
function displayResult($result) {
    if (!$result->isSuccess()) {
        echo "❌ Error: " . $result->getError() . "\n";
        return;
    }
    
    $metadata = $result->getMetadata();
    
    // Display answer with formatting
    echo "📝 Answer:\n";
    echo str_repeat('=', 70) . "\n";
    echo wordwrap($result->getAnswer(), 70) . "\n";
    echo str_repeat('=', 70) . "\n\n";
    
    // Display sources
    echo "📚 Sources:\n";
    foreach ($metadata['sources'] as $idx => $source) {
        $cited = in_array($idx, $metadata['citations']) ? '✓' : ' ';
        echo "  [$cited] {$source['source']}\n";
    }
    echo "\n";
    
    // Display stats
    echo "📊 Stats:\n";
    echo "  Documents: {$metadata['document_count']}\n";
    echo "  Chunks: {$metadata['chunk_count']}\n";
    echo "  Tokens: {$metadata['tokens']['input']} in, {$metadata['tokens']['output']} out\n";
    echo "\n";
}

// Usage
$result = $agent->run('Explain PHP arrays');
displayResult($result);
```

## Part 5: Advanced Techniques

### 1. Custom Chunking Strategy

Control how documents are split:

```php
use ClaudeAgents\RAG\Chunker;

// Create a chunker with custom settings
$chunker = new Chunker(
    chunkSize: 300,  // Smaller chunks for precise retrieval
    overlap: 50      // More overlap for context preservation
);

// Apply to agent
$agent->getRag()->withChunker($chunker);
```

**Chunking Guidelines:**
- **Small chunks (200-300 words)**: Best for FAQs, definitions
- **Medium chunks (400-500 words)**: Good balance for most content
- **Large chunks (600-800 words)**: Better for narrative content
- **Overlap (30-50 words)**: Prevents information loss at boundaries

### 2. Organizing Large Knowledge Bases

```php
class KnowledgeBaseManager {
    private RAGAgent $agent;
    private array $categories = [];
    
    public function __construct(RAGAgent $agent) {
        $this->agent = $agent;
    }
    
    public function addCategory(string $name, array $documents): void {
        foreach ($documents as $doc) {
            $this->agent->addDocument(
                $doc['title'],
                $doc['content'],
                array_merge($doc['metadata'] ?? [], ['category' => $name])
            );
        }
        
        $this->categories[$name] = count($documents);
    }
    
    public function getStats(): array {
        return [
            'categories' => count($this->categories),
            'documents' => $this->agent->getRag()->getDocumentCount(),
            'chunks' => $this->agent->getRag()->getChunkCount(),
        ];
    }
}

// Usage
$kb = new KnowledgeBaseManager($agent);

$kb->addCategory('Variables', [
    ['title' => 'Variable Basics', 'content' => '...'],
    ['title' => 'Variable Scope', 'content' => '...'],
]);

$kb->addCategory('Functions', [
    ['title' => 'Function Basics', 'content' => '...'],
    ['title' => 'Arrow Functions', 'content' => '...'],
]);

print_r($kb->getStats());
```

### 3. Implementing Question Pre-Processing

Improve retrieval quality by processing questions:

```php
class SmartRAGAgent {
    private RAGAgent $agent;
    
    public function __construct(RAGAgent $agent) {
        $this->agent = $agent;
    }
    
    public function ask(string $question): AgentResult {
        // Expand abbreviations
        $question = str_replace('OOP', 'object-oriented programming', $question);
        $question = str_replace('var', 'variable', $question);
        
        // Add context if question is too short
        if (str_word_count($question) < 4) {
            $question = "Regarding PHP, " . $question;
        }
        
        return $this->agent->run($question);
    }
}

// Usage
$smartAgent = new SmartRAGAgent($agent);
$result = $smartAgent->ask('What is OOP?');
```

### 4. Implementing Response Caching

Cache frequent queries:

```php
class CachedRAGAgent {
    private RAGAgent $agent;
    private array $cache = [];
    private int $maxCacheSize = 100;
    
    public function __construct(RAGAgent $agent) {
        $this->agent = $agent;
    }
    
    public function query(string $question): AgentResult {
        $key = md5(strtolower(trim($question)));
        
        if (isset($this->cache[$key])) {
            echo "[Cache Hit]\n";
            return $this->cache[$key];
        }
        
        $result = $this->agent->run($question);
        
        // Add to cache
        if (count($this->cache) >= $this->maxCacheSize) {
            array_shift($this->cache); // Remove oldest
        }
        
        $this->cache[$key] = $result;
        
        return $result;
    }
    
    public function clearCache(): void {
        $this->cache = [];
    }
}
```

### 5. Building a Conversational Interface

Create a REPL for interactive queries:

```php
function runInteractiveMode(RAGAgent $agent): void {
    echo "RAG Agent Interactive Mode\n";
    echo "Type 'quit' to exit, 'stats' for statistics\n\n";
    
    while (true) {
        echo "> ";
        $input = trim(fgets(STDIN));
        
        if ($input === 'quit' || $input === 'exit') {
            echo "Goodbye!\n";
            break;
        }
        
        if ($input === 'stats') {
            $rag = $agent->getRag();
            echo "Documents: {$rag->getDocumentCount()}\n";
            echo "Chunks: {$rag->getChunkCount()}\n";
            continue;
        }
        
        if (empty($input)) {
            continue;
        }
        
        $result = $agent->run($input);
        
        if ($result->isSuccess()) {
            echo "\n" . $result->getAnswer() . "\n\n";
            
            $metadata = $result->getMetadata();
            if (!empty($metadata['citations'])) {
                echo "Sources: ";
                foreach ($metadata['citations'] as $idx) {
                    echo "[{$metadata['sources'][$idx]['source']}] ";
                }
                echo "\n";
            }
        } else {
            echo "Error: " . $result->getError() . "\n";
        }
        
        echo "\n";
    }
}

// Usage
runInteractiveMode($agent);
```

## Part 6: Real-World Use Cases

### Use Case 1: API Documentation Assistant

```php
// Load API documentation
$apiDocs = [
    'Authentication' => file_get_contents('docs/auth.md'),
    'Users API' => file_get_contents('docs/users.md'),
    'Products API' => file_get_contents('docs/products.md'),
    'Orders API' => file_get_contents('docs/orders.md'),
];

foreach ($apiDocs as $title => $content) {
    $agent->addDocument($title, $content, ['type' => 'api_doc']);
}

// Query examples
$queries = [
    'How do I authenticate?',
    'What endpoints are available for users?',
    'How do I create an order?',
];

foreach ($queries as $query) {
    $result = $agent->run($query);
    if ($result->isSuccess()) {
        echo "Q: $query\n";
        echo "A: {$result->getAnswer()}\n\n";
    }
}
```

### Use Case 2: Product Knowledge Base

```php
// Load product catalog
$products = [
    [
        'name' => 'Laptop Pro 15',
        'description' => '15-inch laptop with Intel i7 processor, 16GB RAM, 512GB SSD',
        'price' => 1299,
        'category' => 'laptops'
    ],
    [
        'name' => 'Wireless Mouse',
        'description' => 'Ergonomic wireless mouse with 6 programmable buttons',
        'price' => 49,
        'category' => 'accessories'
    ],
    // ... more products
];

foreach ($products as $product) {
    $agent->addDocument(
        $product['name'],
        $product['description'],
        ['price' => $product['price'], 'category' => $product['category']]
    );
}

// Customer queries
$result = $agent->run('What laptops do you have?');
```

### Use Case 3: Internal Company Wiki

```php
// Load wiki pages
$wikiPages = [
    'Onboarding' => 'Welcome to the team! Here is how to get started...',
    'Code Review Guidelines' => 'All code must be reviewed before merging...',
    'Deployment Process' => 'To deploy to production, follow these steps...',
    'Benefits Guide' => 'We offer health insurance, 401k, and unlimited PTO...',
];

foreach ($wikiPages as $title => $content) {
    $agent->addDocument($title, $content, ['type' => 'wiki']);
}

// Employee queries
$result = $agent->run('How do I deploy code to production?');
```

## Part 7: Best Practices

### 1. Document Preparation

```php
// ✅ Good: Clear, focused documents
$agent->addDocument(
    'Password Reset Process',
    'To reset your password: 1) Click "Forgot Password" 2) Check your email ' .
    '3) Click the reset link 4) Enter your new password'
);

// ❌ Bad: Mixed topics
$agent->addDocument(
    'Various Things',
    'Password reset... Also, our company was founded in 2010... ' .
    'The CEO likes coffee...'
);
```

### 2. Effective Querying

```php
// ✅ Good: Specific questions
$result = $agent->run('How do I reset my password?');

// ❌ Bad: Vague questions
$result = $agent->run('Help me with something');

// ✅ Good: Questions with context
$result = $agent->run('What are the steps to deploy to production?');

// ❌ Bad: Too broad
$result = $agent->run('Tell me everything');
```

### 3. Error Handling

```php
function safeQuery(RAGAgent $agent, string $question): void {
    try {
        $result = $agent->run($question);
        
        if (!$result->isSuccess()) {
            error_log("Query failed: " . $result->getError());
            echo "Sorry, I couldn't answer that question.\n";
            return;
        }
        
        echo $result->getAnswer() . "\n";
        
    } catch (\Exception $e) {
        error_log("Exception in RAG query: " . $e->getMessage());
        echo "An error occurred. Please try again later.\n";
    }
}
```

### 4. Performance Monitoring

```php
class PerformanceMonitor {
    private array $queries = [];
    
    public function monitorQuery(RAGAgent $agent, string $question): AgentResult {
        $start = microtime(true);
        
        $result = $agent->run($question);
        
        $duration = microtime(true) - $start;
        $metadata = $result->getMetadata();
        
        $this->queries[] = [
            'question' => $question,
            'duration' => $duration,
            'tokens' => $metadata['tokens'] ?? [],
            'success' => $result->isSuccess(),
        ];
        
        return $result;
    }
    
    public function getStats(): array {
        $totalDuration = array_sum(array_column($this->queries, 'duration'));
        $avgDuration = $totalDuration / count($this->queries);
        
        $totalTokens = array_sum(array_map(
            fn($q) => ($q['tokens']['input'] ?? 0) + ($q['tokens']['output'] ?? 0),
            $this->queries
        ));
        
        return [
            'total_queries' => count($this->queries),
            'avg_duration' => $avgDuration,
            'total_tokens' => $totalTokens,
        ];
    }
}
```

## Part 8: Troubleshooting

### Problem: No relevant results

**Solution:**
```php
// Check knowledge base
$rag = $agent->getRag();
if ($rag->getDocumentCount() === 0) {
    echo "No documents loaded!\n";
}

// Try different chunk sizes
$chunker = new Chunker(chunkSize: 200, overlap: 30);
$agent->getRag()->withChunker($chunker);
```

### Problem: Answers lack context

**Solution:**
```php
// Increase chunk size for more context
$chunker = new Chunker(chunkSize: 600, overlap: 100);
$agent->getRag()->withChunker($chunker);
```

### Problem: High token usage

**Solution:**
```php
// Retrieve fewer chunks
$result = $agent->getRag()->query($question, topK: 2); // Instead of default 3
```

## Conclusion

You've now learned how to:
- ✅ Create and configure a RAGAgent
- ✅ Add documents and build a knowledge base
- ✅ Query documents and handle results
- ✅ Work with sources and citations
- ✅ Implement advanced features
- ✅ Apply best practices

### Next Steps

1. Build your own knowledge base with your documents
2. Experiment with different chunk sizes
3. Implement caching for production use
4. Explore semantic retrieval for better accuracy
5. Check out the other agents in the framework

### Additional Resources

- [RAGAgent Documentation](../RAGAgent.md)
- [Complete Example](../../examples/rag_example.php)
- [Agent Selection Guide](../agent-selection-guide.md)
- [API Reference](../../README.md)

Happy building! 🚀

