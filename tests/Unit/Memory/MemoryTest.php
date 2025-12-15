<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory;

use ClaudeAgents\Memory\Memory;
use PHPUnit\Framework\TestCase;

class MemoryTest extends TestCase
{
    private Memory $memory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memory = new Memory();
    }

    public function testSetAndGet(): void
    {
        $this->memory->set('key', 'value');

        $this->assertEquals('value', $this->memory->get('key'));
    }

    public function testGetWithDefault(): void
    {
        $this->assertEquals('default', $this->memory->get('nonexistent', 'default'));
    }

    public function testHas(): void
    {
        $this->memory->set('exists', 'value');

        $this->assertTrue($this->memory->has('exists'));
        $this->assertFalse($this->memory->has('does_not_exist'));
    }

    public function testForget(): void
    {
        $this->memory->set('temp', 'value');
        $this->assertTrue($this->memory->has('temp'));

        $this->memory->forget('temp');
        $this->assertFalse($this->memory->has('temp'));
    }

    public function testAll(): void
    {
        $this->memory->set('key1', 'value1');
        $this->memory->set('key2', 'value2');

        $all = $this->memory->all();

        $this->assertCount(2, $all);
        $this->assertEquals('value1', $all['key1']);
        $this->assertEquals('value2', $all['key2']);
    }

    public function testClear(): void
    {
        $this->memory->set('key1', 'value1');
        $this->memory->set('key2', 'value2');

        $this->memory->clear();

        $this->assertEmpty($this->memory->all());
    }

    public function testIncrement(): void
    {
        $result = $this->memory->increment('counter');
        $this->assertEquals(1, $result);

        $result = $this->memory->increment('counter');
        $this->assertEquals(2, $result);

        $result = $this->memory->increment('counter', 5);
        $this->assertEquals(7, $result);
    }

    public function testDecrement(): void
    {
        $this->memory->set('counter', 10);

        $result = $this->memory->decrement('counter');
        $this->assertEquals(9, $result);

        $result = $this->memory->decrement('counter', 3);
        $this->assertEquals(6, $result);
    }

    public function testPush(): void
    {
        $this->memory->push('items', 'first');
        $this->memory->push('items', 'second');
        $this->memory->push('items', 'third');

        $items = $this->memory->get('items');

        $this->assertIsArray($items);
        $this->assertCount(3, $items);
        $this->assertEquals(['first', 'second', 'third'], $items);
    }

    public function testPushOnNonArray(): void
    {
        $this->memory->set('value', 'existing');
        $this->memory->push('value', 'new');

        $result = $this->memory->get('value');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testPull(): void
    {
        $this->memory->set('temp', 'value');

        $value = $this->memory->pull('temp');

        $this->assertEquals('value', $value);
        $this->assertFalse($this->memory->has('temp'));
    }

    public function testPullWithDefault(): void
    {
        $value = $this->memory->pull('nonexistent', 'default');

        $this->assertEquals('default', $value);
    }

    public function testGetHistory(): void
    {
        $this->memory->set('key1', 'value1');
        $this->memory->set('key2', 'value2');
        $this->memory->forget('key1');

        $history = $this->memory->getHistory();

        $this->assertCount(3, $history);
        $this->assertEquals('set', $history[0]['action']);
        $this->assertEquals('key1', $history[0]['key']);
        $this->assertEquals('forget', $history[2]['action']);
    }

    public function testHistoryTracksTimestamp(): void
    {
        $this->memory->set('key', 'value');
        $history = $this->memory->getHistory();

        $this->assertArrayHasKey('timestamp', $history[0]);
        $this->assertIsInt($history[0]['timestamp']);
    }

    public function testClearRecordsHistory(): void
    {
        $this->memory->set('key', 'value');
        $this->memory->clear();

        $history = $this->memory->getHistory();

        $this->assertCount(2, $history);
        $this->assertEquals('clear', $history[1]['action']);
    }
}
