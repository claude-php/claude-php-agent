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
 * MCP tool for searching tools.
 */
class SearchToolsTool extends AbstractMCPTool
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
        return 'search_tools';
    }

    public function getDescription(): string
    {
        return 'Search for tools by name or description.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query to filter tools',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $this->validateParams($params);
            
            $query = strtolower($params['query']);
            $tools = $this->toolRegistry->all();
            $results = [];
            
            foreach ($tools as $tool) {
                $name = strtolower($tool->getName());
                $description = strtolower($tool->getDescription());
                
                if (str_contains($name, $query) || str_contains($description, $query)) {
                    $results[] = [
                        'name' => $tool->getName(),
                        'description' => $tool->getDescription(),
                    ];
                }
            }
            
            return $this->success([
                'count' => count($results),
                'query' => $params['query'],
                'tools' => $results,
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
