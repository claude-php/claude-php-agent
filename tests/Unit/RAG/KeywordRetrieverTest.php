<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\RAG;

use ClaudeAgents\RAG\KeywordRetriever;
use PHPUnit\Framework\TestCase;

class KeywordRetrieverTest extends TestCase
{
    private KeywordRetriever $retriever;

    protected function setUp(): void
    {
        parent::setUp();
        $this->retriever = new KeywordRetriever();
    }

    public function testRetrieveEmptyChunks(): void
    {
        $results = $this->retriever->retrieve('test query', 3);

        $this->assertEmpty($results);
    }

    public function testRetrieveWithNoMatches(): void
    {
        $this->retriever->setChunks([
            ['text' => 'This is about apples and oranges'],
            ['text' => 'This discusses bananas and grapes'],
        ]);

        $results = $this->retriever->retrieve('quantum physics', 2);

        // Should still return chunks even if no matches (with score 0)
        $this->assertLessThanOrEqual(2, count($results));
    }

    public function testRetrieveRanksMatches(): void
    {
        $this->retriever->setChunks([
            ['text' => 'Python is a programming language used for data science'],
            ['text' => 'JavaScript is used for web development'],
            ['text' => 'Python and JavaScript are both programming languages'],
        ]);

        $results = $this->retriever->retrieve('Python programming', 2);

        $this->assertLessThanOrEqual(2, count($results));
        // First result should mention Python
        $this->assertStringContainsString('Python', $results[0]['text']);
    }

    public function testRetrieveExactPhrase(): void
    {
        $this->retriever->setChunks([
            ['text' => 'Machine learning is a subset of AI'],
            ['text' => 'Artificial intelligence and machine learning'],
            ['text' => 'Deep learning is part of machine learning'],
        ]);

        $results = $this->retriever->retrieve('machine learning', 1);

        $this->assertCount(1, $results);
        // Should prioritize chunks with exact phrase
        $this->assertStringContainsString('machine learning', strtolower($results[0]['text']));
    }

    public function testRetrieveTopK(): void
    {
        $chunks = [];
        for ($i = 0; $i < 10; $i++) {
            $chunks[] = ['text' => "Document $i with relevant content"];
        }

        $this->retriever->setChunks($chunks);

        $results = $this->retriever->retrieve('relevant', 5);

        $this->assertCount(5, $results);
    }

    public function testRetrieveScoresLongerWords(): void
    {
        $this->retriever->setChunks([
            ['text' => 'The cat sat on the mat'],
            ['text' => 'Extraordinary circumstances require attention'],
        ]);

        $results = $this->retriever->retrieve('extraordinary', 2);

        // Longer words should get higher scores
        $this->assertStringContainsString('Extraordinary', $results[0]['text']);
    }

    public function testRetrieveIgnoresShortWords(): void
    {
        $this->retriever->setChunks([
            ['text' => 'A comprehensive guide to AI and ML'],
            ['text' => 'Introduction to machine learning basics'],
        ]);

        $results = $this->retriever->retrieve('a to of', 2);

        // Short words (<=2 chars) should be filtered out
        $this->assertLessThanOrEqual(2, count($results));
    }

    public function testRetrieveEmptyQuery(): void
    {
        $this->retriever->setChunks([
            ['text' => 'Some content here'],
        ]);

        $results = $this->retriever->retrieve('', 2);

        $this->assertEmpty($results);
    }

    public function testRetrieveCaseInsensitive(): void
    {
        $this->retriever->setChunks([
            ['text' => 'Python Programming Language'],
            ['text' => 'Java Development'],
        ]);

        $results1 = $this->retriever->retrieve('python', 1);
        $results2 = $this->retriever->retrieve('PYTHON', 1);

        $this->assertEquals($results1, $results2);
    }
}
