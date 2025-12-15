<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory\Entities;

use ClaudeAgents\Memory\Entities\EntityStore;
use PHPUnit\Framework\TestCase;

class EntityStoreTest extends TestCase
{
    private EntityStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new EntityStore();
    }

    public function testStore(): void
    {
        $this->store->store('John', [
            'type' => 'person',
            'attributes' => ['role' => 'developer'],
        ]);

        $this->assertTrue($this->store->has('John'));
    }

    public function testGet(): void
    {
        $data = [
            'type' => 'person',
            'attributes' => ['role' => 'developer'],
        ];

        $this->store->store('John', $data);
        $entity = $this->store->get('John');

        $this->assertIsArray($entity);
        $this->assertEquals('person', $entity['type']);
    }

    public function testGetNonExistent(): void
    {
        $this->assertNull($this->store->get('NonExistent'));
    }

    public function testStoreMany(): void
    {
        $entities = [
            'Alice' => ['type' => 'person'],
            'Bob' => ['type' => 'person'],
        ];

        $this->store->storeMany($entities);

        $this->assertEquals(2, $this->store->count());
    }

    public function testUpdateExisting(): void
    {
        $this->store->store('John', [
            'type' => 'person',
            'mentions' => 1,
            'attributes' => ['role' => 'developer'],
        ]);

        $this->store->store('John', [
            'type' => 'person',
            'mentions' => 1,
            'attributes' => ['level' => 'senior'],
        ]);

        $entity = $this->store->get('John');
        $this->assertEquals(2, $entity['mentions']);
        $this->assertArrayHasKey('role', $entity['attributes']);
        $this->assertArrayHasKey('level', $entity['attributes']);
    }

    public function testGetByType(): void
    {
        $this->store->store('John', ['type' => 'person']);
        $this->store->store('Acme', ['type' => 'organization']);
        $this->store->store('Jane', ['type' => 'person']);

        $people = $this->store->getByType('person');

        $this->assertCount(2, $people);
        $this->assertArrayHasKey('John', $people);
        $this->assertArrayHasKey('Jane', $people);
    }

    public function testSearch(): void
    {
        $this->store->store('John Doe', ['type' => 'person']);
        $this->store->store('Jane Smith', ['type' => 'person']);

        $results = $this->store->search('john');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('John Doe', $results);
    }

    public function testGetMostMentioned(): void
    {
        $this->store->store('Popular', ['mentions' => 10]);
        $this->store->store('Less Popular', ['mentions' => 5]);
        $this->store->store('Least Popular', ['mentions' => 1]);

        $top = $this->store->getMostMentioned(2);

        $this->assertCount(2, $top);
        $keys = array_keys($top);
        $this->assertEquals('Popular', $keys[0]);
    }

    public function testGetRecent(): void
    {
        $this->store->store('Old', ['last_seen' => time() - 1000]);
        sleep(1);
        $this->store->store('Recent', ['last_seen' => time()]);

        $recent = $this->store->getRecent(1);

        $this->assertCount(1, $recent);
        $this->assertArrayHasKey('Recent', $recent);
    }

    public function testForget(): void
    {
        $this->store->store('ToForget', ['type' => 'test']);

        $this->assertTrue($this->store->forget('ToForget'));
        $this->assertFalse($this->store->has('ToForget'));
    }

    public function testForgetNonExistent(): void
    {
        $this->assertFalse($this->store->forget('NonExistent'));
    }

    public function testClear(): void
    {
        $this->store->store('Entity1', ['type' => 'test']);
        $this->store->store('Entity2', ['type' => 'test']);

        $this->store->clear();

        $this->assertEquals(0, $this->store->count());
    }

    public function testAll(): void
    {
        $this->store->store('Entity1', ['type' => 'test']);
        $this->store->store('Entity2', ['type' => 'test']);

        $all = $this->store->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('Entity1', $all);
        $this->assertArrayHasKey('Entity2', $all);
    }

    public function testGetStats(): void
    {
        $this->store->store('Person1', ['type' => 'person', 'mentions' => 5]);
        $this->store->store('Place1', ['type' => 'place', 'mentions' => 3]);

        $stats = $this->store->getStats();

        $this->assertEquals(2, $stats['total_entities']);
        $this->assertEquals(8, $stats['total_mentions']);
        $this->assertEquals(4, $stats['avg_mentions']);
        $this->assertArrayHasKey('types', $stats);
    }
}
