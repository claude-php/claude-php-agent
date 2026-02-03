<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Cache;

use ClaudeAgents\Contracts\CacheInterface;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\Settings\SettingsService;

/**
 * Unified caching service with multiple backend support.
 *
 * Wraps existing cache implementations and provides a consistent
 * interface with additional features like namespacing.
 */
class CacheService implements ServiceInterface
{
    private bool $ready = false;
    private CacheInterface $cache;
    private string $namespace = '';

    /**
     * @param SettingsService $settings Settings service for configuration
     * @param CacheInterface|null $cache Optional pre-configured cache instance
     */
    public function __construct(
        private SettingsService $settings,
        ?CacheInterface $cache = null
    ) {
        if ($cache !== null) {
            $this->cache = $cache;
        }
    }

    public function getName(): string
    {
        return 'cache';
    }

    public function initialize(): void
    {
        if ($this->ready) {
            return;
        }

        // If cache not provided, create from settings
        if (! isset($this->cache)) {
            $this->cache = $this->createCacheFromSettings();
        }

        $this->ready = true;
    }

    public function teardown(): void
    {
        if (isset($this->cache)) {
            $this->cache->clear();
        }

        $this->ready = false;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'ready' => $this->ready,
            'methods' => [
                'get' => [
                    'parameters' => ['key' => 'string'],
                    'return' => 'mixed',
                    'description' => 'Get a value from the cache',
                ],
                'set' => [
                    'parameters' => ['key' => 'string', 'value' => 'mixed', 'ttl' => 'int'],
                    'return' => 'void',
                    'description' => 'Set a value in the cache',
                ],
                'delete' => [
                    'parameters' => ['key' => 'string'],
                    'return' => 'void',
                    'description' => 'Delete a value from the cache',
                ],
                'has' => [
                    'parameters' => ['key' => 'string'],
                    'return' => 'bool',
                    'description' => 'Check if a key exists in the cache',
                ],
                'clear' => [
                    'parameters' => ['namespace' => 'string|null'],
                    'return' => 'void',
                    'description' => 'Clear the cache or a specific namespace',
                ],
            ],
        ];
    }

    /**
     * Get a value from the cache.
     *
     * @param string $key Cache key
     * @return mixed Cached value or null if not found
     */
    public function get(string $key): mixed
    {
        return $this->cache->get($this->namespaceKey($key));
    }

    /**
     * Set a value in the cache.
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return void
     */
    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $ttl = $ttl > 0 ? $ttl : $this->settings->get('cache.ttl', 3600);
        $this->cache->set($this->namespaceKey($key), $value, $ttl);
    }

    /**
     * Delete a value from the cache.
     *
     * @param string $key Cache key
     * @return void
     */
    public function delete(string $key): void
    {
        $this->cache->delete($this->namespaceKey($key));
    }

    /**
     * Check if a key exists in the cache.
     *
     * @param string $key Cache key
     * @return bool True if key exists
     */
    public function has(string $key): bool
    {
        return $this->cache->has($this->namespaceKey($key));
    }

    /**
     * Clear the cache or a specific namespace.
     *
     * @param string|null $namespace Optional namespace to clear (null = clear all)
     * @return void
     */
    public function clear(?string $namespace = null): void
    {
        if ($namespace === null) {
            $this->cache->clear();
        } else {
            // For namespaced clearing, we need to track keys
            // This is a limitation of the current cache interface
            $this->cache->clear();
        }
    }

    /**
     * Set the cache namespace.
     *
     * Allows scoping cache keys to prevent collisions.
     *
     * @param string $namespace Namespace prefix
     * @return self
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Get the current namespace.
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Get or set a cached value with a callback.
     *
     * If the key exists, return the cached value.
     * Otherwise, call the callback, cache the result, and return it.
     *
     * @param string $key Cache key
     * @param callable $callback Callback to generate value if not cached
     * @param int $ttl Time to live in seconds
     * @return mixed Cached or generated value
     */
    public function remember(string $key, callable $callback, int $ttl = 0): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get the underlying cache instance.
     *
     * @return CacheInterface
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }

    /**
     * Create a cache instance from settings.
     *
     * @return CacheInterface
     * @throws \RuntimeException If cache driver not supported
     */
    private function createCacheFromSettings(): CacheInterface
    {
        $driver = $this->settings->get('cache.driver', 'array');

        return match ($driver) {
            'array' => new \ClaudeAgents\Cache\ArrayCache(),
            'file' => new \ClaudeAgents\Cache\FileCache(
                $this->settings->get('cache.path', './storage/cache')
            ),
            'redis' => $this->createRedisCache(),
            default => throw new \RuntimeException("Unsupported cache driver: {$driver}"),
        };
    }

    /**
     * Create a Redis cache instance.
     *
     * @return CacheInterface
     * @throws \RuntimeException If Redis extension not available
     */
    private function createRedisCache(): CacheInterface
    {
        if (! class_exists('Redis')) {
            throw new \RuntimeException('Redis extension is not installed');
        }

        return new RedisCache(
            host: $this->settings->get('cache.redis.host', '127.0.0.1'),
            port: $this->settings->get('cache.redis.port', 6379),
            database: $this->settings->get('cache.redis.database', 0),
            password: $this->settings->get('cache.redis.password'),
        );
    }

    /**
     * Add namespace prefix to cache key.
     *
     * @param string $key Original key
     * @return string Namespaced key
     */
    private function namespaceKey(string $key): string
    {
        if ($this->namespace === '') {
            return $key;
        }

        return "{$this->namespace}:{$key}";
    }
}
