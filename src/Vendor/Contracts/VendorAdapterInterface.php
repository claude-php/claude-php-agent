<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Contracts;

use ClaudeAgents\Vendor\Capability;

/**
 * Interface for vendor LLM adapters.
 *
 * Each vendor adapter encapsulates the HTTP calls to a specific
 * AI provider's API (OpenAI, Google Gemini, etc.).
 */
interface VendorAdapterInterface
{
    /**
     * Get the vendor name (e.g. 'openai', 'google').
     */
    public function getName(): string;

    /**
     * Send a chat/completion request to the vendor.
     *
     * @param string $prompt The user prompt
     * @param array<string, mixed> $options Model, max_tokens, temperature, etc.
     * @return string The model's text response
     */
    public function chat(string $prompt, array $options = []): string;

    /**
     * Check if this adapter is available (API key is set).
     */
    public function isAvailable(): bool;

    /**
     * Get the capabilities this adapter supports.
     *
     * @return Capability[]
     */
    public function getSupportedCapabilities(): array;

    /**
     * Check if this adapter supports a specific capability.
     */
    public function supportsCapability(Capability $capability): bool;

    /**
     * Execute a specific capability with the given parameters.
     *
     * @param Capability $capability The capability to execute
     * @param array<string, mixed> $params Parameters for the capability
     * @return mixed The result (type depends on capability)
     *
     * @throws \InvalidArgumentException If the capability is not supported
     */
    public function executeCapability(Capability $capability, array $params): mixed;
}
