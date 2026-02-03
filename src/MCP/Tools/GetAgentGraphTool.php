<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP\Tools;

use ClaudeAgents\MCP\AbstractMCPTool;
use ClaudeAgents\MCP\AgentRegistry;
use ClaudeAgents\MCP\SessionManager;
use ClaudeAgents\MCP\Visualization\WorkflowVisualizer;
use ClaudeAgents\Tools\ToolRegistry;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;

/**
 * MCP tool for getting agent graph representation.
 */
class GetAgentGraphTool extends AbstractMCPTool
{
    private WorkflowVisualizer $visualizer;

    public function __construct(
        private readonly AgentRegistry $agentRegistry,
        private readonly ToolRegistry $toolRegistry,
        private readonly SessionManager $sessionManager,
        private readonly ClaudePhp $client,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
        $this->visualizer = new WorkflowVisualizer();
    }

    public function getName(): string
    {
        return 'get_agent_graph';
    }

    public function getDescription(): string
    {
        return 'Get the graph representation of an agent workflow with vertices and edges.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'agent_name' => [
                    'type' => 'string',
                    'description' => 'Name of the agent',
                ],
                'tools' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional list of tools',
                ],
            ],
            'required' => ['agent_name'],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $this->validateParams($params);
            
            $agentName = $params['agent_name'];
            $tools = $params['tools'] ?? [];
            
            $agentMetadata = $this->agentRegistry->getAgent($agentName);
            
            if ($agentMetadata === null) {
                return $this->error("Agent not found: {$agentName}", 'NOT_FOUND');
            }
            
            $graph = $this->visualizer->generateGraphRepresentation($agentMetadata, $tools);
            
            return $this->success($graph);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getCategory(): string
    {
        return 'visualization';
    }
}
