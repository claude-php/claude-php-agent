<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for caching implementations.
 */
interface CacheInterface
{
    /**
     * Get a value from cache.
     *
     * @param string $key The cache key
     * @return mixed|null The cached value, or null if not found
     */
    public function get(string $key): mixed;

    /**
     * Set a value in cache.
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     */
    public function set(string $key, mixed $value, int $ttl = 0): void;

    /**
     * Delete a value from cache.
     */
    public function delete(string $key): void;

    /**
     * Check if key exists in cache.
     */
    public function has(string $key): bool;

    /**
     * Clear all cache.
     */
    public function clear(): void;
}
