<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory;

use ClaudeAgents\Memory\Entities\EntityExtractor;
use ClaudeAgents\Memory\Entities\EntityStore;
use ClaudeAgents\Memory\EntityMemory;
use PHPUnit\Framework\TestCase;

class EntityMemoryTest extends TestCase
{
    private EntityExtractor $extractor;
    private EntityStore $store;
    private EntityMemory $memory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = $this->createMock(EntityExtractor::class);
        $this->store = new EntityStore();
        $this->memory = new EntityMemory(
            $this->extractor,
            $this->store,
            extractionInterval: 3
        );
    }

    public function testConstructorValidatesInterval(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EntityMemory($this->extractor, $this->store, extractionInterval: 0);
    }

    public function testAddMessage(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Hello']);

        $this->assertEquals(1, $this->memory->count());
    }

    public function testExtractsEntitiesAtInterval(): void
    {
        $this->extractor->expects($this->once())
            ->method('extract')
            ->willReturn([
                'John' => ['type' => 'person', 'attributes' => []],
            ]);

        // Add messages to trigger extraction
        $this->memory->add(['role' => 'user', 'content' => 'Message 1']);
        $this->memory->add(['role' => 'user', 'content' => 'Message 2']);
        $this->memory->add(['role' => 'user', 'content' => 'Message 3']);

        $this->assertEquals(1, $this->memory->getEntityCount());
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

    public function testGetContextIncludesEntities(): void
    {
        $this->store->store('John', [
            'type' => 'person',
            'mentions' => 5,
            'attributes' => ['role' => 'developer'],
        ]);

        $this->memory->add(['role' => 'user', 'content' => 'Test']);

        $context = $this->memory->getContext();

        // First message should be entity summary
        $this->assertStringContainsString('John', $context[0]['content']);
    }

    public function testGetEntity(): void
    {
        $this->store->store('John', ['type' => 'person']);

        $entity = $this->memory->getEntity('John');

        $this->assertIsArray($entity);
        $this->assertEquals('person', $entity['type']);
    }

    public function testSearchEntities(): void
    {
        $this->store->store('John Doe', ['type' => 'person']);
        $this->store->store('Jane Smith', ['type' => 'person']);

        $results = $this->memory->searchEntities('john');

        $this->assertCount(1, $results);
    }

    public function testGetEntitiesByType(): void
    {
        $this->store->store('John', ['type' => 'person']);
        $this->store->store('Acme', ['type' => 'organization']);

        $people = $this->memory->getEntitiesByType('person');

        $this->assertCount(1, $people);
        $this->assertArrayHasKey('John', $people);
    }

    public function testForceExtractEntities(): void
    {
        $this->extractor->expects($this->once())
            ->method('extract')
            ->willReturn(['Entity' => ['type' => 'test']]);

        $this->memory->add(['role' => 'user', 'content' => 'Test']);
        $this->memory->extractEntities();

        $this->assertEquals(1, $this->memory->getEntityCount());
    }

    public function testClear(): void
    {
        $this->store->store('Entity', ['type' => 'test']);
        $this->memory->add(['role' => 'user', 'content' => 'Test']);

        $this->memory->clear();

        $this->assertEquals(0, $this->memory->count());
        $this->assertEquals(0, $this->memory->getEntityCount());
    }

    public function testGetStats(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);

        $stats = $this->memory->getStats();

        $this->assertArrayHasKey('message_count', $stats);
        $this->assertArrayHasKey('entity_stats', $stats);
        $this->assertArrayHasKey('messages_since_extraction', $stats);
    }

    public function testGetEntityStore(): void
    {
        $store = $this->memory->getEntityStore();

        $this->assertInstanceOf(EntityStore::class, $store);
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
}
