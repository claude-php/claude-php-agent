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
 * MCP tool for visualizing agent workflows.
 */
class VisualizeWorkflowTool extends AbstractMCPTool
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
        return 'visualize_workflow';
    }

    public function getDescription(): string
    {
        return 'Generate a complete visualization of an agent workflow including ASCII art, JSON graph, and text representation.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'agent_name' => [
                    'type' => 'string',
                    'description' => 'Name of the agent to visualize',
                ],
                'tools' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional list of tools used by the agent',
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
            
            $visualization = $this->visualizer->generateComplete($agentMetadata, $tools);
            
            return $this->success($visualization);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getCategory(): string
    {
        return 'visualization';
    }
}
