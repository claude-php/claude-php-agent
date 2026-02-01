<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\RAG\QueryTransformation;

use ClaudeAgents\RAG\QueryTransformation\HyDEGenerator;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;

class HyDEGeneratorTest extends TestCase
{
    private ClaudePhp $client;
    private HyDEGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClaudePhp::class);
        $this->generator = new HyDEGenerator($this->client, 'claude-haiku-4-5');
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

    public function testGenerateHypotheticalDocument(): void
    {
        $query = 'What is the capital of France?';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage('The capital of France is Paris, a major European city...');

        $mockMessages->method('create')->willReturn($response);

        $result = $this->generator->generate($query);

        $this->assertIsString($result);
        $this->assertStringContainsString('Paris', $result);
    }

    public function testGenerateFallsBackOnError(): void
    {
        $query = 'What is machine learning?';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        // Simulate API error
        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $result = $this->generator->generate($query);

        // Should return original query on error
        $this->assertEquals($query, $result);
    }

    public function testGenerateHandlesEmptyResponse(): void
    {
        $query = 'Test query';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        // Create a message with no content
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

        // Should return original query
        $this->assertEquals($query, $result);
    }

    public function testGenerateHandlesMissingTextField(): void
    {
        $query = 'Another test query';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        // Create a message with content but no 'text' field
        $content = (object) [
            'type' => 'image',
            'source' => 'data',
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

        $result = $this->generator->generate($query);

        // Should return original query
        $this->assertEquals($query, $result);
    }

    public function testAugmentQuery(): void
    {
        $query = 'What is PHP?';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage('PHP is a server-side scripting language used for web development.');

        $mockMessages->method('create')->willReturn($response);

        $result = $this->generator->augmentQuery($query);

        $this->assertIsString($result);
        $this->assertStringContainsString($query, $result);
        $this->assertStringContainsString('PHP', $result);
        $this->assertStringContainsString("\n\n", $result);
    }

    public function testGenerateTrimsWhitespace(): void
    {
        $query = 'Test question';

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage("  \n  Answer with whitespace  \n  ");

        $mockMessages->method('create')->willReturn($response);

        $result = $this->generator->generate($query);

        $this->assertEquals('Answer with whitespace', $result);
    }
}
