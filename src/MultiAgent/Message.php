<?php

declare(strict_types=1);

namespace ClaudeAgents\MultiAgent;

/**
 * Represents a message between agents in a multi-agent system.
 */
class Message
{
    private string $id;
    private string $from;
    private string $to;
    private string $content;
    private string $type;
    private array $metadata;
    private float $timestamp;

    /**
     * @param string $from Sender agent ID
     * @param string $to Receiver agent ID (or 'broadcast' for all)
     * @param string $content Message content
     * @param string $type Message type (e.g., 'request', 'response', 'notification')
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        string $from,
        string $to,
        string $content,
        string $type = 'message',
        array $metadata = []
    ) {
        $this->id = uniqid('msg_', true);
        $this->from = $from;
        $this->to = $to;
        $this->content = $content;
        $this->type = $type;
        $this->metadata = $metadata;
        $this->timestamp = microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function isBroadcast(): bool
    {
        return $this->to === 'broadcast';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'from' => $this->from,
            'to' => $this->to,
            'content' => $this->content,
            'type' => $this->type,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
        ];
    }
}
