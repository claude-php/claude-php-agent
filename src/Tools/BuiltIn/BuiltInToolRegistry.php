<?php

declare(strict_types=1);

namespace ClaudeAgents\Tools\BuiltIn;

use ClaudeAgents\Tools\ToolRegistry;

/**
 * Registry for built-in tools with easy configuration.
 */
class BuiltInToolRegistry extends ToolRegistry
{
    /**
     * Create a registry with all built-in tools.
     *
     * @param array<string, array<string, mixed>|false> $config Configuration for individual tools
     *                                                          Keys: 'calculator', 'datetime', 'http', 'filesystem', 'database', 'regex'
     *                                                          Values: Configuration arrays for each tool, or false to exclude
     * @return self
     */
    public static function createWithAll(array $config = []): self
    {
        $registry = new self();

        if (! isset($config['calculator']) || $config['calculator'] !== false) {
            $registry->register(
                CalculatorTool::create($config['calculator'] ?? [])
            );
        }

        if (! isset($config['datetime']) || $config['datetime'] !== false) {
            $registry->register(
                DateTimeTool::create($config['datetime'] ?? [])
            );
        }

        if (! isset($config['http']) || $config['http'] !== false) {
            $registry->register(
                HTTPTool::create($config['http'] ?? [])
            );
        }

        if (! isset($config['filesystem']) || $config['filesystem'] !== false) {
            $registry->register(
                FileSystemTool::create($config['filesystem'] ?? [])
            );
        }

        if (isset($config['database']) && $config['database'] !== false) {
            // Database tool requires connection, so only add if explicitly configured
            $registry->register(
                DatabaseTool::create($config['database'])
            );
        }

        if (! isset($config['regex']) || $config['regex'] !== false) {
            $registry->register(
                RegexTool::create($config['regex'] ?? [])
            );
        }

        return $registry;
    }

    /**
     * Create a registry with only calculator tool.
     *
     * @param array<string, mixed> $config Configuration for calculator
     * @return self
     */
    public static function withCalculator(array $config = []): self
    {
        $registry = new self();
        $registry->register(CalculatorTool::create($config));

        return $registry;
    }

    /**
     * Create a registry with only datetime tool.
     *
     * @param array<string, mixed> $config Configuration for datetime
     * @return self
     */
    public static function withDateTime(array $config = []): self
    {
        $registry = new self();
        $registry->register(DateTimeTool::create($config));

        return $registry;
    }

    /**
     * Create a registry with only HTTP tool.
     *
     * @param array<string, mixed> $config Configuration for HTTP
     * @return self
     */
    public static function withHTTP(array $config = []): self
    {
        $registry = new self();
        $registry->register(HTTPTool::create($config));

        return $registry;
    }

    /**
     * Create a registry with only filesystem tool.
     *
     * @param array<string, mixed> $config Configuration for filesystem
     * @return self
     */
    public static function withFileSystem(array $config = []): self
    {
        $registry = new self();
        $registry->register(FileSystemTool::create($config));

        return $registry;
    }

    /**
     * Create a registry with only database tool.
     *
     * @param array<string, mixed> $config Configuration for database (must include 'connection')
     * @return self
     */
    public static function withDatabase(array $config): self
    {
        $registry = new self();
        $registry->register(DatabaseTool::create($config));

        return $registry;
    }

    /**
     * Create a registry with only regex tool.
     *
     * @param array<string, mixed> $config Configuration for regex
     * @return self
     */
    public static function withRegex(array $config = []): self
    {
        $registry = new self();
        $registry->register(RegexTool::create($config));

        return $registry;
    }

    /**
     * Create a custom registry with selected tools.
     *
     * @param array<string> $tools Tool names to include: 'calculator', 'datetime', 'http', 'filesystem', 'database', 'regex'
     * @param array<string, array<string, mixed>> $config Configuration for each tool
     * @return self
     */
    public static function withTools(array $tools, array $config = []): self
    {
        $registry = new self();

        foreach ($tools as $tool) {
            $toolConfig = $config[$tool] ?? [];

            match ($tool) {
                'calculator' => $registry->register(CalculatorTool::create($toolConfig)),
                'datetime' => $registry->register(DateTimeTool::create($toolConfig)),
                'http' => $registry->register(HTTPTool::create($toolConfig)),
                'filesystem' => $registry->register(FileSystemTool::create($toolConfig)),
                'database' => isset($toolConfig['connection']) ? $registry->register(DatabaseTool::create($toolConfig)) : null,
                'regex' => $registry->register(RegexTool::create($toolConfig)),
                default => null,
            };
        }

        return $registry;
    }
}
