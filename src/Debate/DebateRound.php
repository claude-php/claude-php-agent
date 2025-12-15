<?php

declare(strict_types=1);

namespace ClaudeAgents\Debate;

/**
 * Represents a single round of debate with all participants' statements.
 */
class DebateRound
{
    /**
     * @param int $roundNumber The round number (1-indexed)
     * @param array<string, string> $statements Map of agent name to statement
     * @param int $timestamp When the round occurred
     */
    public function __construct(
        private readonly int $roundNumber,
        private readonly array $statements,
        private readonly int $timestamp = 0,
    ) {
    }

    public function getRoundNumber(): int
    {
        return $this->roundNumber;
    }

    /**
     * @return array<string, string>
     */
    public function getStatements(): array
    {
        return $this->statements;
    }

    /**
     * Get a specific agent's statement.
     */
    public function getStatement(string $agentName): ?string
    {
        return $this->statements[$agentName] ?? null;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Get all participants in this round.
     *
     * @return array<string>
     */
    public function getParticipants(): array
    {
        return array_keys($this->statements);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'round_number' => $this->roundNumber,
            'statements' => $this->statements,
            'participants' => $this->getParticipants(),
            'timestamp' => $this->timestamp,
        ];
    }
}
