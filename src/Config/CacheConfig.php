<?php

declare(strict_types=1);

namespace ClaudeAgents\Config;

/**
 * Configuration for caching policies.
 */
class CacheConfig
{
    public const DEFAULT_TTL_SECONDS = 3600;
    public const DEFAULT_MAX_SIZE = 1000;
    public const DEFAULT_STORAGE_BACKEND = 'memory';
    public const DEFAULT_KEY_STRATEGY = 'hash';

    /**
     * @param int $ttlSeconds Time-to-live in seconds (0 = no expiration)
     * @param int $maxSize Maximum number of entries in cache
     * @param string $storageBackend Storage backend: 'memory', 'file', 'redis', 'memcached'
     * @param string $keyStrategy Key generation: 'hash', 'full', 'custom'
     * @param bool $enabled Whether caching is enabled
     * @param bool $autoCleanup Automatically cleanup expired entries
     * @param int $cleanupIntervalSeconds Interval for cleanup in seconds
     * @param string|null $storagePath Path for file-based storage
     * @param array<string, mixed> $backendConfig Backend-specific configuration
     * @param callable|null $keyGenerator Custom key generator function
     */
    public function __construct(
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
        private readonly int $maxSize = self::DEFAULT_MAX_SIZE,
        private readonly string $storageBackend = self::DEFAULT_STORAGE_BACKEND,
        private readonly string $keyStrategy = self::DEFAULT_KEY_STRATEGY,
        private readonly bool $enabled = true,
        private readonly bool $autoCleanup = true,
        private readonly int $cleanupIntervalSeconds = 300,
        private readonly ?string $storagePath = null,
        private readonly array $backendConfig = [],
        private readonly mixed $keyGenerator = null,
    ) {
        $this->validateTtl();
        $this->validateMaxSize();
        $this->validateStorageBackend();
        $this->validateKeyStrategy();
        $this->validateCleanupInterval();
    }

    /**
     * Create from array configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            ttlSeconds: $config['ttl_seconds'] ?? $config['ttl'] ?? self::DEFAULT_TTL_SECONDS,
            maxSize: $config['max_size'] ?? self::DEFAULT_MAX_SIZE,
            storageBackend: $config['storage_backend'] ?? $config['backend'] ?? self::DEFAULT_STORAGE_BACKEND,
            keyStrategy: $config['key_strategy'] ?? self::DEFAULT_KEY_STRATEGY,
            enabled: $config['enabled'] ?? true,
            autoCleanup: $config['auto_cleanup'] ?? true,
            cleanupIntervalSeconds: $config['cleanup_interval_seconds'] ?? $config['cleanup_interval'] ?? 300,
            storagePath: $config['storage_path'] ?? null,
            backendConfig: $config['backend_config'] ?? [],
            keyGenerator: $config['key_generator'] ?? null,
        );
    }

    public function getTtlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    public function hasExpiration(): bool
    {
        return $this->ttlSeconds > 0;
    }

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    public function getStorageBackend(): string
    {
        return $this->storageBackend;
    }

    public function getKeyStrategy(): string
    {
        return $this->keyStrategy;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function shouldAutoCleanup(): bool
    {
        return $this->autoCleanup;
    }

    public function getCleanupIntervalSeconds(): int
    {
        return $this->cleanupIntervalSeconds;
    }

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function getBackendConfig(): array
    {
        return $this->backendConfig;
    }

    public function getKeyGenerator(): ?callable
    {
        return $this->keyGenerator;
    }

    /**
     * Create a new config with modified values.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            ttlSeconds: $overrides['ttl_seconds'] ?? $overrides['ttl'] ?? $this->ttlSeconds,
            maxSize: $overrides['max_size'] ?? $this->maxSize,
            storageBackend: $overrides['storage_backend'] ?? $overrides['backend'] ?? $this->storageBackend,
            keyStrategy: $overrides['key_strategy'] ?? $this->keyStrategy,
            enabled: $overrides['enabled'] ?? $this->enabled,
            autoCleanup: $overrides['auto_cleanup'] ?? $this->autoCleanup,
            cleanupIntervalSeconds: $overrides['cleanup_interval_seconds'] ?? $overrides['cleanup_interval'] ?? $this->cleanupIntervalSeconds,
            storagePath: $overrides['storage_path'] ?? $this->storagePath,
            backendConfig: $overrides['backend_config'] ?? $this->backendConfig,
            keyGenerator: $overrides['key_generator'] ?? $this->keyGenerator,
        );
    }

    /**
     * Check if using memory storage.
     */
    public function isMemoryStorage(): bool
    {
        return $this->storageBackend === 'memory';
    }

    /**
     * Check if using file storage.
     */
    public function isFileStorage(): bool
    {
        return $this->storageBackend === 'file';
    }

    /**
     * Check if using Redis storage.
     */
    public function isRedisStorage(): bool
    {
        return $this->storageBackend === 'redis';
    }

    /**
     * Check if using Memcached storage.
     */
    public function isMemcachedStorage(): bool
    {
        return $this->storageBackend === 'memcached';
    }

    /**
     * Check if using hash key strategy.
     */
    public function isHashKeyStrategy(): bool
    {
        return $this->keyStrategy === 'hash';
    }

    /**
     * Check if using full key strategy.
     */
    public function isFullKeyStrategy(): bool
    {
        return $this->keyStrategy === 'full';
    }

    /**
     * Check if using custom key strategy.
     */
    public function isCustomKeyStrategy(): bool
    {
        return $this->keyStrategy === 'custom';
    }

    private function validateTtl(): void
    {
        if ($this->ttlSeconds < 0) {
            throw new \InvalidArgumentException('TTL must be non-negative (0 = no expiration)');
        }
    }

    private function validateMaxSize(): void
    {
        if ($this->maxSize < 1) {
            throw new \InvalidArgumentException('Max size must be greater than 0');
        }
    }

    private function validateStorageBackend(): void
    {
        $valid = ['memory', 'file', 'redis', 'memcached'];
        if (! in_array($this->storageBackend, $valid, true)) {
            throw new \InvalidArgumentException(
                "Invalid storage backend: {$this->storageBackend}. Must be one of: " . implode(', ', $valid)
            );
        }
    }

    private function validateKeyStrategy(): void
    {
        $valid = ['hash', 'full', 'custom'];
        if (! in_array($this->keyStrategy, $valid, true)) {
            throw new \InvalidArgumentException(
                "Invalid key strategy: {$this->keyStrategy}. Must be one of: " . implode(', ', $valid)
            );
        }
    }

    private function validateCleanupInterval(): void
    {
        if ($this->cleanupIntervalSeconds < 0) {
            throw new \InvalidArgumentException('Cleanup interval must be non-negative');
        }
    }
}
