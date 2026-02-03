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
 * MCP tool for checking execution status.
 */
class GetExecutionStatusTool extends AbstractMCPTool
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
        return 'get_execution_status';
    }

    public function getDescription(): string
    {
        return 'Get the status and result of a previous agent execution by session ID.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'session_id' => [
                    'type' => 'string',
                    'description' => 'Session ID from a previous execution',
                ],
            ],
            'required' => ['session_id'],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $this->validateParams($params);
            
            $sessionId = $params['session_id'];
            
            if (!$this->sessionManager->hasSession($sessionId)) {
                return $this->error("Session not found: {$sessionId}", 'NOT_FOUND');
            }
            
            $lastExecution = $this->sessionManager->getSessionData($sessionId, 'last_execution');
            
            if ($lastExecution === null) {
                return $this->success([
                    'session_id' => $sessionId,
                    'status' => 'no_executions',
                    'message' => 'No executions found for this session',
                ]);
            }
            
            return $this->success([
                'session_id' => $sessionId,
                'status' => 'completed',
                'execution' => $lastExecution,
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getCategory(): string
    {
        return 'execution';
    }
}
