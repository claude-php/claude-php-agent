<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Support\TextContentExtractor;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Memory Manager Agent - Manages shared knowledge across agent systems.
 *
 * This agent provides intelligent memory storage, retrieval, and search capabilities
 * using both semantic search (via LLM) and keyword-based search as fallback.
 *
 * Features:
 * - Store text-based memories with metadata
 * - Retrieve memories by ID
 * - Search memories using natural language queries
 * - Keyword-based indexing for fast fallback search
 * - Memory tagging and categorization
 * - Automatic memory expiration
 * - Import/export capabilities
 *
 * @package ClaudeAgents\Agents
 */
class MemoryManagerAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $memory = [];
    private array $index = [];
    private array $tags = [];
    private LoggerInterface $logger;
    private int $maxMemories;
    private ?float $defaultTtl;

    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'memory_manager';
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->maxMemories = $options['max_memories'] ?? 10000;
        $this->defaultTtl = $options['default_ttl'] ?? null; // null = no expiration
    }

    public function run(string $task): AgentResult
    {
        // Parse command: store, retrieve, search, tag, stats, etc.
        $this->logger->info("Memory operation: {$task}");

        try {
            // Store command: "store: content here"
            if (str_starts_with(strtolower($task), 'store:')) {
                $content = substr($task, 6);
                $id = $this->store($content);

                return AgentResult::success(
                    answer: "Stored with ID: {$id}",
                    messages: [],
                    iterations: 1,
                    metadata: ['operation' => 'store', 'id' => $id],
                );
            }

            // Retrieve command: "retrieve: mem_id"
            if (str_starts_with(strtolower($task), 'retrieve:')) {
                $id = trim(substr($task, 9));
                $content = $this->retrieve($id);

                return AgentResult::success(
                    answer: $content ?? 'Not found',
                    messages: [],
                    iterations: 1,
                    metadata: ['operation' => 'retrieve', 'id' => $id, 'found' => $content !== null],
                );
            }

            // Tag search: "tag: tagname"
            if (str_starts_with(strtolower($task), 'tag:')) {
                $tag = trim(substr($task, 4));
                $results = $this->findByTag($tag);

                return AgentResult::success(
                    answer: json_encode($results, JSON_PRETTY_PRINT),
                    messages: [],
                    iterations: 1,
                    metadata: ['operation' => 'findByTag', 'tag' => $tag, 'results_count' => count($results)],
                );
            }

            // Stats command: "stats"
            if (strtolower(trim($task)) === 'stats') {
                $stats = $this->getStats();

                return AgentResult::success(
                    answer: json_encode($stats, JSON_PRETTY_PRINT),
                    messages: [],
                    iterations: 1,
                    metadata: ['operation' => 'stats', 'stats' => $stats],
                );
            }

            // List tags: "tags"
            if (strtolower(trim($task)) === 'tags') {
                $tags = $this->getTags();

                return AgentResult::success(
                    answer: json_encode($tags, JSON_PRETTY_PRINT),
                    messages: [],
                    iterations: 1,
                    metadata: ['operation' => 'tags', 'tags' => $tags],
                );
            }

            // Forget command: "forget: mem_id"
            if (str_starts_with(strtolower($task), 'forget:')) {
                $id = trim(substr($task, 7));
                $success = $this->forget($id);

                return AgentResult::success(
                    answer: $success ? "Memory {$id} forgotten" : "Memory {$id} not found",
                    messages: [],
                    iterations: 1,
                    metadata: ['operation' => 'forget', 'id' => $id, 'success' => $success],
                );
            }

            // Default: Search
            $results = $this->search($task);

            return AgentResult::success(
                answer: json_encode($results, JSON_PRETTY_PRINT),
                messages: [],
                iterations: 1,
                metadata: ['operation' => 'search', 'results_count' => count($results)],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Memory operation failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Store content in memory with optional metadata and tags
     */
    public function store(string $content, array $metadata = [], array $tags = []): string
    {
        // Check memory limit
        if (count($this->memory) >= $this->maxMemories) {
            $this->evictOldest();
        }

        $id = uniqid('mem_', true);
        $timestamp = microtime(true);

        $this->memory[$id] = [
            'id' => $id,
            'content' => $content,
            'metadata' => $metadata,
            'tags' => $tags,
            'timestamp' => $timestamp,
            'expires_at' => $this->defaultTtl ? $timestamp + $this->defaultTtl : null,
            'access_count' => 0,
            'last_accessed' => null,
        ];

        // Update index
        $this->updateIndex($id, $content);

        // Update tags index
        foreach ($tags as $tag) {
            if (! isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            $this->tags[$tag][] = $id;
        }

        $this->logger->info("Stored memory: {$id}", ['tags' => $tags]);

        return $id;
    }

    /**
     * Retrieve memory content by ID
     */
    public function retrieve(string $id): ?string
    {
        // Clean expired memories
        $this->cleanExpired();

        if (isset($this->memory[$id])) {
            // Check if memory is expired
            if ($this->isExpired($this->memory[$id])) {
                $this->forget($id);

                return null;
            }

            // Update access statistics
            $this->memory[$id]['access_count']++;
            $this->memory[$id]['last_accessed'] = microtime(true);

            $this->logger->info("Retrieved memory: {$id}");

            return $this->memory[$id]['content'];
        }

        return null;
    }

    /**
     * Retrieve full memory data by ID (including metadata)
     */
    public function retrieveFull(string $id): ?array
    {
        $this->cleanExpired();

        if (isset($this->memory[$id])) {
            if ($this->isExpired($this->memory[$id])) {
                $this->forget($id);

                return null;
            }

            $this->memory[$id]['access_count']++;
            $this->memory[$id]['last_accessed'] = microtime(true);

            return $this->memory[$id];
        }

        return null;
    }

    public function search(string $query, int $limit = 10): array
    {
        $this->logger->info("Searching memory: {$query}");

        // Use LLM to find relevant memories
        $memoryList = array_map(function ($mem) {
            return "[{$mem['id']}] {$mem['content']}";
        }, array_values($this->memory));

        $memoriesStr = implode("\n", array_slice($memoryList, 0, 100));

        $prompt = <<<PROMPT
            Query: {$query}

            Available memories:
            {$memoriesStr}

            Which memories are most relevant? Return up to {$limit} memory IDs as JSON array.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 512,
                'system' => 'You are a memory retrieval system. Return relevant memory IDs.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $json = TextContentExtractor::extractFromResponse($response);
            $ids = json_decode($json, true);

            if (is_array($ids)) {
                return array_filter(array_map(fn ($id) => $this->memory[$id] ?? null, $ids));
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Search failed: {$e->getMessage()}");
        }

        // Fallback: simple keyword matching
        return $this->keywordSearch($query, $limit);
    }

    /**
     * Remove a memory by ID
     */
    public function forget(string $id): bool
    {
        if (isset($this->memory[$id])) {
            // Remove from tags index
            $tags = $this->memory[$id]['tags'] ?? [];
            foreach ($tags as $tag) {
                if (isset($this->tags[$tag])) {
                    $this->tags[$tag] = array_filter($this->tags[$tag], fn ($mid) => $mid !== $id);
                    if (empty($this->tags[$tag])) {
                        unset($this->tags[$tag]);
                    }
                }
            }

            unset($this->memory[$id]);
            $this->logger->info("Forgot memory: {$id}");

            return true;
        }

        return false;
    }

    /**
     * Find memories by tag
     */
    public function findByTag(string $tag): array
    {
        $this->cleanExpired();

        if (! isset($this->tags[$tag])) {
            return [];
        }

        $results = [];
        foreach ($this->tags[$tag] as $id) {
            if (isset($this->memory[$id]) && ! $this->isExpired($this->memory[$id])) {
                $results[] = $this->memory[$id];
            }
        }

        return $results;
    }

    /**
     * Get all unique tags
     */
    public function getTags(): array
    {
        return array_keys($this->tags);
    }

    /**
     * Export all memories to an array
     */
    public function export(): array
    {
        $this->cleanExpired();

        return array_values($this->memory);
    }

    /**
     * Import memories from an array
     */
    public function import(array $memories): int
    {
        $count = 0;
        foreach ($memories as $mem) {
            if (isset($mem['content'])) {
                $this->store(
                    $mem['content'],
                    $mem['metadata'] ?? [],
                    $mem['tags'] ?? []
                );
                $count++;
            }
        }

        $this->logger->info("Imported {$count} memories");

        return $count;
    }

    /**
     * Clear all memories
     */
    public function clear(): void
    {
        $count = count($this->memory);
        $this->memory = [];
        $this->index = [];
        $this->tags = [];
        $this->logger->info("Cleared {$count} memories");
    }

    /**
     * Get memory statistics
     */
    public function getStats(): array
    {
        $this->cleanExpired();

        $totalSize = 0;
        $totalAccess = 0;
        $withMetadata = 0;
        $withTags = 0;

        foreach ($this->memory as $mem) {
            $totalSize += strlen($mem['content']);
            $totalAccess += $mem['access_count'];
            if (! empty($mem['metadata'])) {
                $withMetadata++;
            }
            if (! empty($mem['tags'])) {
                $withTags++;
            }
        }

        return [
            'total_memories' => count($this->memory),
            'total_size_bytes' => $totalSize,
            'total_access_count' => $totalAccess,
            'unique_tags' => count($this->tags),
            'memories_with_metadata' => $withMetadata,
            'memories_with_tags' => $withTags,
            'index_size' => count($this->index),
        ];
    }

    private function updateIndex(string $id, string $content): void
    {
        $words = str_word_count(strtolower($content), 1);

        foreach ($words as $word) {
            if (strlen($word) > 3) { // Index words longer than 3 chars
                if (! isset($this->index[$word])) {
                    $this->index[$word] = [];
                }
                $this->index[$word][] = $id;
            }
        }
    }

    private function keywordSearch(string $query, int $limit): array
    {
        $queryWords = str_word_count(strtolower($query), 1);
        $scores = [];

        foreach ($queryWords as $word) {
            if (isset($this->index[$word])) {
                foreach ($this->index[$word] as $id) {
                    $scores[$id] = ($scores[$id] ?? 0) + 1;
                }
            }
        }

        arsort($scores);
        $topIds = array_slice(array_keys($scores), 0, $limit);

        return array_filter(array_map(fn ($id) => $this->memory[$id] ?? null, $topIds));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMemoryCount(): int
    {
        $this->cleanExpired();

        return count($this->memory);
    }

    /**
     * Check if a memory is expired
     */
    private function isExpired(array $memory): bool
    {
        if ($memory['expires_at'] === null) {
            return false;
        }

        return microtime(true) > $memory['expires_at'];
    }

    /**
     * Clean up expired memories
     */
    private function cleanExpired(): void
    {
        $expired = [];
        foreach ($this->memory as $id => $mem) {
            if ($this->isExpired($mem)) {
                $expired[] = $id;
            }
        }

        foreach ($expired as $id) {
            $this->forget($id);
        }

        if (! empty($expired)) {
            $this->logger->info('Cleaned up ' . count($expired) . ' expired memories');
        }
    }

    /**
     * Evict the oldest memory to make room for new ones
     */
    private function evictOldest(): void
    {
        if (empty($this->memory)) {
            return;
        }

        // Find oldest memory by timestamp
        $oldest = null;
        $oldestId = null;

        foreach ($this->memory as $id => $mem) {
            if ($oldest === null || $mem['timestamp'] < $oldest) {
                $oldest = $mem['timestamp'];
                $oldestId = $id;
            }
        }

        if ($oldestId !== null) {
            $this->forget($oldestId);
            $this->logger->info("Evicted oldest memory: {$oldestId}");
        }
    }
}
