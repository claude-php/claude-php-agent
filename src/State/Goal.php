<?php

declare(strict_types=1);

namespace ClaudeAgents\State;

use ClaudeAgents\Contracts\SerializableInterface;

/**
 * Represents a goal with tracking information.
 */
class Goal implements SerializableInterface
{
    private string $id;
    private int $createdAt;

    /**
     * @param string $description Goal description
     * @param GoalStatus|string $status Goal status
     * @param int $progressPercentage Completion percentage (0-100)
     * @param array<string> $completedSubgoals List of completed subgoals
     * @param array<string, mixed> $metadata Additional metadata
     * @param string|null $id Unique identifier (auto-generated if null)
     * @param int|null $createdAt Creation timestamp (auto-set if null)
     */
    public function __construct(
        private readonly string $description,
        GoalStatus|string $status = 'not_started',
        private int $progressPercentage = 0,
        private array $completedSubgoals = [],
        private array $metadata = [],
        ?string $id = null,
        ?int $createdAt = null,
    ) {
        // Validate description
        if (empty(trim($description))) {
            throw new \InvalidArgumentException('Goal description cannot be empty');
        }

        // Convert status to GoalStatus if string
        $this->status = $status instanceof GoalStatus
            ? $status->value
            : (GoalStatus::isValid($status) ? $status : GoalStatus::NOT_STARTED->value);

        // Validate and clamp progress percentage
        $this->progressPercentage = max(0, min(100, $progressPercentage));

        // Generate ID if not provided
        $this->id = $id ?? uniqid('goal_', true);

        // Set creation timestamp
        $this->createdAt = $createdAt ?? time();
    }

    private string $status;

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(GoalStatus|string $status): void
    {
        if ($status instanceof GoalStatus) {
            $this->status = $status->value;
        } elseif (GoalStatus::isValid($status)) {
            $this->status = $status;
        } else {
            throw new \InvalidArgumentException("Invalid goal status: {$status}");
        }
    }

    /**
     * Get status as enum.
     */
    public function getStatusEnum(): GoalStatus
    {
        return GoalStatus::from($this->status);
    }

    /**
     * Get unique identifier.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get creation timestamp.
     */
    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getProgressPercentage(): int
    {
        return $this->progressPercentage;
    }

    public function setProgressPercentage(int $percentage): void
    {
        $this->progressPercentage = max(0, min(100, $percentage));
    }

    /**
     * @return array<string>
     */
    public function getCompletedSubgoals(): array
    {
        return $this->completedSubgoals;
    }

    /**
     * Mark a subgoal as completed.
     */
    public function completeSubgoal(string $subgoal): void
    {
        if (! in_array($subgoal, $this->completedSubgoals, true)) {
            $this->completedSubgoals[] = $subgoal;
        }
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
    }

    /**
     * Get metadata value.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Mark goal as completed.
     */
    public function complete(): void
    {
        $this->status = GoalStatus::COMPLETED->value;
        $this->progressPercentage = 100;
    }

    /**
     * Mark goal as in progress.
     */
    public function start(): void
    {
        $this->status = GoalStatus::IN_PROGRESS->value;
        if ($this->progressPercentage === 0) {
            $this->progressPercentage = 10;
        }
    }

    /**
     * Pause the goal.
     */
    public function pause(): void
    {
        $this->status = GoalStatus::PAUSED->value;
    }

    /**
     * Cancel the goal.
     */
    public function cancel(): void
    {
        $this->status = GoalStatus::CANCELLED->value;
    }

    /**
     * Mark goal as failed.
     */
    public function fail(): void
    {
        $this->status = GoalStatus::FAILED->value;
    }

    /**
     * Check if goal is complete.
     */
    public function isComplete(): bool
    {
        return $this->status === GoalStatus::COMPLETED->value && $this->progressPercentage === 100;
    }

    /**
     * Check if goal is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === GoalStatus::IN_PROGRESS->value;
    }

    /**
     * Check if goal is paused.
     */
    public function isPaused(): bool
    {
        return $this->status === GoalStatus::PAUSED->value;
    }

    /**
     * Check if goal is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === GoalStatus::CANCELLED->value;
    }

    /**
     * Check if goal has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === GoalStatus::FAILED->value;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'status' => $this->status,
            'progress_percentage' => $this->progressPercentage,
            'completed_subgoals' => $this->completedSubgoals,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * Restore state from serialized data.
     *
     * @param array<string, mixed> $data The serialized state
     */
    public function fromArray(array $data): void
    {
        throw new \BadMethodCallException(
            'Goal is immutable after creation. Use Goal::createFromArray() instead.'
        );
    }

    /**
     * Create new Goal from array data.
     *
     * @param array<string, mixed> $data
     */
    public static function createFromArray(array $data): self
    {
        return new self(
            description: $data['description'] ?? 'Unknown goal',
            status: $data['status'] ?? GoalStatus::NOT_STARTED->value,
            progressPercentage: $data['progress_percentage'] ?? 0,
            completedSubgoals: $data['completed_subgoals'] ?? [],
            metadata: $data['metadata'] ?? [],
            id: $data['id'] ?? null,
            createdAt: $data['created_at'] ?? null,
        );
    }

    /**
     * Serialize to JSON string.
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode Goal to JSON');
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
            'Goal is immutable after creation. Use Goal::createFromJson() instead.'
        );
    }

    /**
     * Create new Goal from JSON string.
     */
    public static function createFromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON data for Goal');
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
}
