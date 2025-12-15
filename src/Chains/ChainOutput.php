<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains;

use ClaudeAgents\Chains\Contracts\ChainOutputInterface;

/**
 * Represents output from a chain.
 *
 * Includes both data and metadata about the execution (tokens, latency, etc).
 */
class ChainOutput implements ChainOutputInterface
{
    /**
     * @param array<string, mixed> $data The output data
     * @param array<string, mixed> $metadata Execution metadata
     */
    public function __construct(
        private readonly array $data,
        private readonly array $metadata = [],
    ) {
    }

    /**
     * Create a new chain output.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     */
    public static function create(array $data, array $metadata = []): self
    {
        return new self($data, $metadata);
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

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get a nested value using dot notation.
     *
     * @param string $path Dot-notation path (e.g., 'result.text')
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

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'metadata' => $this->metadata,
        ];
    }
}
