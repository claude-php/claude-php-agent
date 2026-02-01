<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\RAG\QueryTransformation;

use ClaudeAgents\RAG\QueryTransformation\QueryDecomposer;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;

class QueryDecomposerTest extends TestCase
{
    private ClaudePhp $client;
    private QueryDecomposer $decomposer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClaudePhp::class);
        $this->decomposer = new QueryDecomposer($this->client, 'claude-haiku-4-5');
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

    public function testDecomposeComplexQuery(): void
    {
        $query = 'What is PHP and how does it compare to Python?';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage("1. What is PHP?\n2. What is Python?\n3. How do PHP and Python compare?");

        $mockMessages->method('create')->willReturn($response);

        $result = $this->decomposer->decompose($query);

        $this->assertIsArray($result);
        $this->assertGreaterThan(1, count($result));
        $this->assertContains('What is PHP?', $result);
        $this->assertContains('What is Python?', $result);
    }

    public function testDecomposeFallsBackOnError(): void
    {
        $query = 'Test query';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $result = $this->decomposer->decompose($query);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($query, $result[0]);
    }

    public function testDecomposeHandlesEmptyResponse(): void
    {
        $query = 'Another query';

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

        $result = $this->decomposer->decompose($query);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($query, $result[0]);
    }

    public function testDecomposeRemovesNumbering(): void
    {
        $query = 'Complex question';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage("1. First sub-question\n2) Second sub-question\n3. Third sub-question");

        $mockMessages->method('create')->willReturn($response);

        $result = $this->decomposer->decompose($query);

        $this->assertIsArray($result);
        $this->assertContains('First sub-question', $result);
        $this->assertContains('Second sub-question', $result);
        $this->assertContains('Third sub-question', $result);
    }

    public function testShouldDecomposeWithMultipleIndicators(): void
    {
        $this->assertTrue($this->decomposer->shouldDecompose('What is X and Y?'));
        $this->assertTrue($this->decomposer->shouldDecompose('Compare A and B'));
        $this->assertTrue($this->decomposer->shouldDecompose('What is the difference between X and Y?'));
        $this->assertTrue($this->decomposer->shouldDecompose('Tell me about both X and Y'));
        $this->assertTrue($this->decomposer->shouldDecompose('Explain X as well as Y'));
    }

    public function testShouldDecomposeWithMultipleQuestionMarks(): void
    {
        $this->assertTrue($this->decomposer->shouldDecompose('What is X? And what is Y?'));
    }

    public function testShouldDecomposeWithLongQuery(): void
    {
        $longQuery = str_repeat('word ', 25); // 25 words
        $this->assertTrue($this->decomposer->shouldDecompose($longQuery));
    }

    public function testShouldNotDecomposeSimpleQuery(): void
    {
        $this->assertFalse($this->decomposer->shouldDecompose('What is PHP?'));
        $this->assertFalse($this->decomposer->shouldDecompose('Short question'));
    }

    public function testDecomposeHandlesEmptyLines(): void
    {
        $query = 'Test';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage("1. First\n\n2. Second\n\n\n3. Third");

        $mockMessages->method('create')->willReturn($response);

        $result = $this->decomposer->decompose($query);

        $this->assertIsArray($result);
        // Should not include empty strings
        foreach ($result as $q) {
            $this->assertNotEmpty($q);
        }
    }

    public function testDecomposeHandlesNoSubQueries(): void
    {
        $query = 'Simple query';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage('');

        $mockMessages->method('create')->willReturn($response);

        $result = $this->decomposer->decompose($query);

        // Should return original query if decomposition yields nothing
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($query, $result[0]);
    }
}
