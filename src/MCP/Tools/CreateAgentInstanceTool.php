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
 * MCP tool for creating configured agent instances.
 */
class CreateAgentInstanceTool extends AbstractMCPTool
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
        return 'create_agent_instance';
    }

    public function getDescription(): string
    {
        return 'Create a configured agent instance and store it in a session for later use.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'agent_name' => [
                    'type' => 'string',
                    'description' => 'Name of the agent to create',
                ],
                'config' => [
                    'type' => 'object',
                    'description' => 'Configuration options for the agent',
                ],
                'session_id' => [
                    'type' => 'string',
                    'description' => 'Optional session ID (will be generated if not provided)',
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
            $config = $params['config'] ?? [];
            $sessionId = $params['session_id'] ?? uniqid('session_', true);
            
            // Validate agent exists
            $agentMetadata = $this->agentRegistry->getAgent($agentName);
            if ($agentMetadata === null) {
                return $this->error("Agent not found: {$agentName}", 'NOT_FOUND');
            }
            
            // Store configuration in session
            $this->sessionManager->setSessionData($sessionId, 'agent_config', [
                'agent_name' => $agentName,
                'config' => $config,
                'created_at' => time(),
            ]);
            
            return $this->success([
                'session_id' => $sessionId,
                'agent_name' => $agentName,
                'config' => $config,
                'status' => 'created',
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getCategory(): string
    {
        return 'configuration';
    }
}
