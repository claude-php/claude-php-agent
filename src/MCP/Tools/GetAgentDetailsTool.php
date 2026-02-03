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
 * MCP tool for getting detailed agent information.
 */
class GetAgentDetailsTool extends AbstractMCPTool
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
        return 'get_agent_details';
    }

    public function getDescription(): string
    {
        return 'Get detailed information about a specific agent including metadata, capabilities, and configuration options.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'agent_name' => [
                    'type' => 'string',
                    'description' => 'The name of the agent (e.g., ReactAgent, RAGAgent)',
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
            $agent = $this->agentRegistry->getAgent($agentName);
            
            if ($agent === null) {
                return $this->error("Agent not found: {$agentName}", 'NOT_FOUND');
            }
            
            return $this->success($agent);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getCategory(): string
    {
        return 'agent';
    }
}
