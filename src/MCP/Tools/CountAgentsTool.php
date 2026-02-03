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
 * MCP tool for counting agents.
 */
class CountAgentsTool extends AbstractMCPTool
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
        return 'count_agents';
    }

    public function getDescription(): string
    {
        return 'Count the total number of available agents, optionally filtered by type.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'description' => 'Optional agent type to count (e.g., react, rag)',
                ],
            ],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $this->validateParams($params);
            
            $type = $params['type'] ?? null;
            $count = $this->agentRegistry->count($type);
            
            return $this->success([
                'count' => $count,
                'type' => $type ?? 'all',
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getCategory(): string
    {
        return 'agent';
    }
}
