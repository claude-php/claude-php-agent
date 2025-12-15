<?php

declare(strict_types=1);

namespace ClaudeAgents\Events;

/**
 * Base class for all agent events.
 */
abstract class AgentEvent
{
    private readonly float $timestamp;

    public function __construct(
        private readonly string $agentName,
        float $timestamp = 0.0,
    ) {
        $this->timestamp = $timestamp === 0.0 ? microtime(true) : $timestamp;
    }

    public function getAgentName(): string
    {
        return $this->agentName;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * Get event type name.
     */
    abstract public function getEventType(): string;

    /**
     * Convert event to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->getEventType(),
            'agent_name' => $this->agentName,
            'timestamp' => $this->timestamp,
        ];
    }
}
