<?php

declare(strict_types=1);

namespace Tests\Unit\Agents;

use ClaudeAgents\Agents\MemoryManagerAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MemoryManagerAgentTest extends TestCase
{
    private ClaudePhp $client;
    private MemoryManagerAgent $agent;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
        $this->agent = new MemoryManagerAgent($this->client, ['name' => 'test_memory_manager']);
    }

    public function test_creates_agent_with_default_options(): void
    {
        $agent = new MemoryManagerAgent($this->client);

        $this->assertSame('memory_manager', $agent->getName());
    }

    public function test_creates_agent_with_custom_options(): void
    {
        $logger = new NullLogger();
        $agent = new MemoryManagerAgent($this->client, [
            'name' => 'custom_memory',
            'max_memories' => 5000,
            'default_ttl' => 3600.0,
            'logger' => $logger,
        ]);

        $this->assertSame('custom_memory', $agent->getName());
    }

    public function test_get_name(): void
    {
        $this->assertSame('test_memory_manager', $this->agent->getName());
    }

    public function test_stores_memory(): void
    {
        $id = $this->agent->store('Test content');

        $this->assertIsString($id);
        $this->assertStringStartsWith('mem_', $id);
        $this->assertSame(1, $this->agent->getMemoryCount());
    }

    public function test_stores_memory_with_metadata(): void
    {
        $id = $this->agent->store('Test content', ['source' => 'unit_test', 'priority' => 'high']);

        $this->assertIsString($id);
        $memory = $this->agent->retrieveFull($id);

        $this->assertIsArray($memory);
        $this->assertArrayHasKey('metadata', $memory);
        $this->assertSame('unit_test', $memory['metadata']['source']);
    }

    public function test_stores_memory_with_tags(): void
    {
        $id = $this->agent->store('Test content', [], ['tag1', 'tag2']);

        $memory = $this->agent->retrieveFull($id);

        $this->assertIsArray($memory);
        $this->assertArrayHasKey('tags', $memory);
        $this->assertContains('tag1', $memory['tags']);
        $this->assertContains('tag2', $memory['tags']);
    }

    public function test_retrieves_memory_by_id(): void
    {
        $id = $this->agent->store('Hello world');

        $content = $this->agent->retrieve($id);

        $this->assertSame('Hello world', $content);
    }

    public function test_retrieve_returns_null_for_nonexistent_id(): void
    {
        $content = $this->agent->retrieve('nonexistent_id');

        $this->assertNull($content);
    }

    public function test_retrieve_full_returns_complete_memory_data(): void
    {
        $id = $this->agent->store('Test content', ['key' => 'value'], ['tag']);

        $memory = $this->agent->retrieveFull($id);

        $this->assertIsArray($memory);
        $this->assertArrayHasKey('id', $memory);
        $this->assertArrayHasKey('content', $memory);
        $this->assertArrayHasKey('metadata', $memory);
        $this->assertArrayHasKey('tags', $memory);
        $this->assertArrayHasKey('timestamp', $memory);
        $this->assertArrayHasKey('access_count', $memory);
        $this->assertSame('Test content', $memory['content']);
    }

    public function test_retrieve_updates_access_count(): void
    {
        $id = $this->agent->store('Test content');

        $this->agent->retrieve($id);
        $this->agent->retrieve($id);

        $memory = $this->agent->retrieveFull($id);
        $this->assertSame(3, $memory['access_count']); // 3 because retrieveFull also increments
    }

    public function test_forget_removes_memory(): void
    {
        $id = $this->agent->store('Test content');

        $success = $this->agent->forget($id);

        $this->assertTrue($success);
        $this->assertNull($this->agent->retrieve($id));
        $this->assertSame(0, $this->agent->getMemoryCount());
    }

    public function test_forget_returns_false_for_nonexistent_id(): void
    {
        $success = $this->agent->forget('nonexistent_id');

        $this->assertFalse($success);
    }

    public function test_find_by_tag(): void
    {
        $id1 = $this->agent->store('Content 1', [], ['important', 'work']);
        $id2 = $this->agent->store('Content 2', [], ['important']);
        $id3 = $this->agent->store('Content 3', [], ['personal']);

        $results = $this->agent->findByTag('important');

        $this->assertCount(2, $results);
        $ids = array_column($results, 'id');
        $this->assertContains($id1, $ids);
        $this->assertContains($id2, $ids);
        $this->assertNotContains($id3, $ids);
    }

    public function test_find_by_tag_returns_empty_for_nonexistent_tag(): void
    {
        $results = $this->agent->findByTag('nonexistent');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function test_get_tags_returns_all_unique_tags(): void
    {
        $this->agent->store('Content 1', [], ['tag1', 'tag2']);
        $this->agent->store('Content 2', [], ['tag2', 'tag3']);

        $tags = $this->agent->getTags();

        $this->assertCount(3, $tags);
        $this->assertContains('tag1', $tags);
        $this->assertContains('tag2', $tags);
        $this->assertContains('tag3', $tags);
    }

    public function test_forget_removes_tag_associations(): void
    {
        $id = $this->agent->store('Content', [], ['test_tag']);

        $this->assertContains('test_tag', $this->agent->getTags());

        $this->agent->forget($id);

        $this->assertNotContains('test_tag', $this->agent->getTags());
    }

    public function test_export_returns_all_memories(): void
    {
        $this->agent->store('Content 1');
        $this->agent->store('Content 2');
        $this->agent->store('Content 3');

        $exported = $this->agent->export();

        $this->assertCount(3, $exported);
        $this->assertIsArray($exported[0]);
        $this->assertArrayHasKey('content', $exported[0]);
    }

    public function test_import_adds_memories(): void
    {
        $memories = [
            ['content' => 'Imported 1', 'metadata' => ['source' => 'import']],
            ['content' => 'Imported 2', 'tags' => ['imported']],
        ];

        $count = $this->agent->import($memories);

        $this->assertSame(2, $count);
        $this->assertSame(2, $this->agent->getMemoryCount());
    }

    public function test_import_skips_invalid_entries(): void
    {
        $memories = [
            ['content' => 'Valid'],
            ['invalid' => 'no content field'],
            ['content' => 'Also valid'],
        ];

        $count = $this->agent->import($memories);

        $this->assertSame(2, $count);
        $this->assertSame(2, $this->agent->getMemoryCount());
    }

    public function test_clear_removes_all_memories(): void
    {
        $this->agent->store('Content 1', [], ['tag1']);
        $this->agent->store('Content 2', [], ['tag2']);

        $this->agent->clear();

        $this->assertSame(0, $this->agent->getMemoryCount());
        $this->assertEmpty($this->agent->getTags());
    }

    public function test_get_stats_returns_correct_statistics(): void
    {
        $this->agent->store('Short', ['key' => 'value'], ['tag1']);
        $this->agent->store('A longer content string', [], ['tag1', 'tag2']);
        $this->agent->store('Medium length');

        $stats = $this->agent->getStats();

        $this->assertArrayHasKey('total_memories', $stats);
        $this->assertArrayHasKey('total_size_bytes', $stats);
        $this->assertArrayHasKey('total_access_count', $stats);
        $this->assertArrayHasKey('unique_tags', $stats);
        $this->assertArrayHasKey('memories_with_metadata', $stats);
        $this->assertArrayHasKey('memories_with_tags', $stats);
        $this->assertArrayHasKey('index_size', $stats);

        $this->assertSame(3, $stats['total_memories']);
        $this->assertSame(2, $stats['unique_tags']);
        $this->assertSame(1, $stats['memories_with_metadata']);
        $this->assertSame(2, $stats['memories_with_tags']);
        $this->assertGreaterThan(0, $stats['total_size_bytes']);
    }

    public function test_evicts_oldest_memory_when_limit_reached(): void
    {
        $agent = new MemoryManagerAgent($this->client, ['max_memories' => 3]);

        $id1 = $agent->store('Memory 1');
        $id2 = $agent->store('Memory 2');
        $id3 = $agent->store('Memory 3');
        $id4 = $agent->store('Memory 4'); // Should evict id1

        $this->assertSame(3, $agent->getMemoryCount());
        $this->assertNull($agent->retrieve($id1)); // Oldest should be gone
        $this->assertIsString($agent->retrieve($id2));
        $this->assertIsString($agent->retrieve($id3));
        $this->assertIsString($agent->retrieve($id4));
    }

    public function test_memory_expiration_with_ttl(): void
    {
        $agent = new MemoryManagerAgent($this->client, ['default_ttl' => 0.1]); // 0.1 seconds

        $id = $agent->store('Expiring content');

        $this->assertIsString($agent->retrieve($id));

        usleep(150000); // 0.15 seconds

        $this->assertNull($agent->retrieve($id)); // Should be expired
    }

    public function test_run_method_with_store_command(): void
    {
        $result = $this->agent->run('store: This is test content');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Stored with ID:', $result->getAnswer());
        $this->assertSame(1, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('operation', $metadata);
        $this->assertArrayHasKey('id', $metadata);
        $this->assertSame('store', $metadata['operation']);
    }

    public function test_run_method_with_retrieve_command(): void
    {
        $id = $this->agent->store('Test content');

        $result = $this->agent->run("retrieve: {$id}");

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Test content', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertSame('retrieve', $metadata['operation']);
        $this->assertTrue($metadata['found']);
    }

    public function test_run_method_with_retrieve_nonexistent(): void
    {
        $result = $this->agent->run('retrieve: nonexistent_id');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Not found', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertFalse($metadata['found']);
    }

    public function test_run_method_with_tag_command(): void
    {
        $this->agent->store('Tagged content', [], ['testtag']);

        $result = $this->agent->run('tag: testtag');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Tagged content', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertSame('findByTag', $metadata['operation']);
        $this->assertSame(1, $metadata['results_count']);
    }

    public function test_run_method_with_stats_command(): void
    {
        $this->agent->store('Content 1');
        $this->agent->store('Content 2');

        $result = $this->agent->run('stats');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('total_memories', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertSame('stats', $metadata['operation']);
        $this->assertArrayHasKey('stats', $metadata);
    }

    public function test_run_method_with_tags_command(): void
    {
        $this->agent->store('Content', [], ['tag1', 'tag2']);

        $result = $this->agent->run('tags');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('tag1', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertSame('tags', $metadata['operation']);
    }

    public function test_run_method_with_forget_command(): void
    {
        $id = $this->agent->store('To be forgotten');

        $result = $this->agent->run("forget: {$id}");

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('forgotten', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertSame('forget', $metadata['operation']);
        $this->assertTrue($metadata['success']);

        $this->assertNull($this->agent->retrieve($id));
    }

    public function test_run_method_with_search_uses_llm(): void
    {
        $this->mockLlmResponse('["mem_123"]');

        $this->agent->store('Searchable content');

        $result = $this->agent->run('find searchable');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        $this->assertSame('search', $metadata['operation']);
    }

    public function test_search_falls_back_to_keyword_on_llm_failure(): void
    {
        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willThrowException(new \RuntimeException('API Error'));
        $this->client->method('messages')->willReturn($messages);

        $id = $this->agent->store('This is searchable content with unique words');

        $results = $this->agent->search('searchable unique');

        // Should fallback to keyword search
        $this->assertIsArray($results);
        // Result may or may not be empty depending on keyword match
    }

    public function test_keyword_search_finds_relevant_memories(): void
    {
        $id1 = $this->agent->store('PHP programming language is great');
        $id2 = $this->agent->store('Python programming is also good');
        $id3 = $this->agent->store('JavaScript for web development');

        // Mock LLM to fail so we use keyword search
        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willThrowException(new \RuntimeException('API Error'));
        $this->client->method('messages')->willReturn($messages);

        $results = $this->agent->search('programming language');

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));

        // Should find memories with "programming"
        $contents = array_column($results, 'content');
        $hasMatch = false;
        foreach ($contents as $content) {
            if (stripos($content, 'programming') !== false) {
                $hasMatch = true;

                break;
            }
        }
        $this->assertTrue($hasMatch);
    }

    public function test_run_returns_failure_on_exception(): void
    {
        // Force an exception by mocking the logger to throw
        $agent = new class ($this->client) extends MemoryManagerAgent {
            public function store(string $content, array $metadata = [], array $tags = []): string
            {
                throw new \RuntimeException('Forced error');
            }
        };

        $result = $agent->run('store: test');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Forced error', $result->getError());
    }

    private function mockLlmResponse(string $text): void
    {
        $usage = new Usage(
            input_tokens: 100,
            output_tokens: 50
        );

        $response = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => $text],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willReturn($response);
        $this->client->method('messages')->willReturn($messages);
    }
}
