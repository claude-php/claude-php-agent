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
 * MCP tool for searching agents.
 */
class SearchAgentsTool extends AbstractMCPTool
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
        return 'search_agents';
    }

    public function getDescription(): string
    {
        return 'Search for agents by name, type, or capabilities. Returns matching agent metadata.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query to filter agents by name, type, or description',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Filter by agent type (e.g., react, rag, chain-of-thought)',
                ],
                'capabilities' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Filter by capabilities (e.g., reasoning, tool-use, memory)',
                ],
            ],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $this->validateParams($params);
            
            $query = $params['query'] ?? null;
            $type = $params['type'] ?? null;
            $capabilities = $params['capabilities'] ?? null;
            
            $results = $this->agentRegistry->search($query, $type, $capabilities);
            
            return $this->success([
                'count' => count($results),
                'agents' => $results,
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
