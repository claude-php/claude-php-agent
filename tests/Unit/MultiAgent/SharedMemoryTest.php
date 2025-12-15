<?php

declare(strict_types=1);

namespace Tests\Unit\MultiAgent;

use ClaudeAgents\MultiAgent\SharedMemory;
use PHPUnit\Framework\TestCase;

class SharedMemoryTest extends TestCase
{
    private SharedMemory $memory;

    protected function setUp(): void
    {
        $this->memory = new SharedMemory();
    }

    public function test_writes_and_reads_data(): void
    {
        $this->memory->write('key1', 'value1', 'agent1');

        $value = $this->memory->read('key1', 'agent2');

        $this->assertEquals('value1', $value);
    }

    public function test_reads_default_value_for_missing_key(): void
    {
        $value = $this->memory->read('nonexistent', 'agent1', 'default');

        $this->assertEquals('default', $value);
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        $this->memory->write('key1', 'value1', 'agent1');

        $this->assertTrue($this->memory->has('key1'));
    }

    public function test_has_returns_false_for_missing_key(): void
    {
        $this->assertFalse($this->memory->has('nonexistent'));
    }

    public function test_deletes_key(): void
    {
        $this->memory->write('key1', 'value1', 'agent1');
        $deleted = $this->memory->delete('key1', 'agent1');

        $this->assertTrue($deleted);
        $this->assertFalse($this->memory->has('key1'));
    }

    public function test_delete_returns_false_for_missing_key(): void
    {
        $deleted = $this->memory->delete('nonexistent', 'agent1');

        $this->assertFalse($deleted);
    }

    public function test_gets_all_keys(): void
    {
        $this->memory->write('key1', 'value1', 'agent1');
        $this->memory->write('key2', 'value2', 'agent1');
        $this->memory->write('key3', 'value3', 'agent2');

        $keys = $this->memory->keys();

        $this->assertCount(3, $keys);
        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertContains('key3', $keys);
    }

    public function test_gets_all_data(): void
    {
        $this->memory->write('key1', 'value1', 'agent1');
        $this->memory->write('key2', 'value2', 'agent2');

        $data = $this->memory->getAll();

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
        ], $data);
    }

    public function test_stores_metadata(): void
    {
        $this->memory->write('key1', 'value1', 'agent1', ['custom' => 'meta']);

        $metadata = $this->memory->getMetadata('key1');

        $this->assertIsArray($metadata);
        $this->assertEquals('agent1', $metadata['written_by']);
        $this->assertArrayHasKey('written_at', $metadata);
        $this->assertEquals(1, $metadata['version']);
        $this->assertEquals(['custom' => 'meta'], $metadata['metadata']);
    }

    public function test_increments_version_on_overwrite(): void
    {
        $this->memory->write('key1', 'value1', 'agent1');
        $this->memory->write('key1', 'value2', 'agent2');

        $metadata = $this->memory->getMetadata('key1');

        $this->assertEquals(2, $metadata['version']);
        $this->assertEquals('agent2', $metadata['written_by']);
    }

    public function test_clears_all_data(): void
    {
        $this->memory->write('key1', 'value1', 'agent1');
        $this->memory->write('key2', 'value2', 'agent2');

        $this->memory->clear();

        $this->assertEmpty($this->memory->keys());
        $this->assertEmpty($this->memory->getAll());
    }

    public function test_tracks_access_log(): void
    {
        $memory = new SharedMemory(['track_access' => true]);

        $memory->write('key1', 'value1', 'agent1');
        $memory->read('key1', 'agent2');
        $memory->delete('key1', 'agent3');

        $log = $memory->getAccessLog();

        $this->assertCount(3, $log);
        $this->assertEquals('write', $log[0]['operation']);
        $this->assertEquals('agent1', $log[0]['agent_id']);
        $this->assertEquals('read', $log[1]['operation']);
        $this->assertEquals('agent2', $log[1]['agent_id']);
        $this->assertEquals('delete', $log[2]['operation']);
        $this->assertEquals('agent3', $log[2]['agent_id']);
    }

    public function test_clears_access_log(): void
    {
        $this->memory->write('key1', 'value1', 'agent1');

        $this->memory->clearAccessLog();

        $this->assertEmpty($this->memory->getAccessLog());
    }

    public function test_gets_statistics(): void
    {
        $this->memory->write('key1', 'value1', 'agent1');
        $this->memory->write('key2', 'value2', 'agent2');
        $this->memory->read('key1', 'agent3');
        $this->memory->delete('key2', 'agent1');

        $stats = $this->memory->getStatistics();

        $this->assertEquals(1, $stats['total_keys']); // key2 was deleted
        $this->assertEquals(4, $stats['total_operations']);
        $this->assertEquals(1, $stats['reads']);
        $this->assertEquals(2, $stats['writes']);
        $this->assertEquals(1, $stats['deletes']);
        $this->assertEquals(3, $stats['unique_agents']);
    }

    public function test_compare_and_swap_succeeds(): void
    {
        $this->memory->write('counter', 0, 'agent1');

        $result = $this->memory->compareAndSwap('counter', 0, 1, 'agent2');

        $this->assertTrue($result);
        $this->assertEquals(1, $this->memory->read('counter', 'agent2'));
    }

    public function test_compare_and_swap_fails_on_mismatch(): void
    {
        $this->memory->write('counter', 5, 'agent1');

        $result = $this->memory->compareAndSwap('counter', 0, 10, 'agent2');

        $this->assertFalse($result);
        $this->assertEquals(5, $this->memory->read('counter', 'agent2'));
    }

    public function test_compare_and_swap_fails_for_missing_key(): void
    {
        $result = $this->memory->compareAndSwap('nonexistent', 0, 1, 'agent1');

        $this->assertFalse($result);
    }

    public function test_appends_to_array(): void
    {
        $this->memory->write('list', ['a', 'b'], 'agent1');

        $this->memory->append('list', 'c', 'agent2');

        $this->assertEquals(['a', 'b', 'c'], $this->memory->read('list', 'agent1'));
    }

    public function test_append_creates_array_if_missing(): void
    {
        $this->memory->append('newlist', 'first', 'agent1');

        $this->assertEquals(['first'], $this->memory->read('newlist', 'agent1'));
    }

    public function test_append_converts_scalar_to_array(): void
    {
        $this->memory->write('value', 'scalar', 'agent1');

        $this->memory->append('value', 'appended', 'agent2');

        $this->assertEquals(['scalar', 'appended'], $this->memory->read('value', 'agent1'));
    }

    public function test_increments_numeric_value(): void
    {
        $this->memory->write('counter', 10, 'agent1');

        $new = $this->memory->increment('counter', 'agent2', 5);

        $this->assertEquals(15, $new);
        $this->assertEquals(15, $this->memory->read('counter', 'agent1'));
    }

    public function test_increment_creates_key_if_missing(): void
    {
        $new = $this->memory->increment('newcounter', 'agent1', 3);

        $this->assertEquals(3, $new);
    }

    public function test_exports_state(): void
    {
        $this->memory->write('key1', 'value1', 'agent1');
        $this->memory->read('key1', 'agent2');

        $export = $this->memory->export();

        $this->assertArrayHasKey('data', $export);
        $this->assertArrayHasKey('metadata', $export);
        $this->assertArrayHasKey('access_log', $export);
        $this->assertArrayHasKey('statistics', $export);
        $this->assertEquals(['key1' => 'value1'], $export['data']);
    }

    public function test_imports_state(): void
    {
        $state = [
            'data' => ['key1' => 'value1', 'key2' => 'value2'],
            'metadata' => ['key1' => ['written_by' => 'agent1']],
            'access_log' => [['operation' => 'write', 'key' => 'key1', 'agent_id' => 'agent1']],
        ];

        $this->memory->import($state);

        $this->assertEquals('value1', $this->memory->read('key1', 'agent1'));
        $this->assertEquals('value2', $this->memory->read('key2', 'agent1'));
        $this->assertCount(2, $this->memory->keys());
    }
}
