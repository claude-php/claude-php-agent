<?php

declare(strict_types=1);

namespace ClaudeAgents\Events;

/**
 * Event fired when an agent starts execution.
 */
class AgentStartedEvent extends AgentEvent
{
    public function __construct(
        string $agentName,
        private readonly string $task,
        float $timestamp = 0.0,
    ) {
        parent::__construct($agentName, $timestamp);
    }

    public function getTask(): string
    {
        return $this->task;
    }

    public function getEventType(): string
    {
        return 'agent.started';
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'task' => substr($this->task, 0, 100),
        ]);
    }
}
