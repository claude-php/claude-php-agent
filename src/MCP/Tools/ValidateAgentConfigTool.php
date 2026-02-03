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
 * MCP tool for validating agent configuration.
 */
class ValidateAgentConfigTool extends AbstractMCPTool
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
        return 'validate_agent_config';
    }

    public function getDescription(): string
    {
        return 'Validate agent configuration parameters before creating or updating an agent.';
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
                'config' => [
                    'type' => 'object',
                    'description' => 'Configuration parameters to validate',
                ],
            ],
            'required' => ['agent_name', 'config'],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $this->validateParams($params);
            
            $agentName = $params['agent_name'];
            $config = $params['config'];
            
            // Validate agent exists
            $agentMetadata = $this->agentRegistry->getAgent($agentName);
            if ($agentMetadata === null) {
                return $this->error("Agent not found: {$agentName}", 'NOT_FOUND');
            }
            
            // Validate configuration parameters
            $errors = [];
            $warnings = [];
            
            // Check for common configuration keys
            $validKeys = ['name', 'model', 'max_tokens', 'temperature', 'tools', 'memory'];
            foreach ($config as $key => $value) {
                if (!in_array($key, $validKeys, true)) {
                    $warnings[] = "Unknown configuration key: {$key}";
                }
            }
            
            // Validate specific parameters
            if (isset($config['max_tokens']) && (!is_int($config['max_tokens']) || $config['max_tokens'] <= 0)) {
                $errors[] = 'max_tokens must be a positive integer';
            }
            
            if (isset($config['temperature']) && (!is_numeric($config['temperature']) || $config['temperature'] < 0 || $config['temperature'] > 1)) {
                $errors[] = 'temperature must be a number between 0 and 1';
            }
            
            $isValid = empty($errors);
            
            return $this->success([
                'valid' => $isValid,
                'agent_name' => $agentName,
                'errors' => $errors,
                'warnings' => $warnings,
                'config' => $config,
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
