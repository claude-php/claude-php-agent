<?php

declare(strict_types=1);

namespace Tests\Integration\Agents;

use ClaudeAgents\Agents\MemoryManagerAgent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for MemoryManagerAgent
 *
 * These tests verify the agent works with real API calls.
 * Set ANTHROPIC_API_KEY environment variable to run these tests.
 */
class MemoryManagerAgentIntegrationTest extends TestCase
{
    private ?ClaudePhp $client = null;
    private ?MemoryManagerAgent $agent = null;

    protected function setUp(): void
    {
        $apiKey = getenv('ANTHROPIC_API_KEY');

        if (! $apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set. Skipping integration tests.');
        }

        $this->client = new ClaudePhp(apiKey: $apiKey);
        $this->agent = new MemoryManagerAgent($this->client, [
            'name' => 'integration_test_agent',
        ]);
    }

    public function test_stores_and_retrieves_memory(): void
    {
        $content = 'Integration test content: The quick brown fox jumps over the lazy dog';

        $id = $this->agent->store($content);

        $this->assertIsString($id);
        $this->assertStringStartsWith('mem_', $id);

        $retrieved = $this->agent->retrieve($id);

        $this->assertSame($content, $retrieved);
    }

    public function test_search_with_llm_finds_relevant_memories(): void
    {
        // Store multiple memories
        $this->agent->store('PHP is a server-side scripting language used for web development');
        $this->agent->store('Python is a high-level programming language known for readability');
        $this->agent->store('JavaScript is primarily used for client-side web programming');
        $this->agent->store('The weather today is sunny and warm');

        // Search for programming-related content
        $results = $this->agent->search('programming languages', 3);

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));

        // Results should contain programming-related content
        $allContent = implode(' ', array_column($results, 'content'));
        $this->assertTrue(
            stripos($allContent, 'programming') !== false ||
            stripos($allContent, 'language') !== false ||
            stripos($allContent, 'PHP') !== false
        );
    }

    public function test_stores_memory_with_tags_and_finds_by_tag(): void
    {
        $id1 = $this->agent->store(
            'Claude AI is an advanced language model',
            ['source' => 'documentation'],
            ['AI', 'Claude']
        );

        $id2 = $this->agent->store(
            'Machine learning powers modern AI systems',
            ['source' => 'article'],
            ['AI', 'ML']
        );

        $aiMemories = $this->agent->findByTag('AI');

        $this->assertCount(2, $aiMemories);

        $claudeMemories = $this->agent->findByTag('Claude');
        $this->assertCount(1, $claudeMemories);
        $this->assertSame($id1, $claudeMemories[0]['id']);
    }

    public function test_run_method_with_various_commands(): void
    {
        // Test store command
        $result = $this->agent->run('store: Important information about API integration');
        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $memId = $metadata['id'];

        // Test retrieve command
        $result = $this->agent->run("retrieve: {$memId}");
        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('API integration', $result->getAnswer());

        // Test stats command
        $result = $this->agent->run('stats');
        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('total_memories', $result->getAnswer());

        // Test forget command
        $result = $this->agent->run("forget: {$memId}");
        $this->assertTrue($result->isSuccess());

        // Verify it's forgotten
        $result = $this->agent->run("retrieve: {$memId}");
        $this->assertSame('Not found', $result->getAnswer());
    }

    public function test_import_and_export_workflow(): void
    {
        // Store some memories
        $this->agent->store('Memory 1', ['type' => 'test'], ['export-test']);
        $this->agent->store('Memory 2', ['type' => 'test'], ['export-test']);
        $this->agent->store('Memory 3', ['type' => 'test'], ['export-test']);

        // Export
        $exported = $this->agent->export();
        $this->assertCount(3, $exported);

        // Create new agent and import
        $newAgent = new MemoryManagerAgent($this->client, ['name' => 'import_test']);
        $count = $newAgent->import($exported);

        $this->assertSame(3, $count);
        $this->assertSame(3, $newAgent->getMemoryCount());

        // Verify imported memories have tags
        $tagged = $newAgent->findByTag('export-test');
        $this->assertCount(3, $tagged);
    }

    public function test_memory_statistics_accuracy(): void
    {
        // Clear and start fresh
        $this->agent->clear();

        $this->agent->store('Short', ['key' => 'val'], ['tag1']);
        $this->agent->store('Medium length text', [], ['tag1', 'tag2']);
        $this->agent->store('Another piece of content');

        $stats = $this->agent->getStats();

        $this->assertSame(3, $stats['total_memories']);
        $this->assertSame(2, $stats['unique_tags']);
        $this->assertSame(1, $stats['memories_with_metadata']);
        $this->assertSame(2, $stats['memories_with_tags']);
        $this->assertGreaterThan(0, $stats['total_size_bytes']);
        $this->assertGreaterThan(0, $stats['index_size']);
    }

    public function test_concurrent_operations(): void
    {
        $ids = [];

        // Store multiple memories
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->agent->store("Memory number {$i}", ['index' => $i], ["batch{$i}"]);
        }

        // Retrieve all
        foreach ($ids as $i => $id) {
            $content = $this->agent->retrieve($id);
            $this->assertStringContainsString("Memory number {$i}", $content);
        }

        // Verify count
        $this->assertSame(5, $this->agent->getMemoryCount());

        // Forget some
        $this->agent->forget($ids[0]);
        $this->agent->forget($ids[2]);

        $this->assertSame(3, $this->agent->getMemoryCount());
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if ($this->agent) {
            $this->agent->clear();
        }
    }
}
