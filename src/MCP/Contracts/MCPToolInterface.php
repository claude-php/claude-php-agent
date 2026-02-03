<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP\Contracts;

/**
 * Interface for MCP tools.
 *
 * MCP tools expose agent functionality through the Model Context Protocol.
 */
interface MCPToolInterface
{
    /**
     * Get the tool name (used for MCP tool registration).
     */
    public function getName(): string;

    /**
     * Get human-readable tool description.
     */
    public function getDescription(): string;

    /**
     * Get the JSON Schema for tool input parameters.
     *
     * @return array<string, mixed>
     */
    public function getInputSchema(): array;

    /**
     * Execute the tool with given parameters.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array;

    /**
     * Get tool category (e.g., 'agent', 'tool', 'visualization', 'config').
     */
    public function getCategory(): string;
}
