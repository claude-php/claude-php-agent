<?php

declare(strict_types=1);

namespace ClaudeAgents\Streaming;

/**
 * Represents a single streaming event.
 */
class StreamEvent
{
    public const TYPE_CONTENT_BLOCK_START = 'content_block_start';
    public const TYPE_CONTENT_BLOCK_DELTA = 'content_block_delta';
    public const TYPE_CONTENT_BLOCK_STOP = 'content_block_stop';
    public const TYPE_MESSAGE_START = 'message_start';
    public const TYPE_MESSAGE_DELTA = 'message_delta';
    public const TYPE_MESSAGE_STOP = 'message_stop';
    public const TYPE_TOOL_USE = 'tool_use';
    public const TYPE_TEXT = 'text';
    public const TYPE_ERROR = 'error';
    public const TYPE_PING = 'ping';
    public const TYPE_METADATA = 'metadata';

    /**
     * @param string $type Event type
     * @param string $text Text content (if applicable)
     * @param array<string, mixed> $data Additional event data
     * @param int $timestamp Event timestamp
     */
    public function __construct(
        private readonly string $type,
        private readonly string $text = '',
        private readonly array $data = [],
        private readonly int $timestamp = 0,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Check if this is a text content event.
     */
    public function isText(): bool
    {
        return $this->type === self::TYPE_TEXT || $this->type === self::TYPE_CONTENT_BLOCK_DELTA;
    }

    /**
     * Check if this is a tool use event.
     */
    public function isToolUse(): bool
    {
        return $this->type === self::TYPE_TOOL_USE;
    }

    /**
     * Check if this is an error event.
     */
    public function isError(): bool
    {
        return $this->type === self::TYPE_ERROR;
    }

    /**
     * Check if this is a metadata event.
     */
    public function isMetadata(): bool
    {
        return $this->type === self::TYPE_METADATA;
    }

    /**
     * Check if this is a ping/heartbeat event.
     */
    public function isPing(): bool
    {
        return $this->type === self::TYPE_PING;
    }

    /**
     * Get tool use data if applicable.
     *
     * @return array<string, mixed>|null
     */
    public function getToolUse(): ?array
    {
        return $this->isToolUse() ? $this->data : null;
    }

    /**
     * Create a text event.
     */
    public static function text(string $text): self
    {
        return new self(self::TYPE_TEXT, $text, [], time());
    }

    /**
     * Create a tool use event.
     *
     * @param array<string, mixed> $toolData
     */
    public static function toolUse(array $toolData): self
    {
        return new self(self::TYPE_TOOL_USE, '', $toolData, time());
    }

    /**
     * Create a delta event.
     */
    public static function delta(string $text): self
    {
        return new self(self::TYPE_CONTENT_BLOCK_DELTA, $text, [], time());
    }

    /**
     * Create an error event.
     *
     * @param array<string, mixed> $errorData
     */
    public static function error(string $message, array $errorData = []): self
    {
        return new self(self::TYPE_ERROR, $message, $errorData, time());
    }

    /**
     * Create a metadata event.
     *
     * @param array<string, mixed> $metadata
     */
    public static function metadata(array $metadata): self
    {
        return new self(self::TYPE_METADATA, '', $metadata, time());
    }

    /**
     * Create a ping/heartbeat event.
     */
    public static function ping(): self
    {
        return new self(self::TYPE_PING, '', [], time());
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'text' => $this->text,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
        ];
    }
}
