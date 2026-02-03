<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Cache;

use ClaudeAgents\Contracts\CacheInterface;
use Redis;

/**
 * Redis-based cache implementation.
 *
 * Provides distributed caching support using Redis.
 */
class RedisCache implements CacheInterface
{
    private Redis $redis;

    /**
     * @param string $host Redis host
     * @param int $port Redis port
     * @param int $database Redis database number
     * @param string|null $password Redis password
     * @param int $timeout Connection timeout in seconds
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        int $database = 0,
        ?string $password = null,
        int $timeout = 5
    ) {
        $this->redis = new Redis();

        if (! $this->redis->connect($host, $port, $timeout)) {
            throw new \RuntimeException("Failed to connect to Redis at {$host}:{$port}");
        }

        if ($password !== null && $password !== '') {
            if (! $this->redis->auth($password)) {
                throw new \RuntimeException('Redis authentication failed');
            }
        }

        if (! $this->redis->select($database)) {
            throw new \RuntimeException("Failed to select Redis database: {$database}");
        }
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            return null;
        }

        return unserialize($value);
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $serialized = serialize($value);

        if ($ttl > 0) {
            $this->redis->setex($key, $ttl, $serialized);
        } else {
            $this->redis->set($key, $serialized);
        }
    }

    public function delete(string $key): void
    {
        $this->redis->del($key);
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function clear(): void
    {
        $this->redis->flushDB();
    }

    /**
     * Get the underlying Redis instance.
     *
     * @return Redis
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }

    /**
     * Disconnect from Redis.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->redis->close();
    }

    /**
     * Close Redis connection on destruct.
     */
    public function __destruct()
    {
        try {
            $this->redis->close();
        } catch (\Throwable $e) {
            // Ignore errors during cleanup
        }
    }
}
