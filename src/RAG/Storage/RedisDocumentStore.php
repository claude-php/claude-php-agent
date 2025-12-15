<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Storage;

use ClaudeAgents\Contracts\DocumentStoreInterface;
use ClaudeAgents\RAG\Document;

/**
 * Redis-based persistent document store.
 *
 * Requires the Redis PHP extension or predis/predis package.
 */
class RedisDocumentStore implements DocumentStoreInterface
{
    private const KEY_PREFIX = 'rag:doc:';
    private const SET_KEY = 'rag:docs';

    /**
     * @param \Redis|\Predis\Client $redis Redis client instance
     */
    public function __construct(
        private readonly object $redis,
    ) {
        if (! ($redis instanceof \Redis) && ! class_exists('\\Predis\\Client')) {
            throw new \RuntimeException('Redis extension or predis/predis package required');
        }
    }

    public function add(string $id, string $title, string $content, array $metadata = []): void
    {
        $document = new Document($id, $title, $content, $metadata);
        $key = self::KEY_PREFIX . $id;

        $data = json_encode($document->toArray());
        if ($data === false) {
            throw new \RuntimeException('Failed to encode document');
        }

        // Store document
        $this->set($key, $data);

        // Add to set of document IDs
        $this->sadd(self::SET_KEY, $id);
    }

    public function get(string $id): ?array
    {
        $key = self::KEY_PREFIX . $id;
        $data = $this->getValue($key);

        if ($data === null || $data === false) {
            return null;
        }

        $decoded = json_decode($data, true);
        if ($decoded === null) {
            throw new \RuntimeException('Failed to decode document: ' . json_last_error_msg());
        }

        return $decoded;
    }

    public function has(string $id): bool
    {
        $key = self::KEY_PREFIX . $id;

        return $this->exists($key);
    }

    public function remove(string $id): void
    {
        $key = self::KEY_PREFIX . $id;
        $this->del($key);
        $this->srem(self::SET_KEY, $id);
    }

    public function all(): array
    {
        $ids = $this->smembers(self::SET_KEY);
        $documents = [];

        foreach ($ids as $id) {
            $doc = $this->get($id);
            if ($doc !== null) {
                $documents[$id] = $doc;
            }
        }

        return $documents;
    }

    public function count(): int
    {
        return $this->scard(self::SET_KEY);
    }

    public function clear(): void
    {
        $ids = $this->smembers(self::SET_KEY);

        foreach ($ids as $id) {
            $this->remove($id);
        }
    }

    /**
     * Set a value (abstraction for Redis/Predis).
     */
    private function set(string $key, string $value): void
    {
        if ($this->redis instanceof \Redis) {
            $this->redis->set($key, $value);
        } else {
            $this->redis->set($key, $value);
        }
    }

    /**
     * Get a value (abstraction for Redis/Predis).
     */
    private function getValue(string $key): mixed
    {
        if ($this->redis instanceof \Redis) {
            return $this->redis->get($key);
        }

        return $this->redis->get($key);

    }

    /**
     * Check if key exists (abstraction for Redis/Predis).
     */
    private function exists(string $key): bool
    {
        if ($this->redis instanceof \Redis) {
            return $this->redis->exists($key) > 0;
        }

        return (bool) $this->redis->exists($key);

    }

    /**
     * Delete a key (abstraction for Redis/Predis).
     */
    private function del(string $key): void
    {
        if ($this->redis instanceof \Redis) {
            $this->redis->del($key);
        } else {
            $this->redis->del([$key]);
        }
    }

    /**
     * Add to set (abstraction for Redis/Predis).
     */
    private function sadd(string $key, string $member): void
    {
        if ($this->redis instanceof \Redis) {
            $this->redis->sAdd($key, $member);
        } else {
            $this->redis->sadd($key, $member);
        }
    }

    /**
     * Remove from set (abstraction for Redis/Predis).
     */
    private function srem(string $key, string $member): void
    {
        if ($this->redis instanceof \Redis) {
            $this->redis->sRem($key, $member);
        } else {
            $this->redis->srem($key, $member);
        }
    }

    /**
     * Get all set members (abstraction for Redis/Predis).
     *
     * @return array<string>
     */
    private function smembers(string $key): array
    {
        if ($this->redis instanceof \Redis) {
            return $this->redis->sMembers($key);
        }

        return $this->redis->smembers($key);

    }

    /**
     * Get set cardinality (abstraction for Redis/Predis).
     */
    private function scard(string $key): int
    {
        if ($this->redis instanceof \Redis) {
            return $this->redis->sCard($key);
        }

        return $this->redis->scard($key);

    }
}
