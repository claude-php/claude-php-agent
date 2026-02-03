<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP\Tools;

use ClaudeAgents\MCP\AbstractMCPTool;
use ClaudeAgents\MCP\AgentRegistry;
use ClaudeAgents\MCP\SessionManager;
use ClaudeAgents\Tools\ToolRegistry;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;

/**
 * MCP tool for getting tool details.
 */
class GetToolDetailsTool extends AbstractMCPTool
{
    public function __construct(
        private readonly AgentRegistry $agentRegistry,
        private readonly ToolRegistry $toolRegistry,
        private readonly SessionManager $sessionManager,
        private readonly ClaudePhp $client,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
    }

    public function getName(): string
    {
        return 'get_tool_details';
    }

    public function getDescription(): string
    {
        return 'Get detailed information about a specific tool including its schema and usage.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tool_name' => [
                    'type' => 'string',
                    'description' => 'Name of the tool to get details for',
                ],
            ],
            'required' => ['tool_name'],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $this->validateParams($params);
            
            $toolName = $params['tool_name'];
            $tool = $this->toolRegistry->get($toolName);
            
            if ($tool === null) {
                return $this->error("Tool not found: {$toolName}", 'NOT_FOUND');
            }
            
            return $this->success([
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'schema' => $tool->toDefinition(),
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getCategory(): string
    {
        return 'tool';
    }
}
