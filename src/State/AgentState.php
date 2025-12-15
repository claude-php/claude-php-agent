<?php

declare(strict_types=1);

namespace ClaudeAgents\State;

use ClaudeAgents\Contracts\SerializableInterface;

/**
 * Persistent state for an autonomous agent.
 */
class AgentState implements SerializableInterface
{
    private string $id;
    private StateConfig $config;
    private int $sessionStartedAt;

    /**
     * @param int $sessionNumber Current session number
     * @param Goal $goal The agent's goal
     * @param array<array<string, mixed>> $conversationHistory Conversation messages
     * @param array<array<string, mixed>> $actionHistory History of actions taken
     * @param array<string, mixed> $metadata Custom metadata
     * @param int $createdAt Creation timestamp
     * @param int $updatedAt Last update timestamp
     * @param string|null $id Unique identifier (auto-generated if null)
     * @param StateConfig|null $config State configuration
     */
    public function __construct(
        private int $sessionNumber,
        private Goal $goal,
        private array $conversationHistory = [],
        private array $actionHistory = [],
        private array $metadata = [],
        private int $createdAt = 0,
        private int $updatedAt = 0,
        ?string $id = null,
        ?StateConfig $config = null,
    ) {
        // Validate session number
        if ($sessionNumber < 1) {
            throw new \InvalidArgumentException('Session number must be at least 1');
        }

        $now = time();

        if ($createdAt === 0) {
            $this->createdAt = $now;
        }
        if ($updatedAt === 0) {
            $this->updatedAt = $now;
        }

        // Validate timestamps
        if ($this->createdAt < 0 || $this->updatedAt < 0) {
            throw new \InvalidArgumentException('Timestamps must be non-negative');
        }
        if ($this->updatedAt < $this->createdAt) {
            throw new \InvalidArgumentException('Updated timestamp cannot be before created timestamp');
        }

        // Generate ID if not provided
        $this->id = $id ?? uniqid('state_', true);

        // Set config
        $this->config = $config ?? StateConfig::default();

        // Track session start time
        $this->sessionStartedAt = $now;

        // Apply history limits
        $this->applyHistoryLimits();
    }

    public function getSessionNumber(): int
    {
        return $this->sessionNumber;
    }

    public function incrementSession(): void
    {
        $this->sessionNumber++;
        $this->sessionStartedAt = time();
        $this->updatedAt = time();
    }

    /**
     * Get unique identifier.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get state configuration.
     */
    public function getConfig(): StateConfig
    {
        return $this->config;
    }

    public function getGoal(): Goal
    {
        return $this->goal;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getConversationHistory(): array
    {
        return $this->conversationHistory;
    }

    /**
     * Add a conversation message.
     *
     * @param array<string, mixed> $message
     */
    public function addMessage(array $message): void
    {
        $this->conversationHistory[] = array_merge(
            $message,
            ['timestamp' => time()]
        );
        $this->updatedAt = time();
        $this->applyHistoryLimits();
    }

    /**
     * Get recent conversation messages.
     *
     * @param int $count Number of recent messages to retrieve
     * @return array<array<string, mixed>>
     */
    public function getRecentMessages(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        return array_slice($this->conversationHistory, -$count);
    }

    /**
     * Clear conversation history.
     */
    public function clearConversationHistory(): void
    {
        $this->conversationHistory = [];
        $this->updatedAt = time();
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getActionHistory(): array
    {
        return $this->actionHistory;
    }

    /**
     * Record an action taken.
     *
     * @param array<string, mixed> $action
     */
    public function recordAction(array $action): void
    {
        $this->actionHistory[] = array_merge(
            $action,
            ['timestamp' => time()]
        );
        $this->updatedAt = time();
        $this->applyHistoryLimits();
    }

    /**
     * Get recent actions.
     *
     * @param int $count Number of recent actions to retrieve
     * @return array<array<string, mixed>>
     */
    public function getRecentActions(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        return array_slice($this->actionHistory, -$count);
    }

    /**
     * Clear action history.
     */
    public function clearActionHistory(): void
    {
        $this->actionHistory = [];
        $this->updatedAt = time();
    }

    /**
     * Clear all history (conversation and actions).
     */
    public function clearAllHistory(): void
    {
        $this->conversationHistory = [];
        $this->actionHistory = [];
        $this->updatedAt = time();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set metadata value.
     */
    public function setMetadataValue(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
        $this->updatedAt = time();
    }

    /**
     * Get metadata value.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    /**
     * Get total lifetime duration in seconds.
     */
    public function getLifetimeDuration(): int
    {
        return time() - $this->createdAt;
    }

    /**
     * Get current session duration in seconds.
     */
    public function getSessionDuration(): int
    {
        return time() - $this->sessionStartedAt;
    }

    /**
     * Get idle time in seconds (time since last update).
     */
    public function getIdleTime(): int
    {
        return time() - $this->updatedAt;
    }

    /**
     * Get approximate state size in bytes.
     */
    public function getStateSize(): int
    {
        return strlen(json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    /**
     * Get statistics about the state.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'session_number' => $this->sessionNumber,
            'conversation_count' => count($this->conversationHistory),
            'action_count' => count($this->actionHistory),
            'goal_progress' => $this->goal->getProgressPercentage(),
            'goal_status' => $this->goal->getStatus(),
            'lifetime_duration' => $this->getLifetimeDuration(),
            'session_duration' => $this->getSessionDuration(),
            'idle_time' => $this->getIdleTime(),
            'state_size_bytes' => $this->getStateSize(),
        ];
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'version' => $this->getVersion(),
            'session_number' => $this->sessionNumber,
            'goal' => $this->goal->toArray(),
            'conversation_history' => $this->conversationHistory,
            'action_history' => $this->actionHistory,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'session_started_at' => $this->sessionStartedAt,
            'config' => [
                'max_conversation_history' => $this->config->maxConversationHistory,
                'max_action_history' => $this->config->maxActionHistory,
                'compress_history' => $this->config->compressHistory,
                'atomic_writes' => $this->config->atomicWrites,
                'backup_retention' => $this->config->backupRetention,
                'version' => $this->config->version,
            ],
        ];
    }

    /**
     * Restore state from serialized data (instance method from SerializableInterface).
     *
     * @param array<string, mixed> $data The serialized state
     */
    public function fromArray(array $data): void
    {
        throw new \BadMethodCallException(
            'AgentState is immutable after creation. Use AgentState::createFromArray() instead.'
        );
    }

    /**
     * Create from array (FIXED: includes description parameter).
     *
     * @param array<string, mixed> $data
     */
    public static function createFromArray(array $data): self
    {
        // FIX: Properly reconstruct Goal with description
        $goal = Goal::createFromArray($data['goal'] ?? []);

        // Reconstruct config if present
        $config = null;
        if (isset($data['config'])) {
            $config = new StateConfig(
                maxConversationHistory: $data['config']['max_conversation_history'] ?? 1000,
                maxActionHistory: $data['config']['max_action_history'] ?? 1000,
                compressHistory: $data['config']['compress_history'] ?? false,
                atomicWrites: $data['config']['atomic_writes'] ?? true,
                backupRetention: $data['config']['backup_retention'] ?? 5,
                version: $data['config']['version'] ?? 1,
            );
        }

        $state = new self(
            sessionNumber: $data['session_number'] ?? 1,
            goal: $goal,
            conversationHistory: $data['conversation_history'] ?? [],
            actionHistory: $data['action_history'] ?? [],
            metadata: $data['metadata'] ?? [],
            createdAt: $data['created_at'] ?? time(),
            updatedAt: $data['updated_at'] ?? time(),
            id: $data['id'] ?? null,
            config: $config,
        );

        // Restore session start time if available
        if (isset($data['session_started_at'])) {
            $state->sessionStartedAt = $data['session_started_at'];
        }

        return $state;
    }

    /**
     * Serialize to JSON string.
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode AgentState to JSON');
        }

        return $json;
    }

    /**
     * Restore from JSON string.
     *
     * @param string $json The JSON serialized state
     */
    public function fromJson(string $json): void
    {
        throw new \BadMethodCallException(
            'AgentState is immutable after creation. Use AgentState::createFromJson() instead.'
        );
    }

    /**
     * Create new AgentState from JSON string.
     */
    public static function createFromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON data for AgentState');
        }

        return self::createFromArray($data);
    }

    /**
     * Get a unique identifier for this instance.
     */
    public function getStateId(): string
    {
        return $this->id;
    }

    /**
     * Get the version of the serialization format.
     */
    public function getVersion(): string
    {
        return '1.0';
    }

    /**
     * Apply history limits based on configuration.
     */
    private function applyHistoryLimits(): void
    {
        // Limit conversation history
        if ($this->config->maxConversationHistory > 0
            && count($this->conversationHistory) > $this->config->maxConversationHistory) {
            $this->conversationHistory = array_slice(
                $this->conversationHistory,
                -$this->config->maxConversationHistory
            );
        }

        // Limit action history
        if ($this->config->maxActionHistory > 0
            && count($this->actionHistory) > $this->config->maxActionHistory) {
            $this->actionHistory = array_slice(
                $this->actionHistory,
                -$this->config->maxActionHistory
            );
        }
    }
}
