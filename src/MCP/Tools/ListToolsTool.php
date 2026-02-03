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
 * MCP tool for listing available tools.
 */
class ListToolsTool extends AbstractMCPTool
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
        return 'list_tools';
    }

    public function getDescription(): string
    {
        return 'List all available tools that can be used by agents.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $tools = $this->toolRegistry->all();
            $toolList = [];
            
            foreach ($tools as $tool) {
                $toolList[] = [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                ];
            }
            
            return $this->success([
                'count' => count($toolList),
                'tools' => $toolList,
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
