<?php

declare(strict_types=1);

namespace ClaudeAgents\Templates;

use ClaudeAgents\Agent;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudeAgents\Agents\HierarchicalAgent;
use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Agents\ReflexAgent;
use ClaudeAgents\Agents\ModelBasedAgent;
use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudeAgents\Agents\MakerAgent;
use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\CoordinatorAgent;
use ClaudeAgents\Agents\IntentClassifierAgent;
use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Agents\MemoryManagerAgent;
use ClaudeAgents\Templates\Exceptions\TemplateInstantiationException;
use ClaudePhp\ClaudePhp;
use Throwable;

/**
 * Instantiates agents from templates.
 */
class TemplateInstantiator
{
    private array $agentTypeMap = [];

    public function __construct()
    {
        $this->initializeAgentTypeMap();
    }

    private function initializeAgentTypeMap(): void
    {
        $this->agentTypeMap = [
            'Agent' => Agent::class,
            'ReactAgent' => ReactAgent::class,
            'ReflectionAgent' => ReflectionAgent::class,
            'HierarchicalAgent' => HierarchicalAgent::class,
            'DialogAgent' => DialogAgent::class,
            'ChainOfThoughtAgent' => ChainOfThoughtAgent::class,
            'ReflexAgent' => ReflexAgent::class,
            'ModelBasedAgent' => ModelBasedAgent::class,
            'PlanExecuteAgent' => PlanExecuteAgent::class,
            'TreeOfThoughtsAgent' => TreeOfThoughtsAgent::class,
            'MakerAgent' => MakerAgent::class,
            'AdaptiveAgentService' => AdaptiveAgentService::class,
            'CoordinatorAgent' => CoordinatorAgent::class,
            'IntentClassifierAgent' => IntentClassifierAgent::class,
            'MonitoringAgent' => MonitoringAgent::class,
            'MemoryManagerAgent' => MemoryManagerAgent::class,
        ];
    }

    /**
     * Instantiate an agent from a template.
     *
     * @param Template $template The template to instantiate
     * @param array $overrides Configuration overrides (api_key, model, tools, etc.)
     * @return object The instantiated agent
     * @throws TemplateInstantiationException
     */
    public function instantiate(Template $template, array $overrides = []): object
    {
        // Validate template first
        if (!$template->isValid()) {
            throw TemplateInstantiationException::invalidConfiguration(
                'Template validation failed: ' . implode(', ', $template->getErrors())
            );
        }

        $agentType = $template->getAgentType();
        if (!$agentType) {
            throw TemplateInstantiationException::invalidConfiguration('Agent type not specified in template');
        }

        // Get agent class
        $agentClass = $this->getAgentClass($agentType);

        // Merge template config with overrides
        $config = array_merge($template->getConfig(), $overrides);

        // Extract required dependencies
        $client = $this->extractClient($config);

        // Build agent configuration array
        $agentConfig = $this->buildAgentConfig($config);

        try {
            // Instantiate based on agent type
            return $this->createAgent($agentClass, $client, $agentConfig);
        } catch (Throwable $e) {
            throw TemplateInstantiationException::fromPrevious(
                "Failed to instantiate {$agentType}: {$e->getMessage()}",
                $e
            );
        }
    }

    /**
     * Get the agent class for a given type.
     */
    private function getAgentClass(string $agentType): string
    {
        if (!isset($this->agentTypeMap[$agentType])) {
            throw TemplateInstantiationException::agentTypeNotFound($agentType);
        }

        $class = $this->agentTypeMap[$agentType];

        if (!class_exists($class)) {
            throw TemplateInstantiationException::agentTypeNotFound($agentType);
        }

        return $class;
    }

    /**
     * Extract or create the Claude client.
     */
    private function extractClient(array &$config): ClaudePhp
    {
        // If client is provided directly
        if (isset($config['client']) && $config['client'] instanceof ClaudePhp) {
            $client = $config['client'];
            unset($config['client']);
            return $client;
        }

        // If API key is provided, create client
        if (isset($config['api_key'])) {
            $apiKey = $config['api_key'];
            unset($config['api_key']);
            return new ClaudePhp(apiKey: $apiKey);
        }

        // Try environment variable
        $apiKey = getenv('ANTHROPIC_API_KEY');
        if ($apiKey === false) {
            throw TemplateInstantiationException::missingDependency('api_key or ANTHROPIC_API_KEY environment variable');
        }

        return new ClaudePhp(apiKey: $apiKey);
    }

    /**
     * Build agent configuration from template config.
     */
    private function buildAgentConfig(array $config): array
    {
        $agentConfig = [];

        // Map common configuration options
        $configMapping = [
            'model' => 'model',
            'max_iterations' => 'max_iterations',
            'system_prompt' => 'system',
            'system' => 'system',
            'temperature' => 'temperature',
            'max_tokens' => 'max_tokens',
            'tools' => 'tools',
            'memory' => 'memory',
            'timeout' => 'timeout',
        ];

        foreach ($configMapping as $templateKey => $agentKey) {
            if (isset($config[$templateKey])) {
                $agentConfig[$agentKey] = $config[$templateKey];
            }
        }

        // Add any other config options not in the mapping
        foreach ($config as $key => $value) {
            if (!isset($configMapping[$key]) && $key !== 'agent_type') {
                $agentConfig[$key] = $value;
            }
        }

        return $agentConfig;
    }

    /**
     * Create the agent instance.
     */
    private function createAgent(string $agentClass, ClaudePhp $client, array $config): object
    {
        // Special case for Agent base class - use fluent API
        if ($agentClass === Agent::class) {
            return $this->createBaseAgent($client, $config);
        }

        // For other agents, use constructor
        return new $agentClass($client, $config);
    }

    /**
     * Create base Agent using fluent API.
     */
    private function createBaseAgent(ClaudePhp $client, array $config): Agent
    {
        $agent = Agent::create($client);

        if (isset($config['model'])) {
            $agent->withModel($config['model']);
        }

        if (isset($config['system'])) {
            $agent->withSystemPrompt($config['system']);
        }

        if (isset($config['max_iterations'])) {
            $agent->maxIterations($config['max_iterations']);
        }

        if (isset($config['tools']) && is_array($config['tools'])) {
            foreach ($config['tools'] as $tool) {
                $agent->withTool($tool);
            }
        }

        if (isset($config['memory'])) {
            $agent->withMemory($config['memory']);
        }

        return $agent;
    }

    /**
     * Register a custom agent type.
     */
    public function registerAgentType(string $name, string $class): self
    {
        if (!class_exists($class)) {
            throw TemplateInstantiationException::agentTypeNotFound($class);
        }

        $this->agentTypeMap[$name] = $class;
        return $this;
    }

    /**
     * Get all registered agent types.
     */
    public function getRegisteredAgentTypes(): array
    {
        return array_keys($this->agentTypeMap);
    }

    /**
     * Check if an agent type is registered.
     */
    public function hasAgentType(string $name): bool
    {
        return isset($this->agentTypeMap[$name]);
    }
}
