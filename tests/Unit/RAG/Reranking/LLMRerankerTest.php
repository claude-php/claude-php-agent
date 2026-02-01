<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\RAG\Reranking;

use ClaudeAgents\RAG\Reranking\LLMReranker;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;

class LLMRerankerTest extends TestCase
{
    private ClaudePhp $client;
    private LLMReranker $reranker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClaudePhp::class);
        $this->reranker = new LLMReranker($this->client, 'claude-haiku-4-5');
    }

    private function createMockMessage(string $text): Message
    {
        $content = (object) [
            'type' => 'text',
            'text' => $text,
        ];

        return new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [$content],
            model: 'claude-haiku-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 10, output_tokens: 10)
        );
    }

    public function testRerankDocuments(): void
    {
        $query = 'What is PHP?';
        $documents = [
            ['text' => 'PHP is a programming language', 'id' => 1],
            ['text' => 'Python is also a programming language', 'id' => 2],
            ['text' => 'PHP stands for Hypertext Preprocessor', 'id' => 3],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        // Mock responses with different scores - will be called once per document
        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $this->createMockMessage('9'),
            $this->createMockMessage('3'),
            $this->createMockMessage('8')
        );

        $result = $this->reranker->rerank($query, $documents, 2);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        // Should return top 2 by score (doc 1 with score 9, doc 3 with score 8)
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals(3, $result[1]['id']);
    }

    public function testRerankEmptyDocuments(): void
    {
        $query = 'Test query';
        $documents = [];

        $result = $this->reranker->rerank($query, $documents, 5);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testRerankReturnsTopK(): void
    {
        $query = 'Search query';
        $documents = [
            ['text' => 'Document 1', 'id' => 1],
            ['text' => 'Document 2', 'id' => 2],
            ['text' => 'Document 3', 'id' => 3],
            ['text' => 'Document 4', 'id' => 4],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $this->createMockMessage('6'),
            $this->createMockMessage('8'),
            $this->createMockMessage('7'),
            $this->createMockMessage('9')
        );

        $result = $this->reranker->rerank($query, $documents, 2);

        $this->assertCount(2, $result);
        $this->assertEquals(4, $result[0]['id']); // Score 9
        $this->assertEquals(2, $result[1]['id']); // Score 8
    }

    public function testRerankHandlesApiError(): void
    {
        $query = 'Query';
        $documents = [
            ['text' => 'Document 1', 'id' => 1],
            ['text' => 'Document 2', 'id' => 2],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        // Simulate API errors - should return default score of 5.0
        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $result = $this->reranker->rerank($query, $documents, 2);

        // Should still return documents (with equal scores)
        $this->assertCount(2, $result);
    }

    public function testRerankClampsScoreToRange(): void
    {
        $query = 'Test';
        $documents = [
            ['text' => 'Doc 1', 'id' => 1],
            ['text' => 'Doc 2', 'id' => 2],
            ['text' => 'Doc 3', 'id' => 3],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        // Return out-of-range scores
        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $this->createMockMessage('15'),  // > 10, will be clamped to 10
            $this->createMockMessage('-5'),  // < 0, will be clamped to 0
            $this->createMockMessage('7')
        );

        $result = $this->reranker->rerank($query, $documents, 3);

        $this->assertCount(3, $result);
        // Doc 1 should be first (clamped to 10), then Doc 3 (7), then Doc 2 (clamped to 0)
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals(3, $result[1]['id']);
        $this->assertEquals(2, $result[2]['id']);
    }

    public function testRerankHandlesEmptyResponse(): void
    {
        $query = 'Query';
        $documents = [
            ['text' => 'Document', 'id' => 1],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [],
            model: 'claude-haiku-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 10, output_tokens: 10)
        );

        $mockMessages->method('create')->willReturn($mockResponse);

        $result = $this->reranker->rerank($query, $documents, 1);

        // Should use default score of 5.0
        $this->assertCount(1, $result);
    }

    public function testRerankHandlesMissingTextField(): void
    {
        $query = 'Query';
        $documents = [
            ['text' => 'Document', 'id' => 1],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $content = (object) [
            'type' => 'image',
        ];
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [$content],
            model: 'claude-haiku-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 10, output_tokens: 10)
        );

        $mockMessages->method('create')->willReturn($mockResponse);

        $result = $this->reranker->rerank($query, $documents, 1);

        // Should use default score of 5.0
        $this->assertCount(1, $result);
    }

    public function testRerankHandlesNonNumericScore(): void
    {
        $query = 'Query';
        $documents = [
            ['text' => 'Doc 1', 'id' => 1],
            ['text' => 'Doc 2', 'id' => 2],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $this->createMockMessage('not a number'),
            $this->createMockMessage('7')
        );

        $result = $this->reranker->rerank($query, $documents, 2);

        $this->assertCount(2, $result);
        // Doc with score 7 should be first
        $this->assertEquals(2, $result[0]['id']);
    }

    public function testRerankPreservesDocumentStructure(): void
    {
        $query = 'Query';
        $documents = [
            ['text' => 'Content', 'id' => 1, 'metadata' => ['key' => 'value']],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $mockMessages->method('create')->willReturn(
            $this->createMockMessage('8')
        );

        $result = $this->reranker->rerank($query, $documents, 1);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('metadata', $result[0]);
        $this->assertEquals('value', $result[0]['metadata']['key']);
    }
}
