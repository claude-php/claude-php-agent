<?php

declare(strict_types=1);

namespace ClaudeAgents\Tools;

use ClaudeAgents\Contracts\ToolInterface;

/**
 * Registry for managing multiple tools.
 */
class ToolRegistry
{
    /**
     * @var array<string, ToolInterface>
     */
    private array $tools = [];

    /**
     * Register a tool.
     */
    public function register(ToolInterface $tool): self
    {
        $this->tools[$tool->getName()] = $tool;

        return $this;
    }

    /**
     * Register multiple tools.
     *
     * @param array<ToolInterface> $tools
     */
    public function registerMany(array $tools): self
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }

        return $this;
    }

    /**
     * Get a tool by name.
     */
    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Check if a tool exists.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Remove a tool.
     */
    public function remove(string $name): self
    {
        unset($this->tools[$name]);

        return $this;
    }

    /**
     * Get all registered tools.
     *
     * @return array<ToolInterface>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * Get all tool names.
     *
     * @return array<string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Get tool definitions for API calls.
     *
     * @return array<array<string, mixed>>
     */
    public function toDefinitions(): array
    {
        return array_map(
            fn (ToolInterface $tool) => $tool->toDefinition(),
            array_values($this->tools)
        );
    }

    /**
     * Execute a tool by name.
     *
     * @param string $name Tool name
     * @param array<string, mixed> $input Tool input
     * @return ToolResult The execution result
     */
    public function execute(string $name, array $input): ToolResult
    {
        $tool = $this->get($name);

        if ($tool === null) {
            return ToolResult::error("Unknown tool: {$name}");
        }

        $result = $tool->execute($input);

        if ($result instanceof ToolResult) {
            return $result;
        }

        // Convert ToolResultInterface to ToolResult if needed
        return new ToolResult(
            $result->getContent(),
            $result->isError()
        );
    }

    /**
     * Get the count of registered tools.
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Clear all registered tools.
     */
    public function clear(): self
    {
        $this->tools = [];

        return $this;
    }
}
