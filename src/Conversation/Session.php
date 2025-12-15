<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation;

/**
 * Represents a conversation session with state and history.
 */
class Session
{
    private string $id;
    private array $turns = [];
    private array $state = [];
    private float $createdAt;
    private ?float $lastActivity = null;

    public function __construct(string $id = null)
    {
        $this->id = $id ?? uniqid('session_', true);
        $this->createdAt = microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function addTurn(Turn $turn): void
    {
        $this->turns[] = $turn;
        $this->lastActivity = microtime(true);
    }

    /**
     * @return array<Turn>
     */
    public function getTurns(): array
    {
        return $this->turns;
    }

    public function setState(array $state): void
    {
        $this->state = $state;
    }

    public function getState(): array
    {
        return $this->state;
    }

    public function updateState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    public function getCreatedAt(): float
    {
        return $this->createdAt;
    }

    public function getLastActivity(): ?float
    {
        return $this->lastActivity;
    }

    public function getTurnCount(): int
    {
        return count($this->turns);
    }
}
