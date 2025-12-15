<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains\Contracts;

/**
 * Interface for chain input objects.
 */
interface ChainInputInterface
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
     * Get all data.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Validate against a schema.
     *
     * @param array<string, mixed> $schema JSON schema
     * @return bool True if valid
     */
    public function validate(array $schema): bool;
}
