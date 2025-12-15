<?php
#!/usr/bin/env php
<?php
/**
 * Advanced RAG Example
 *
 * Demonstrates advanced RAG features:
 * - Custom text splitters (recursive, code-aware, markdown)
 * - Document loaders (CSV, JSON, directory)
 * - Metadata filtering
 * - Query transformation (multi-query, HyDE)
 * - Re-ranking
 * - Vector stores
 * - Persistent storage
 *
 * Usage: php examples/rag_advanced_example.php
 * Requires: ANTHROPIC_API_KEY environment variable
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\RAG\RAGPipeline;
use ClaudeAgents\RAG\Splitters\RecursiveCharacterTextSplitter;
use ClaudeAgents\RAG\Splitters\CodeTextSplitter;
use ClaudeAgents\RAG\Splitters\MarkdownTextSplitter;
use ClaudeAgents\RAG\Loaders\CSVLoader;
use ClaudeAgents\RAG\Loaders\JSONLoader;
use ClaudeAgents\RAG\Loaders\DirectoryLoader;
use ClaudeAgents\RAG\QueryTransformation\MultiQueryGenerator;
use ClaudeAgents\RAG\QueryTransformation\HyDEGenerator;
use ClaudeAgents\RAG\Reranking\ScoreReranker;
use ClaudeAgents\RAG\Storage\FileDocumentStore;
use ClaudePhp\ClaudePhp;

// Check for API key
$apiKey = getenv('ANTHROPIC_API_KEY');
if (empty($apiKey)) {
    echo "Error: ANTHROPIC_API_KEY environment variable not set.\n";
    exit(1);
}

$client = new ClaudePhp(apiKey: $apiKey);

echo "=== Advanced RAG Features Demo ===\n\n";

// ============================================================================
// 1. Custom Text Splitters
// ============================================================================
echo "━━━ 1. Custom Text Splitters ━━━\n\n";

// Recursive Character Splitter
echo "Recursive Character Text Splitter:\n";
$recursiveSplitter = new RecursiveCharacterTextSplitter(
    chunkSize: 500,
    overlap: 50
);

$longText = str_repeat("This is a paragraph. It contains multiple sentences. ", 100);
$chunks = $recursiveSplitter->chunk($longText);
echo "  Created " . count($chunks) . " chunks from long text\n";
echo "  First chunk: " . substr($chunks[0], 0, 100) . "...\n\n";

// Code-Aware Splitter
echo "Code Text Splitter (PHP):\n";
$codeSplitter = new CodeTextSplitter(language: 'php', chunkSize: 1000);
$phpCode = <<<'PHP'
<?php
class Example {
    public function method1() {
        return "test";
    }
    
    public function method2() {
        return "another";
    }
}

function helper() {
    return true;
}
PHP;
$codeChunks = $codeSplitter->chunk($phpCode);
echo "  Created " . count($codeChunks) . " chunks from PHP code\n\n";

// Markdown Splitter
echo "Markdown Text Splitter:\n";
$mdSplitter = new MarkdownTextSplitter(chunkSize: 500);
$markdown = <<<'MD'
# Main Title

## Section 1

This is content in section 1.

## Section 2

This is content in section 2.

### Subsection 2.1

More content here.
MD;
$mdChunks = $mdSplitter->chunk($markdown);
echo "  Created " . count($mdChunks) . " chunks from Markdown\n\n";

// ============================================================================
// 2. Document Loaders
// ============================================================================
echo "━━━ 2. Document Loaders ━━━\n\n";

// Create sample CSV
$csvPath = sys_get_temp_dir() . '/sample_docs.csv';
file_put_contents($csvPath, <<<CSV
title,content,category
PHP Basics,PHP is a server-side language used for web development,programming
Web Design,HTML and CSS are used to create web pages,design
Databases,SQL is used to query relational databases,data
CSV
);

echo "CSV Loader:\n";
$csvLoader = new CSVLoader($csvPath, contentColumn: 'content', titleColumn: 'title');
$csvDocs = $csvLoader->load();
echo "  Loaded " . count($csvDocs) . " documents from CSV\n";
echo "  First doc: " . $csvDocs[0]['title'] . "\n\n";

// Create sample JSON
$jsonPath = sys_get_temp_dir() . '/sample_docs.json';
file_put_contents($jsonPath, json_encode([
    ['title' => 'JavaScript', 'content' => 'JavaScript runs in the browser', 'tags' => ['web', 'frontend']],
    ['title' => 'Python', 'content' => 'Python is great for data science', 'tags' => ['data', 'ml']],
]));

echo "JSON Loader:\n";
$jsonLoader = new JSONLoader($jsonPath, contentField: 'content', titleField: 'title');
$jsonDocs = $jsonLoader->load();
echo "  Loaded " . count($jsonDocs) . " documents from JSON\n\n";

// Clean up temp files
unlink($csvPath);
unlink($jsonPath);

// ============================================================================
// 3. Metadata Filtering
// ============================================================================
echo "━━━ 3. Metadata Filtering ━━━\n\n";

$pipeline = RAGPipeline::create($client);

// Add documents with metadata
$pipeline->addDocument(
    'PHP 8.3 Features',
    'PHP 8.3 introduces new features like typed class constants and readonly amendments.',
    ['category' => 'programming', 'language' => 'php', 'year' => 2023]
);

$pipeline->addDocument(
    'JavaScript ES2023',
    'ES2023 brings new array methods and improved performance.',
    ['category' => 'programming', 'language' => 'javascript', 'year' => 2023]
);

$pipeline->addDocument(
    'CSS Grid Layout',
    'CSS Grid provides a powerful 2D layout system for web design.',
    ['category' => 'design', 'language' => 'css', 'year' => 2022]
);

echo "Added 3 documents with metadata\n";

// Query with filters
echo "Querying with filter: category=programming, language=php\n";
$result = $pipeline->query(
    'What are the new features?',
    topK: 2,
    filters: ['category' => 'programming', 'language' => 'php']
);
echo "Retrieved " . count($result['sources']) . " filtered sources\n";
echo "Answer: " . substr($result['answer'], 0, 150) . "...\n\n";

// ============================================================================
// 4. Query Transformation
// ============================================================================
echo "━━━ 4. Query Transformation ━━━\n\n";

// Multi-Query Generation
echo "Multi-Query Generation:\n";
$multiQuery = new MultiQueryGenerator($client, numQueries: 3);
$originalQuery = "What is object-oriented programming?";
echo "  Original: {$originalQuery}\n";
$variations = $multiQuery->generate($originalQuery);
echo "  Generated " . count($variations) . " variations:\n";
foreach (array_slice($variations, 1, 2) as $i => $var) {
    echo "    " . ($i + 1) . ". {$var}\n";
}
echo "\n";

// HyDE Generation
echo "HyDE (Hypothetical Document Embeddings):\n";
$hyde = new HyDEGenerator($client);
echo "  Query: What is machine learning?\n";
$hypothetical = $hyde->generate("What is machine learning?");
echo "  Hypothetical doc (first 100 chars): " . substr($hypothetical, 0, 100) . "...\n\n";

// ============================================================================
// 5. Re-ranking
// ============================================================================
echo "━━━ 5. Re-ranking ━━━\n\n";

$reranker = new ScoreReranker(weights: [
    'keyword_density' => 1.0,
    'exact_match' => 2.0,
    'title_match' => 1.5,
]);

$mockDocs = [
    ['text' => 'PHP is a programming language', 'source' => 'PHP Guide'],
    ['text' => 'JavaScript is also a language', 'source' => 'JS Guide'],
    ['text' => 'Python programming language features', 'source' => 'Python Docs'],
];

$reranked = $reranker->rerank('PHP programming', $mockDocs, topK: 2);
echo "Re-ranked documents (top 2):\n";
foreach ($reranked as $i => $doc) {
    echo "  " . ($i + 1) . ". " . $doc['source'] . "\n";
}
echo "\n";

// ============================================================================
// 6. Persistent Storage
// ============================================================================
echo "━━━ 6. Persistent Storage ━━━\n\n";

$storageDir = sys_get_temp_dir() . '/rag_storage_' . uniqid();
$fileStore = new FileDocumentStore($storageDir);

echo "File-based Document Store:\n";
$fileStore->add('doc1', 'Test Document', 'This is test content', ['type' => 'test']);
echo "  Added document to: {$storageDir}\n";
echo "  Document count: " . $fileStore->count() . "\n";

$retrieved = $fileStore->get('doc1');
echo "  Retrieved: " . $retrieved['title'] . "\n";

// Clean up
$fileStore->clear();
rmdir($storageDir);
echo "\n";

// ============================================================================
// Summary
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Advanced Features Demo Complete!\n\n";
echo "Features Demonstrated:\n";
echo "  ✓ Recursive Character Text Splitter\n";
echo "  ✓ Code-Aware Text Splitter (PHP)\n";
echo "  ✓ Markdown Text Splitter\n";
echo "  ✓ CSV Document Loader\n";
echo "  ✓ JSON Document Loader\n";
echo "  ✓ Metadata Filtering in Retrieval\n";
echo "  ✓ Multi-Query Generation\n";
echo "  ✓ HyDE Query Transformation\n";
echo "  ✓ Score-based Re-ranking\n";
echo "  ✓ File-based Persistent Storage\n";
echo "\nSee the code for usage examples of each feature.\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

