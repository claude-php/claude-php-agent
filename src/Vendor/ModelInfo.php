<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor;

/**
 * Value object holding metadata about a specific model.
 */
class ModelInfo
{
    /**
     * @param string $id The model identifier (e.g. 'gpt-5.2', 'gemini-2.5-flash')
     * @param string $vendor The vendor name ('openai', 'google', 'anthropic')
     * @param Capability[] $capabilities Capabilities this model supports
     * @param string $description Human-readable description
     * @param string|null $endpoint Override endpoint URL if different from vendor default
     * @param int|null $maxTokens Maximum output tokens
     * @param int|null $contextWindow Context window size in tokens
     * @param bool $isDefault Whether this is the default model for its vendor+capability
     */
    public function __construct(
        public readonly string $id,
        public readonly string $vendor,
        public readonly array $capabilities,
        public readonly string $description,
        public readonly ?string $endpoint = null,
        public readonly ?int $maxTokens = null,
        public readonly ?int $contextWindow = null,
        public readonly bool $isDefault = false,
    ) {
    }

    /**
     * Check if this model supports a given capability.
     */
    public function hasCapability(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'vendor' => $this->vendor,
            'capabilities' => array_map(fn (Capability $c) => $c->value, $this->capabilities),
            'description' => $this->description,
            'endpoint' => $this->endpoint,
            'max_tokens' => $this->maxTokens,
            'context_window' => $this->contextWindow,
            'is_default' => $this->isDefault,
        ];
    }
}
