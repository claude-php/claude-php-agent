<?php

declare(strict_types=1);

namespace ClaudeAgents\Config;

use ClaudeAgents\Exceptions\ConfigurationException;

/**
 * Configuration for streaming behavior.
 */
class StreamConfig
{
    public const DEFAULT_BUFFER_SIZE = 1024;
    public const DEFAULT_FLUSH_INTERVAL_MS = 100;
    public const DEFAULT_CHUNK_SIZE = 512;

    /**
     * @param int $bufferSize Size of the stream buffer in bytes
     * @param int $flushIntervalMs Interval to flush buffer in milliseconds
     * @param int $chunkSize Size of chunks to process at once
     * @param bool $autoFlush Automatically flush on newlines
     * @param bool $includeMetadata Include metadata events in stream
     * @param bool $includeUsage Include token usage in stream
     * @param bool $formatJson Format output as JSON events
     * @param string $eventPrefix Prefix for event types (e.g., 'data: ')
     * @param array<string> $handlers List of handler class names to use
     * @param array<string, mixed> $handlerConfig Configuration for handlers
     */
    public function __construct(
        private readonly int $bufferSize = self::DEFAULT_BUFFER_SIZE,
        private readonly int $flushIntervalMs = self::DEFAULT_FLUSH_INTERVAL_MS,
        private readonly int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        private readonly bool $autoFlush = true,
        private readonly bool $includeMetadata = true,
        private readonly bool $includeUsage = true,
        private readonly bool $formatJson = false,
        private readonly string $eventPrefix = '',
        private readonly array $handlers = [],
        private readonly array $handlerConfig = [],
    ) {
        $this->validateBufferSize();
        $this->validateFlushInterval();
        $this->validateChunkSize();
    }

    /**
     * Create from array configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            bufferSize: $config['buffer_size'] ?? self::DEFAULT_BUFFER_SIZE,
            flushIntervalMs: $config['flush_interval_ms'] ?? $config['flush_interval'] ?? self::DEFAULT_FLUSH_INTERVAL_MS,
            chunkSize: $config['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE,
            autoFlush: $config['auto_flush'] ?? true,
            includeMetadata: $config['include_metadata'] ?? true,
            includeUsage: $config['include_usage'] ?? true,
            formatJson: $config['format_json'] ?? false,
            eventPrefix: $config['event_prefix'] ?? '',
            handlers: $config['handlers'] ?? [],
            handlerConfig: $config['handler_config'] ?? [],
        );
    }

    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    public function getFlushIntervalMs(): int
    {
        return $this->flushIntervalMs;
    }

    public function getFlushIntervalSeconds(): float
    {
        return $this->flushIntervalMs / 1000.0;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function shouldAutoFlush(): bool
    {
        return $this->autoFlush;
    }

    public function shouldIncludeMetadata(): bool
    {
        return $this->includeMetadata;
    }

    public function shouldIncludeUsage(): bool
    {
        return $this->includeUsage;
    }

    public function shouldFormatJson(): bool
    {
        return $this->formatJson;
    }

    public function getEventPrefix(): string
    {
        return $this->eventPrefix;
    }

    public function getHandlers(): array
    {
        return $this->handlers;
    }

    public function getHandlerConfig(): array
    {
        return $this->handlerConfig;
    }

    /**
     * Get configuration for a specific handler.
     *
     * @param string $handlerName
     * @return array<string, mixed>
     */
    public function getHandlerConfigFor(string $handlerName): array
    {
        return $this->handlerConfig[$handlerName] ?? [];
    }

    /**
     * Create a new config with modified values.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            bufferSize: $overrides['buffer_size'] ?? $this->bufferSize,
            flushIntervalMs: $overrides['flush_interval_ms'] ?? $overrides['flush_interval'] ?? $this->flushIntervalMs,
            chunkSize: $overrides['chunk_size'] ?? $this->chunkSize,
            autoFlush: $overrides['auto_flush'] ?? $this->autoFlush,
            includeMetadata: $overrides['include_metadata'] ?? $this->includeMetadata,
            includeUsage: $overrides['include_usage'] ?? $this->includeUsage,
            formatJson: $overrides['format_json'] ?? $this->formatJson,
            eventPrefix: $overrides['event_prefix'] ?? $this->eventPrefix,
            handlers: $overrides['handlers'] ?? $this->handlers,
            handlerConfig: $overrides['handler_config'] ?? $this->handlerConfig,
        );
    }

    /**
     * Add a handler to the configuration.
     *
     * @param string $handlerClass
     * @param array<string, mixed> $config
     */
    public function withHandler(string $handlerClass, array $config = []): self
    {
        $handlers = $this->handlers;
        $handlers[] = $handlerClass;

        $handlerConfig = $this->handlerConfig;
        $handlerConfig[$handlerClass] = $config;

        return $this->with([
            'handlers' => $handlers,
            'handler_config' => $handlerConfig,
        ]);
    }

    private function validateBufferSize(): void
    {
        if ($this->bufferSize < 1) {
            throw new ConfigurationException('Buffer size must be greater than 0', 'buffer_size', $this->bufferSize);
        }
        if ($this->bufferSize > 1048576) { // 1MB
            throw new ConfigurationException('Buffer size is too large (max: 1MB)', 'buffer_size', $this->bufferSize);
        }
    }

    private function validateFlushInterval(): void
    {
        if ($this->flushIntervalMs < 0) {
            throw new ConfigurationException('Flush interval must be non-negative', 'flush_interval_ms', $this->flushIntervalMs);
        }
    }

    private function validateChunkSize(): void
    {
        if ($this->chunkSize < 1) {
            throw new ConfigurationException('Chunk size must be greater than 0', 'chunk_size', $this->chunkSize);
        }
    }
}
