<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory\Entities;

use ClaudeAgents\Memory\Entities\EntityExtractor;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;

class EntityExtractorTest extends TestCase
{
    private ClaudePhp $client;
    private EntityExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClaudePhp::class);
        $this->extractor = new EntityExtractor($this->client, [
            'model' => 'claude-haiku-4-5',
        ]);
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

    public function testExtractWithEmptyMessages(): void
    {
        $entities = $this->extractor->extract([]);

        $this->assertIsArray($entities);
        $this->assertEmpty($entities);
    }

    public function testExtractWithStringContent(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'John works at Acme Corp in New York'],
        ];

        // Mock the response
        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage(json_encode([
            'John' => [
                'type' => 'person',
                'attributes' => ['role' => 'employee'],
            ],
            'Acme Corp' => [
                'type' => 'organization',
                'attributes' => [],
            ],
            'New York' => [
                'type' => 'place',
                'attributes' => [],
            ],
        ]));

        $mockMessages->method('create')->willReturn($response);

        $entities = $this->extractor->extract($messages);

        $this->assertIsArray($entities);
        $this->assertArrayHasKey('John', $entities);
        $this->assertArrayHasKey('Acme Corp', $entities);
        $this->assertArrayHasKey('New York', $entities);
        $this->assertEquals('person', $entities['John']['type']);
        $this->assertArrayHasKey('mentions', $entities['John']);
        $this->assertArrayHasKey('first_seen', $entities['John']);
        $this->assertArrayHasKey('last_seen', $entities['John']);
    }

    public function testExtractWithArrayContent(): void
    {
        $messages = [
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Alice is the CEO of TechStart'],
                    ['type' => 'tool_use', 'name' => 'search'],
                ],
            ],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage(json_encode([
            'Alice' => [
                'type' => 'person',
                'attributes' => ['role' => 'CEO'],
            ],
        ]));

        $mockMessages->method('create')->willReturn($response);

        $entities = $this->extractor->extract($messages);

        $this->assertIsArray($entities);
        $this->assertArrayHasKey('Alice', $entities);
    }

    public function testExtractSingleEntity(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'John is a developer'],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage(json_encode([
            'John' => [
                'type' => 'person',
                'attributes' => ['role' => 'developer'],
            ],
        ]));

        $mockMessages->method('create')->willReturn($response);

        $entity = $this->extractor->extractEntity($messages, 'John');

        $this->assertIsArray($entity);
        $this->assertEquals('person', $entity['type']);
    }

    public function testExtractSingleEntityNotFound(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Alice is here'],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage(json_encode([
            'Alice' => [
                'type' => 'person',
                'attributes' => [],
            ],
        ]));

        $mockMessages->method('create')->willReturn($response);

        $entity = $this->extractor->extractEntity($messages, 'Bob');

        $this->assertNull($entity);
    }

    public function testFallbackToSimpleExtraction(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'John and Alice went to Paris last summer'],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        // Simulate API failure
        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $entities = $this->extractor->extract($messages);

        // Should fallback to simple extraction with capitalized words
        $this->assertIsArray($entities);
        $this->assertArrayHasKey('John', $entities);
        $this->assertArrayHasKey('Alice', $entities);
        $this->assertArrayHasKey('Paris', $entities);
        $this->assertEquals('unknown', $entities['John']['type']);
    }

    public function testHandlesInvalidJson(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Test message with Names'],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage('This is not valid JSON');

        $mockMessages->method('create')->willReturn($response);

        $entities = $this->extractor->extract($messages);

        // Should fallback to simple extraction
        $this->assertIsArray($entities);
    }

    public function testHandlesJsonWithMarkdown(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Bob works here'],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $response = $this->createMockMessage("```json\n" . json_encode([
            'Bob' => [
                'type' => 'person',
                'attributes' => [],
            ],
        ]) . "\n```");

        $mockMessages->method('create')->willReturn($response);

        $entities = $this->extractor->extract($messages);

        $this->assertIsArray($entities);
        $this->assertArrayHasKey('Bob', $entities);
    }

    public function testSimpleExtractFiltersCommonWords(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'The meeting with John was great'],
        ];

        $mockMessages = $this->createMock(Messages::class);
        $this->client->method('messages')->willReturn($mockMessages);

        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $entities = $this->extractor->extract($messages);

        // 'The' should be filtered out
        $this->assertArrayNotHasKey('The', $entities);
        $this->assertArrayHasKey('John', $entities);
    }
}
