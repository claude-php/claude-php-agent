<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory;

use ClaudeAgents\Memory\ConversationKGMemory;
use ClaudeAgents\Memory\Entities\EntityExtractor;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class ConversationKGMemoryTest extends TestCase
{
    private ClaudePhp $client;
    private EntityExtractor $extractor;
    private ConversationKGMemory $memory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClaudePhp::class);
        $this->extractor = $this->createMock(EntityExtractor::class);
        $this->memory = new ConversationKGMemory(
            $this->client,
            $this->extractor,
            ['extraction_interval' => 3]
        );
    }

    public function testAddMessage(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Hello']);

        $this->assertEquals(1, $this->memory->count());
    }

    public function testGetMessages(): void
    {
        $message = ['role' => 'user', 'content' => 'Test'];
        $this->memory->add($message);

        $messages = $this->memory->getMessages();

        $this->assertCount(1, $messages);
        $this->assertEquals($message, $messages[0]);
    }

    public function testGetContext(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);

        $context = $this->memory->getContext();

        $this->assertIsArray($context);
        $this->assertNotEmpty($context);
    }

    public function testGetEntities(): void
    {
        $entities = $this->memory->getEntities();

        $this->assertIsArray($entities);
        $this->assertEmpty($entities);
    }

    public function testGetRelationships(): void
    {
        $relationships = $this->memory->getRelationships();

        $this->assertIsArray($relationships);
        $this->assertEmpty($relationships);
    }

    public function testQuery(): void
    {
        // No relationships yet
        $results = $this->memory->query('John', 'works_at', 'Acme');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testClear(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);
        $this->memory->clear();

        $this->assertEquals(0, $this->memory->count());
        $this->assertEmpty($this->memory->getEntities());
        $this->assertEmpty($this->memory->getRelationships());
    }

    public function testGetStats(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);

        $stats = $this->memory->getStats();

        $this->assertArrayHasKey('message_count', $stats);
        $this->assertArrayHasKey('entity_count', $stats);
        $this->assertArrayHasKey('relationship_count', $stats);
        $this->assertArrayHasKey('messages_since_extraction', $stats);
    }

    public function testAddMany(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Message 1'],
            ['role' => 'assistant', 'content' => 'Response 1'],
        ];

        $this->memory->addMany($messages);

        $this->assertEquals(2, $this->memory->count());
    }

    public function testGetEntityRelationships(): void
    {
        $relationships = $this->memory->getEntityRelationships('John');

        $this->assertIsArray($relationships);
    }
}
