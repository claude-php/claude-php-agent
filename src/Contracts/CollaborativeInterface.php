<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\MultiAgent\Message;

/**
 * Interface for collaborative agents that can communicate with other agents.
 */
interface CollaborativeInterface extends AgentInterface
{
    /**
     * Send a message to another agent.
     */
    public function sendMessage(Message $message): void;

    /**
     * Receive a message from another agent.
     */
    public function receiveMessage(Message $message): void;

    /**
     * Get the agent's unique identifier.
     */
    public function getAgentId(): string;

    /**
     * Get agent capabilities/skills.
     *
     * @return array<string>
     */
    public function getCapabilities(): array;
}
