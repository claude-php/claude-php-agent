<?php

declare(strict_types=1);

namespace ClaudeAgents\Events;

/**
 * Event fired when an agent fails execution.
 */
class AgentFailedEvent extends AgentEvent
{
    public function __construct(
        string $agentName,
        private readonly \Throwable $error,
        private readonly float $duration,
        float $timestamp = 0.0,
    ) {
        parent::__construct($agentName, $timestamp);
    }

    public function getError(): \Throwable
    {
        return $this->error;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getEventType(): string
    {
        return 'agent.failed';
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'duration' => $this->duration,
            'error' => $this->error->getMessage(),
            'error_type' => get_class($this->error),
        ]);
    }
}
