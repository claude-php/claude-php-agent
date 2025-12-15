<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for memory/state management.
 */
interface MemoryInterface
{
    /**
     * Store a value in memory.
     *
     * @param string $key The key to store under
     * @param mixed $value The value to store
     */
    public function set(string $key, mixed $value): void;

    /**
     * Retrieve a value from memory.
     *
     * @param string $key The key to retrieve
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The stored value or default
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if a key exists in memory.
     */
    public function has(string $key): bool;

    /**
     * Remove a key from memory.
     */
    public function forget(string $key): void;

    /**
     * Get all stored data.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Clear all memory.
     */
    public function clear(): void;
}
