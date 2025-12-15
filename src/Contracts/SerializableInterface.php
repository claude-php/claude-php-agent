<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for agent state serialization and persistence.
 *
 * Enables saving and restoring agent state for:
 * - Long-running tasks that span multiple requests
 * - Checkpointing and recovery
 * - State sharing across distributed systems
 */
interface SerializableInterface
{
    /**
     * Serialize the current state to an array.
     *
     * @return array<string, mixed> Serialized state data
     */
    public function toArray(): array;

    /**
     * Restore state from serialized data.
     *
     * @param array<string, mixed> $data The serialized state
     */
    public function fromArray(array $data): void;

    /**
     * Serialize to JSON string.
     */
    public function toJson(): string;

    /**
     * Restore from JSON string.
     *
     * @param string $json The JSON serialized state
     */
    public function fromJson(string $json): void;

    /**
     * Get a unique identifier for this instance.
     *
     * Used for tracking and retrieving persisted state.
     */
    public function getStateId(): string;

    /**
     * Get the version of the serialization format.
     *
     * Useful for handling migrations when state structure changes.
     */
    public function getVersion(): string;
}
