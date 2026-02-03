<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP\Config;

/**
 * Configuration for the MCP Server.
 *
 * Manages server settings, transport options, and tool enablement.
 */
class MCPServerConfig
{
    /**
     * @param string $serverName Server name for MCP identification
     * @param string $serverVersion Server version
     * @param string $description Human-readable server description
     * @param string $transport Transport protocol ('stdio' or 'sse')
     * @param string $sseEndpoint HTTP endpoint for SSE transport
     * @param int $ssePort Port for SSE HTTP server
     * @param int $sessionTimeout Session timeout in seconds
     * @param bool $enableVisualization Enable workflow visualization tools
     * @param array<string> $toolsEnabled List of enabled tool names (empty = all)
     * @param int $maxExecutionTime Maximum agent execution time in seconds
     * @param bool $enableLogging Enable detailed logging
     * @param string|null $logPath Path for log file
     */
    public function __construct(
        public readonly string $serverName = 'claude-php-agent',
        public readonly string $serverVersion = '1.0.0',
        public readonly string $description = 'MCP server for Claude PHP Agent framework',
        public readonly string $transport = 'stdio',
        public readonly string $sseEndpoint = '/mcp',
        public readonly int $ssePort = 8080,
        public readonly int $sessionTimeout = 3600,
        public readonly bool $enableVisualization = true,
        public readonly array $toolsEnabled = [],
        public readonly int $maxExecutionTime = 300,
        public readonly bool $enableLogging = false,
        public readonly ?string $logPath = null,
    ) {
    }

    /**
     * Create config from array.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            serverName: $config['server_name'] ?? 'claude-php-agent',
            serverVersion: $config['server_version'] ?? '1.0.0',
            description: $config['description'] ?? 'MCP server for Claude PHP Agent framework',
            transport: $config['transport'] ?? 'stdio',
            sseEndpoint: $config['sse_endpoint'] ?? '/mcp',
            ssePort: $config['sse_port'] ?? 8080,
            sessionTimeout: $config['session_timeout'] ?? 3600,
            enableVisualization: $config['enable_visualization'] ?? true,
            toolsEnabled: $config['tools_enabled'] ?? [],
            maxExecutionTime: $config['max_execution_time'] ?? 300,
            enableLogging: $config['enable_logging'] ?? false,
            logPath: $config['log_path'] ?? null,
        );
    }

    /**
     * Convert config to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'server_name' => $this->serverName,
            'server_version' => $this->serverVersion,
            'description' => $this->description,
            'transport' => $this->transport,
            'sse_endpoint' => $this->sseEndpoint,
            'sse_port' => $this->ssePort,
            'session_timeout' => $this->sessionTimeout,
            'enable_visualization' => $this->enableVisualization,
            'tools_enabled' => $this->toolsEnabled,
            'max_execution_time' => $this->maxExecutionTime,
            'enable_logging' => $this->enableLogging,
            'log_path' => $this->logPath,
        ];
    }

    /**
     * Check if a tool is enabled.
     */
    public function isToolEnabled(string $toolName): bool
    {
        // If no specific tools are enabled, all are enabled
        if (empty($this->toolsEnabled)) {
            return true;
        }

        return in_array($toolName, $this->toolsEnabled, true);
    }

    /**
     * Validate configuration.
     *
     * @throws \InvalidArgumentException
     */
    public function validate(): void
    {
        if (!in_array($this->transport, ['stdio', 'sse'], true)) {
            throw new \InvalidArgumentException("Invalid transport: {$this->transport}. Must be 'stdio' or 'sse'.");
        }

        if ($this->sessionTimeout < 0) {
            throw new \InvalidArgumentException('Session timeout must be non-negative.');
        }

        if ($this->maxExecutionTime < 0) {
            throw new \InvalidArgumentException('Max execution time must be non-negative.');
        }

        if ($this->ssePort < 1 || $this->ssePort > 65535) {
            throw new \InvalidArgumentException('SSE port must be between 1 and 65535.');
        }
    }
}
