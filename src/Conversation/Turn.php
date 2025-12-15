<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation;

/**
 * Represents a single turn in a dialog (user input + agent response).
 */
class Turn
{
    private string $id;
    private string $userInput;
    private string $agentResponse;
    private array $metadata;
    private float $timestamp;

    public function __construct(string $userInput, string $agentResponse, array $metadata = [])
    {
        $this->id = uniqid('turn_', true);
        $this->userInput = $userInput;
        $this->agentResponse = $agentResponse;
        $this->metadata = $metadata;
        $this->timestamp = microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserInput(): string
    {
        return $this->userInput;
    }

    public function getAgentResponse(): string
    {
        return $this->agentResponse;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_input' => $this->userInput,
            'agent_response' => $this->agentResponse,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
        ];
    }
}
