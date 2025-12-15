<?php

declare(strict_types=1);

namespace ClaudeAgents\Progress;

/**
 * Structured progress event emitted while an agent is running.
 *
 * Consumers can use this to continuously surface updates to users (UI, CLI, logs)
 * without tightly coupling to internal loop strategy implementations.
 */
class AgentUpdate
{
    /**
     * @param string $type Event type (e.g. 'agent.start', 'llm.iteration', 'tool.executed', 'llm.stream')
     * @param string $agent Agent name/identifier
     * @param array<string, mixed> $data Event payload
     * @param float $timestamp Unix timestamp (microtime)
     */
    public function __construct(
        private readonly string $type,
        private readonly string $agent,
        private readonly array $data = [],
        private readonly float $timestamp = 0.0,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAgent(): string
    {
        return $this->agent;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'agent' => $this->agent,
            'timestamp' => $this->timestamp,
            'data' => $this->data,
        ];
    }
}

