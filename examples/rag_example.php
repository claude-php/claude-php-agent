#!/usr/bin/env php
<?php
/**
 * RAG (Retrieval-Augmented Generation) Example
 *
 * Demonstrates knowledge base integration with document retrieval.
 * Shows how to build agents that can answer questions based on
 * provided documents and cite sources.
 *
 * Usage: php examples/rag_example.php
 * Requires: ANTHROPIC_API_KEY environment variable
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\RAGAgent;
use ClaudePhp\ClaudePhp;

// Check for API key
$apiKey = getenv('ANTHROPIC_API_KEY');
if (empty($apiKey)) {
    echo "Error: ANTHROPIC_API_KEY environment variable not set.\n";
    echo "Please set it before running this example.\n";
    exit(1);
}

// Initialize Claude client
$client = new ClaudePhp(apiKey: $apiKey);

echo "=== RAG Agent Example ===\n\n";
echo "Building knowledge base...\n\n";

// Create RAG agent
$agent = new RAGAgent($client, ['name' => 'rag_assistant']);

// Add documents to knowledge base using fluent interface
$agent
    ->addDocument(
        'PHP Basics',
        'PHP is a server-side scripting language widely used for web development. ' .
        'It was created by Rasmus Lerdorf in 1994. ' .
        'Variables in PHP are declared with the $ symbol and are case-sensitive. ' .
        'PHP supports both procedural and object-oriented programming paradigms. ' .
        'Arrays can be associative (with string keys) or indexed (with numeric keys). ' .
        'Functions are defined with the function keyword and can accept parameters.'
    )
    ->addDocument(
        'PHP Object-Oriented Programming',
        'Classes are defined with the class keyword in PHP. ' .
        'Objects are instances of classes created using the new keyword. ' .
        'Methods are functions defined inside a class. ' .
        'Properties are variables inside a class. ' .
        'PHP supports inheritance, interfaces, traits, and abstract classes. ' .
        'Visibility modifiers include public, protected, and private. ' .
        'Constructors are defined using __construct() magic method.'
    )
    ->addDocument(
        'Claude API',
        'The Claude API allows you to integrate Anthropic\'s Claude LLM into your applications. ' .
        'You need an API key to authenticate requests. ' .
        'The Messages API is the main endpoint for conversational interactions. ' .
        'You can specify the model (e.g., claude-sonnet-4-5), max tokens, and system prompt. ' .
        'The API supports streaming responses and tool use (function calling). ' .
        'Rate limits and pricing vary by model and usage tier.'
    )
    ->addDocument(
        'PHP Arrays',
        'PHP arrays are versatile data structures that can hold multiple values. ' .
        'Indexed arrays use numeric indices starting at 0. ' .
        'Associative arrays use string keys to access values. ' .
        'You can create arrays using array() or the short syntax []. ' .
        'Common array functions include array_push(), array_pop(), count(), and array_map(). ' .
        'The foreach loop is commonly used to iterate over arrays.'
    );

echo "Added " . $agent->getRag()->getDocumentCount() . " documents to knowledge base.\n";
echo "Created " . $agent->getRag()->getChunkCount() . " searchable chunks.\n\n";

// Query 1: Object-oriented programming
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Query 1: What is a class in PHP?\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$result1 = $agent->run('What is a class in PHP?');

if ($result1->isSuccess()) {
    echo "Answer:\n" . $result1->getAnswer() . "\n\n";
    
    $metadata1 = $result1->getMetadata();
    if (!empty($metadata1['sources'])) {
        echo "Sources Used:\n";
        foreach ($metadata1['sources'] as $source) {
            echo "  • " . $source['source'] . "\n";
        }
    }
    
    if (!empty($metadata1['citations'])) {
        echo "\nCitations: [Source " . implode('], [Source ', $metadata1['citations']) . "]\n";
    }
    
    echo "\nTokens Used: " . ($metadata1['tokens']['input'] ?? 0) . " input, " . 
         ($metadata1['tokens']['output'] ?? 0) . " output\n";
} else {
    echo "Error: " . $result1->getError() . "\n";
}

echo "\n";

// Query 2: API authentication
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Query 2: How do I authenticate with the Claude API?\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$result2 = $agent->run('How do I authenticate with the Claude API?');

if ($result2->isSuccess()) {
    echo "Answer:\n" . $result2->getAnswer() . "\n\n";
    
    $metadata2 = $result2->getMetadata();
    if (!empty($metadata2['sources'])) {
        echo "Sources Used:\n";
        foreach ($metadata2['sources'] as $source) {
            echo "  • " . $source['source'] . "\n";
        }
    }
} else {
    echo "Error: " . $result2->getError() . "\n";
}

echo "\n";

// Query 3: Arrays
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Query 3: What are associative arrays in PHP?\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$result3 = $agent->run('What are associative arrays in PHP?');

if ($result3->isSuccess()) {
    echo "Answer:\n" . $result3->getAnswer() . "\n\n";
    
    $metadata3 = $result3->getMetadata();
    if (!empty($metadata3['sources'])) {
        echo "Sources Used:\n";
        foreach ($metadata3['sources'] as $source) {
            echo "  • " . $source['source'] . "\n";
        }
    }
} else {
    echo "Error: " . $result3->getError() . "\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example completed successfully!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

