<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains;

use ClaudeAgents\Chains\Contracts\ChainInputInterface;

/**
 * Represents input to a chain.
 *
 * Provides a type-safe wrapper around input data with validation support.
 */
class ChainInput implements ChainInputInterface
{
    /**
     * @param array<string, mixed> $data The input data
     */
    public function __construct(
        private readonly array $data,
    ) {
    }

    /**
     * Create a new chain input.
     *
     * @param array<string, mixed> $data
     */
    public static function create(array $data): self
    {
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function all(): array
    {
        return $this->data;
    }

    /**
     * Validate against a JSON schema.
     *
     * @param array<string, mixed> $schema JSON schema
     * @throws \ClaudeAgents\Chains\Exceptions\ChainValidationException
     * @return bool True if valid
     */
    public function validate(array $schema): bool
    {
        // Basic required fields validation
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $required) {
                if (! $this->has($required)) {
                    throw new Exceptions\ChainValidationException(
                        "Missing required input field: {$required}"
                    );
                }
            }
        }

        return true;
    }

    /**
     * Get a nested value using dot notation.
     *
     * @param string $path Dot-notation path (e.g., 'user.name')
     * @param mixed $default Default value
     * @return mixed
     */
    public function getDot(string $path, mixed $default = null): mixed
    {
        $parts = explode('.', $path);
        $value = $this->data;

        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }

        return $value;
    }
}
