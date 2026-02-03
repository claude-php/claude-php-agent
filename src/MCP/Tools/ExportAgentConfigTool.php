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
 * MCP tool for exporting agent configuration.
 */
class ExportAgentConfigTool extends AbstractMCPTool
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
        return 'export_agent_config';
    }

    public function getDescription(): string
    {
        return 'Export agent configuration and workflow summary as JSON.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'agent_name' => [
                    'type' => 'string',
                    'description' => 'Name of the agent to export',
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
            
            $summary = $this->visualizer->generateSummary($agentMetadata, $tools);
            
            return $this->success([
                'config' => $agentMetadata,
                'summary' => $summary,
                'export_timestamp' => time(),
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getCategory(): string
    {
        return 'visualization';
    }
}
