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
 * MCP tool for executing agents.
 */
class RunAgentTool extends AbstractMCPTool
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
        return 'run_agent';
    }

    public function getDescription(): string
    {
        return 'Execute an agent with specified parameters and return the result.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'agent_name' => [
                    'type' => 'string',
                    'description' => 'Name of the agent to run (e.g., ReactAgent, RAGAgent)',
                ],
                'input' => [
                    'type' => 'string',
                    'description' => 'Input prompt or task for the agent',
                ],
                'options' => [
                    'type' => 'object',
                    'description' => 'Optional configuration for the agent',
                ],
                'session_id' => [
                    'type' => 'string',
                    'description' => 'Optional session ID for memory persistence',
                ],
            ],
            'required' => ['agent_name', 'input'],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $this->validateParams($params);
            
            $agentName = $params['agent_name'];
            $input = $params['input'];
            $options = $params['options'] ?? [];
            $sessionId = $params['session_id'] ?? uniqid('session_', true);
            
            // Get or create agent instance
            $agent = $this->agentRegistry->createAgent($agentName, $options);
            
            if ($agent === null) {
                return $this->error("Failed to create agent: {$agentName}", 'CREATION_FAILED');
            }
            
            // Execute the agent
            $startTime = microtime(true);
            $result = $agent->run($input);
            $executionTime = microtime(true) - $startTime;
            
            // Store execution in session
            $this->sessionManager->setSessionData($sessionId, 'last_execution', [
                'agent' => $agentName,
                'input' => $input,
                'output' => $result->getOutput(),
                'timestamp' => time(),
                'execution_time' => $executionTime,
            ]);
            
            return $this->success([
                'agent' => $agentName,
                'output' => $result->getOutput(),
                'session_id' => $sessionId,
                'execution_time' => round($executionTime, 3),
                'metadata' => [
                    'success' => true,
                    'timestamp' => time(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Agent execution failed: {$e->getMessage()}");
            return $this->error("Execution failed: {$e->getMessage()}", 'EXECUTION_ERROR');
        }
    }

    public function getCategory(): string
    {
        return 'execution';
    }
}
