<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP;

use ClaudeAgents\MCP\Config\MCPServerConfig;
use ClaudeAgents\MCP\Contracts\MCPToolInterface;
use ClaudeAgents\MCP\Transport\TransportInterface;
use ClaudeAgents\Tools\ToolRegistry;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Main MCP Server implementation.
 *
 * Manages MCP tool registration, transport connections, and request routing.
 */
class MCPServer
{
    private MCPServerConfig $config;
    private AgentRegistry $agentRegistry;
    private ToolRegistry $toolRegistry;
    private SessionManager $sessionManager;
    private TransportInterface $transport;
    private LoggerInterface $logger;

    /**
     * @var array<string, MCPToolInterface>
     */
    private array $mcpTools = [];

    private bool $running = false;

    /**
     * @param array<string, mixed>|MCPServerConfig $config
     */
    public function __construct(
        private readonly ClaudePhp $client,
        array|MCPServerConfig $config = [],
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config instanceof MCPServerConfig 
            ? $config 
            : MCPServerConfig::fromArray($config);
        
        $this->config->validate();
        
        $this->logger = $logger ?? new NullLogger();
        $this->agentRegistry = new AgentRegistry($this->client);
        $this->toolRegistry = new ToolRegistry();
        $this->sessionManager = new SessionManager($this->config->sessionTimeout);
        
        $this->initializeTransport();
        $this->registerTools();
    }

    /**
     * Initialize the transport layer.
     */
    private function initializeTransport(): void
    {
        $transportClass = match ($this->config->transport) {
            'stdio' => \ClaudeAgents\MCP\Transport\StdioTransport::class,
            'sse' => \ClaudeAgents\MCP\Transport\SSETransport::class,
            default => throw new \InvalidArgumentException("Unknown transport: {$this->config->transport}"),
        };

        $this->transport = new $transportClass($this->config, $this->logger);
    }

    /**
     * Register all MCP tools.
     */
    private function registerTools(): void
    {
        $toolClasses = $this->discoverToolClasses();

        foreach ($toolClasses as $toolClass) {
            if (!class_exists($toolClass)) {
                $this->logger->warning("Tool class not found: {$toolClass}");
                continue;
            }

            try {
                $tool = new $toolClass(
                    $this->agentRegistry,
                    $this->toolRegistry,
                    $this->sessionManager,
                    $this->client,
                    $this->logger
                );

                if (!($tool instanceof MCPToolInterface)) {
                    $this->logger->warning("Class {$toolClass} does not implement MCPToolInterface");
                    continue;
                }

                $toolName = $tool->getName();
                
                if (!$this->config->isToolEnabled($toolName)) {
                    $this->logger->info("Tool {$toolName} is disabled by configuration");
                    continue;
                }

                $this->mcpTools[$toolName] = $tool;
                $this->logger->info("Registered MCP tool: {$toolName}");
            } catch (\Exception $e) {
                $this->logger->error("Failed to register tool {$toolClass}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Discover all tool classes.
     *
     * @return array<string>
     */
    private function discoverToolClasses(): array
    {
        return [
            // Agent Discovery Tools
            \ClaudeAgents\MCP\Tools\SearchAgentsTool::class,
            \ClaudeAgents\MCP\Tools\ListAgentTypesTool::class,
            \ClaudeAgents\MCP\Tools\GetAgentDetailsTool::class,
            \ClaudeAgents\MCP\Tools\CountAgentsTool::class,
            
            // Execution Tools
            \ClaudeAgents\MCP\Tools\RunAgentTool::class,
            \ClaudeAgents\MCP\Tools\GetExecutionStatusTool::class,
            
            // Tool Management
            \ClaudeAgents\MCP\Tools\ListToolsTool::class,
            \ClaudeAgents\MCP\Tools\GetToolDetailsTool::class,
            \ClaudeAgents\MCP\Tools\SearchToolsTool::class,
            
            // Visualization Tools
            \ClaudeAgents\MCP\Tools\VisualizeWorkflowTool::class,
            \ClaudeAgents\MCP\Tools\GetAgentGraphTool::class,
            \ClaudeAgents\MCP\Tools\ExportAgentConfigTool::class,
            
            // Configuration Tools
            \ClaudeAgents\MCP\Tools\UpdateAgentConfigTool::class,
            \ClaudeAgents\MCP\Tools\CreateAgentInstanceTool::class,
            \ClaudeAgents\MCP\Tools\ValidateAgentConfigTool::class,
        ];
    }

    /**
     * Start the MCP server.
     */
    public function start(): void
    {
        $this->logger->info("Starting MCP server '{$this->config->serverName}' with {$this->config->transport} transport");
        $this->logger->info("Registered " . count($this->mcpTools) . " MCP tools");
        
        $this->running = true;
        $this->transport->start();
        
        while ($this->running && $this->transport->isConnected()) {
            $request = $this->transport->receive();
            
            if ($request === null) {
                continue;
            }
            
            $response = $this->handleRequest($request);
            $this->transport->send($response);
        }
    }

    /**
     * Handle an MCP request.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function handleRequest(array $request): array
    {
        try {
            $method = $request['method'] ?? null;
            $params = $request['params'] ?? [];
            $id = $request['id'] ?? null;

            // Handle initialize request
            if ($method === 'initialize') {
                return $this->handleInitialize($id);
            }

            // Handle list tools request
            if ($method === 'tools/list') {
                return $this->handleListTools($id);
            }

            // Handle tool call request
            if ($method === 'tools/call') {
                $toolName = $params['name'] ?? null;
                $toolParams = $params['arguments'] ?? [];
                
                return $this->handleToolCall($id, $toolName, $toolParams);
            }

            return $this->errorResponse($id, "Unknown method: {$method}");
        } catch (\Exception $e) {
            $this->logger->error("Error handling request: {$e->getMessage()}");
            return $this->errorResponse($request['id'] ?? null, $e->getMessage());
        }
    }

    /**
     * Handle initialize request.
     *
     * @return array<string, mixed>
     */
    private function handleInitialize($id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => '2024-11-05',
                'serverInfo' => [
                    'name' => $this->config->serverName,
                    'version' => $this->config->serverVersion,
                ],
                'capabilities' => [
                    'tools' => (object)[],
                ],
            ],
        ];
    }

    /**
     * Handle list tools request.
     *
     * @return array<string, mixed>
     */
    private function handleListTools($id): array
    {
        $tools = [];
        
        foreach ($this->mcpTools as $tool) {
            $tools[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'tools' => $tools,
            ],
        ];
    }

    /**
     * Handle tool call request.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleToolCall($id, ?string $toolName, array $params): array
    {
        if ($toolName === null) {
            return $this->errorResponse($id, 'Tool name is required');
        }

        if (!isset($this->mcpTools[$toolName])) {
            return $this->errorResponse($id, "Unknown tool: {$toolName}");
        }

        try {
            $tool = $this->mcpTools[$toolName];
            $result = $tool->execute($params);

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => json_encode($result, JSON_PRETTY_PRINT),
                        ],
                    ],
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error("Tool execution error ({$toolName}): {$e->getMessage()}");
            return $this->errorResponse($id, "Tool execution failed: {$e->getMessage()}");
        }
    }

    /**
     * Create error response.
     *
     * @return array<string, mixed>
     */
    private function errorResponse($id, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => -32603,
                'message' => $message,
            ],
        ];
    }

    /**
     * Shutdown the server.
     */
    public function shutdown(): void
    {
        $this->logger->info('Shutting down MCP server');
        $this->running = false;
        $this->transport->shutdown();
        $this->sessionManager->clearAll();
    }

    /**
     * Get the agent registry.
     */
    public function getAgentRegistry(): AgentRegistry
    {
        return $this->agentRegistry;
    }

    /**
     * Get the tool registry.
     */
    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    /**
     * Get the session manager.
     */
    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }

    /**
     * Get registered MCP tools.
     *
     * @return array<string, MCPToolInterface>
     */
    public function getMCPTools(): array
    {
        return $this->mcpTools;
    }
}
