<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Agents;

use ClaudeAgents\Agents\RAGAgent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for RAGAgent.
 *
 * This test requires a valid ANTHROPIC_API_KEY environment variable.
 * It makes real API calls to Claude.
 */
class RAGAgentIntegrationTest extends TestCase
{
    private ?ClaudePhp $client = null;
    private ?RAGAgent $agent = null;

    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY environment variable not set');
        }

        $this->client = new ClaudePhp(apiKey: $apiKey);
        $this->agent = new RAGAgent($this->client, ['name' => 'test_rag_agent']);
    }

    public function testBasicRAGWorkflow(): void
    {
        // Add documents to knowledge base
        $this->agent->addDocument(
            'PHP Basics',
            'PHP is a popular server-side scripting language designed for web development. ' .
            'It was created by Rasmus Lerdorf in 1994. PHP code is executed on the server, ' .
            'generating HTML which is then sent to the client. Variables in PHP start with the $ symbol.'
        );

        $this->agent->addDocument(
            'PHP Data Types',
            'PHP supports several data types including strings, integers, floats, booleans, ' .
            'arrays, objects, and NULL. Arrays in PHP can be indexed or associative. ' .
            'Type juggling allows automatic conversion between types.'
        );

        // Query the knowledge base
        $result = $this->agent->run('Who created PHP?');

        // Assertions
        $this->assertTrue($result->isSuccess(), 'RAG query should succeed');
        $this->assertNotEmpty($result->getAnswer(), 'Should have an answer');

        // Check metadata
        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('sources', $metadata);
        $this->assertArrayHasKey('citations', $metadata);
        $this->assertArrayHasKey('document_count', $metadata);
        $this->assertArrayHasKey('chunk_count', $metadata);

        $this->assertEquals(2, $metadata['document_count']);
        $this->assertGreaterThan(0, $metadata['chunk_count']);

        // The answer should mention Rasmus Lerdorf
        $answer = strtolower($result->getAnswer());
        $this->assertStringContainsString('rasmus', $answer, 'Answer should mention Rasmus');
    }

    public function testMultipleQueriesSameKnowledgeBase(): void
    {
        // Add documents
        $this->agent->addDocuments([
            [
                'title' => 'Web Frameworks',
                'content' => 'Laravel is a PHP web framework with expressive syntax. ' .
                           'It follows the MVC pattern and includes features like routing, ' .
                           'authentication, and an ORM called Eloquent.',
            ],
            [
                'title' => 'Database Access',
                'content' => 'PHP can connect to databases using PDO or MySQLi. ' .
                           'PDO supports multiple database systems and uses prepared statements ' .
                           'for security. Eloquent ORM in Laravel provides an ActiveRecord implementation.',
            ],
        ]);

        // First query
        $result1 = $this->agent->run('What is Laravel?');
        $this->assertTrue($result1->isSuccess());
        $this->assertStringContainsString('framework', strtolower($result1->getAnswer()));

        // Second query using same knowledge base
        $result2 = $this->agent->run('How does PHP connect to databases?');
        $this->assertTrue($result2->isSuccess());
        $answer2 = strtolower($result2->getAnswer());
        $this->assertTrue(
            str_contains($answer2, 'pdo') || str_contains($answer2, 'mysqli'),
            'Answer should mention PDO or MySQLi'
        );
    }

    public function testRAGWithSourceCitations(): void
    {
        $this->agent->addDocument(
            'PHP Security',
            'Always validate and sanitize user input in PHP. Use prepared statements ' .
            'to prevent SQL injection. Never store passwords in plain text - use ' .
            'password_hash() and password_verify() functions.'
        );

        $result = $this->agent->run('How should I handle passwords in PHP?');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        $this->assertNotEmpty($metadata['sources']);

        // Check that sources have proper structure
        $source = $metadata['sources'][0];
        $this->assertArrayHasKey('source', $source);
        $this->assertArrayHasKey('text_preview', $source);
        $this->assertEquals('PHP Security', $source['source']);
    }

    public function testQueryWithNoRelevantDocuments(): void
    {
        // Add unrelated documents
        $this->agent->addDocument(
            'Cooking',
            'A recipe for chocolate cake requires flour, sugar, eggs, and cocoa powder.'
        );

        $result = $this->agent->run('What is machine learning?');

        $this->assertTrue($result->isSuccess());

        // The agent should acknowledge lack of information
        $answer = strtolower($result->getAnswer());
        $this->assertTrue(
            str_contains($answer, 'not') ||
            str_contains($answer, 'cannot') ||
            str_contains($answer, 'no information'),
            'Should indicate lack of relevant information'
        );
    }

    public function testLargeKnowledgeBase(): void
    {
        // Add multiple documents
        $topics = [
            'Variables' => 'PHP variables start with $. They are case-sensitive and follow naming rules.',
            'Functions' => 'Functions are defined with the function keyword. They can accept parameters and return values.',
            'Classes' => 'PHP supports object-oriented programming with classes, objects, inheritance, and interfaces.',
            'Arrays' => 'Arrays can store multiple values. They can be indexed or associative with string keys.',
            'Loops' => 'PHP has for, foreach, while, and do-while loops for iteration.',
            'Conditionals' => 'Use if, else, elseif, and switch statements for conditional logic.',
            'Strings' => 'Strings can be single or double quoted. Use concatenation with the . operator.',
            'Error Handling' => 'Use try-catch blocks for exceptions. Enable error reporting during development.',
        ];

        foreach ($topics as $title => $content) {
            $this->agent->addDocument($title, $content);
        }

        $result = $this->agent->run('How do I create a loop in PHP?');

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $this->assertEquals(8, $metadata['document_count']);

        // Should retrieve relevant chunks about loops
        $answer = strtolower($result->getAnswer());
        $this->assertTrue(
            str_contains($answer, 'for') ||
            str_contains($answer, 'while') ||
            str_contains($answer, 'foreach'),
            'Answer should mention loop types'
        );
    }

    public function testEmptyKnowledgeBase(): void
    {
        $result = $this->agent->run('What is PHP?');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No documents', $result->getError());
    }

    public function testTokenUsageTracking(): void
    {
        $this->agent->addDocument('Test', 'Some test content for token tracking.');

        $result = $this->agent->run('What is this about?');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('tokens', $metadata);
        $this->assertArrayHasKey('input', $metadata['tokens']);
        $this->assertArrayHasKey('output', $metadata['tokens']);
        $this->assertGreaterThan(0, $metadata['tokens']['input']);
        $this->assertGreaterThan(0, $metadata['tokens']['output']);
    }
}
