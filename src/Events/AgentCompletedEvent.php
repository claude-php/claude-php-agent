<?php

declare(strict_types=1);

namespace ClaudeAgents\Events;

use ClaudeAgents\AgentResult;

/**
 * Event fired when an agent completes execution.
 */
class AgentCompletedEvent extends AgentEvent
{
    public function __construct(
        string $agentName,
        private readonly AgentResult $result,
        private readonly float $duration,
        float $timestamp = 0.0,
    ) {
        parent::__construct($agentName, $timestamp);
    }

    public function getResult(): AgentResult
    {
        return $this->result;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getEventType(): string
    {
        return 'agent.completed';
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'duration' => $this->duration,
            'success' => $this->result->isSuccess(),
            'iterations' => $this->result->getIterations(),
        ]);
    }
}
