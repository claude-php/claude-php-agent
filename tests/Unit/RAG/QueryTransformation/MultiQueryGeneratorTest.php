<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\RAG\QueryTransformation;

use ClaudeAgents\RAG\QueryTransformation\MultiQueryGenerator;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;

class MultiQueryGeneratorTest extends TestCase
{
    private ClaudePhp $client;
    private MultiQueryGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClaudePhp::class);
        $this->generator = new MultiQueryGenerator($this->client, 3, 'claude-haiku-4-5');
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

    public function testGenerateMultipleQueries(): void
    {
        $query = 'What is artificial intelligence?';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage("1. How would you define artificial intelligence?\n2. Can you explain what AI means?\n3. What does artificial intelligence refer to?");

        $mockMessages->method('create')->willReturn($response);

        $result = $this->generator->generate($query);

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertEquals($query, $result[0]); // Original query should be first
    }

    public function testGenerateFallsBackOnError(): void
    {
        $query = 'Test query';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $result = $this->generator->generate($query);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($query, $result[0]);
    }

    public function testGenerateHandlesEmptyResponse(): void
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

        $result = $this->generator->generate($query);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($query, $result[0]);
    }

    public function testGenerateRemovesNumbering(): void
    {
        $query = 'Original question';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage("1. First variation\n2) Second variation\n3. Third variation");

        $mockMessages->method('create')->willReturn($response);

        $result = $this->generator->generate($query);

        $this->assertIsArray($result);
        $this->assertContains('First variation', $result);
        $this->assertContains('Second variation', $result);
        $this->assertContains('Third variation', $result);
    }

    public function testGenerateRespectsMaxQueries(): void
    {
        $query = 'Test question';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage("1. Query 1\n2. Query 2\n3. Query 3\n4. Query 4\n5. Query 5");

        $mockMessages->method('create')->willReturn($response);

        $result = $this->generator->generate($query);

        // Should be at most numQueries + 1 (original)
        $this->assertLessThanOrEqual(4, count($result));
        $this->assertEquals($query, $result[0]);
    }

    public function testGenerateFiltersOriginalQuery(): void
    {
        $query = 'What is PHP?';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage("1. What is PHP?\n2. Can you explain PHP?\n3. Tell me about PHP");

        $mockMessages->method('create')->willReturn($response);

        $result = $this->generator->generate($query);

        // Count how many times the original query appears
        $count = 0;
        foreach ($result as $q) {
            if ($q === $query) {
                $count++;
            }
        }

        // Original should only appear once (at the beginning)
        $this->assertEquals(1, $count);
    }

    public function testGenerateHandlesEmptyLines(): void
    {
        $query = 'Test';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage("1. First\n\n2. Second\n\n\n3. Third");

        $mockMessages->method('create')->willReturn($response);

        $result = $this->generator->generate($query);

        $this->assertIsArray($result);
        // Should not include empty strings
        foreach ($result as $q) {
            $this->assertNotEmpty($q);
        }
    }
}
