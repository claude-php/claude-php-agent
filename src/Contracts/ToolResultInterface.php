<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for tool execution results.
 */
interface ToolResultInterface
{
    /**
     * Get the result content.
     */
    public function getContent(): string;

    /**
     * Check if the result is an error.
     */
    public function isError(): bool;

    /**
     * Convert to API-compatible format.
     *
     * @param string $toolUseId The tool_use_id from the API
     * @return array<string, mixed>
     */
    public function toApiFormat(string $toolUseId): array;
}
