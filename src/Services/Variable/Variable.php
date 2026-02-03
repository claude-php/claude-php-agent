<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Variable;

/**
 * Represents a variable with metadata.
 */
class Variable
{
    /**
     * @param string $key Variable key
     * @param mixed $value Variable value (encrypted for credentials)
     * @param VariableType $type Variable type
     * @param int $updatedAt Unix timestamp of last update
     */
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly VariableType $type,
        public readonly int $updatedAt
    ) {
    }

    /**
     * Create a variable from an array.
     *
     * @param array<string, mixed> $data Variable data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'] ?? '',
            value: $data['value'] ?? null,
            type: VariableType::from($data['type'] ?? 'generic'),
            updatedAt: $data['updated_at'] ?? time()
        );
    }

    /**
     * Convert the variable to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type->value,
            'updated_at' => $this->updatedAt,
        ];
    }
}
