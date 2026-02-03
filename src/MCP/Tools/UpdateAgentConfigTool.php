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
 * MCP tool for updating agent configuration.
 */
class UpdateAgentConfigTool extends AbstractMCPTool
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
        return 'update_agent_config';
    }

    public function getDescription(): string
    {
        return 'Update agent configuration parameters for a session.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'session_id' => [
                    'type' => 'string',
                    'description' => 'Session ID to update configuration for',
                ],
                'config' => [
                    'type' => 'object',
                    'description' => 'Configuration parameters to update',
                ],
            ],
            'required' => ['session_id', 'config'],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $this->validateParams($params);
            
            $sessionId = $params['session_id'];
            $config = $params['config'];
            
            // Store updated config in session
            $existingConfig = $this->sessionManager->getSessionData($sessionId, 'agent_config') ?? [];
            $mergedConfig = array_merge($existingConfig, $config);
            
            $this->sessionManager->setSessionData($sessionId, 'agent_config', $mergedConfig);
            
            return $this->success([
                'session_id' => $sessionId,
                'config' => $mergedConfig,
                'updated_at' => time(),
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
