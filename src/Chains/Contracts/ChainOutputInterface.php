<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains\Contracts;

/**
 * Interface for chain output objects.
 */
interface ChainOutputInterface
{
    /**
     * Get a value by key.
     *
     * @param string $key The key to retrieve
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The value
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if a key exists.
     *
     * @param string $key The key to check
     * @return bool True if the key exists
     */
    public function has(string $key): bool;

    /**
     * Get all output data.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Get metadata about the output.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * Get a metadata value.
     *
     * @param string $key The metadata key
     * @param mixed $default Default value
     * @return mixed The metadata value
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed;
}
