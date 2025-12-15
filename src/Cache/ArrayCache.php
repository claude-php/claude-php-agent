<?php

declare(strict_types=1);

namespace ClaudeAgents\Cache;

use ClaudeAgents\Contracts\CacheInterface;

/**
 * In-memory array-based cache.
 */
class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expires: int}>
     */
    private array $cache = [];

    public function get(string $key): mixed
    {
        if (! isset($this->cache[$key])) {
            return null;
        }

        $item = $this->cache[$key];

        // Check expiration
        if ($item['expires'] > 0 && time() > $item['expires']) {
            unset($this->cache[$key]);

            return null;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $expires = $ttl > 0 ? time() + $ttl : 0;

        $this->cache[$key] = [
            'value' => $value,
            'expires' => $expires,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->cache[$key]);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function clear(): void
    {
        $this->cache = [];
    }

    /**
     * Get cache size.
     */
    public function size(): int
    {
        return count($this->cache);
    }
}
