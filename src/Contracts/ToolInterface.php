<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for tool implementations.
 */
interface ToolInterface
{
    /**
     * Get the tool name.
     */
    public function getName(): string;

    /**
     * Get the tool description.
     */
    public function getDescription(): string;

    /**
     * Get the input schema for the tool.
     *
     * @return array<string, mixed>
     */
    public function getInputSchema(): array;

    /**
     * Execute the tool with the given input.
     *
     * @param array<string, mixed> $input The tool input parameters
     * @return ToolResultInterface The result of the tool execution
     */
    public function execute(array $input): ToolResultInterface;

    /**
     * Convert the tool to an API-compatible definition.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array;
}
