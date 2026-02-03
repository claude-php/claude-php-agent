<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP;

use ClaudeAgents\Factory\AgentFactory;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;

/**
 * Registry for discovering and managing agent metadata.
 *
 * Scans available agents and provides search/filter capabilities.
 */
class AgentRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $agents = [];

    private AgentFactory $factory;

    public function __construct(
        private readonly ClaudePhp $client
    ) {
        $this->factory = new AgentFactory($this->client);
        $this->discoverAgents();
    }

    /**
     * Discover all available agents.
     */
    private function discoverAgents(): void
    {
        $agentClasses = [
            'ReactAgent' => [
                'type' => 'react',
                'description' => 'Reasoning and Action agent that iteratively thinks and acts',
                'capabilities' => ['reasoning', 'tool-use', 'iterative'],
                'factory_method' => 'createReactAgent',
            ],
            'ChainOfThoughtAgent' => [
                'type' => 'chain-of-thought',
                'description' => 'Agent that breaks down problems step-by-step',
                'capabilities' => ['reasoning', 'step-by-step', 'explanation'],
                'factory_method' => 'createChainOfThoughtAgent',
            ],
            'TreeOfThoughtsAgent' => [
                'type' => 'tree-of-thoughts',
                'description' => 'Agent that explores multiple reasoning paths',
                'capabilities' => ['reasoning', 'branching', 'exploration'],
                'factory_method' => 'createTreeOfThoughtsAgent',
            ],
            'PlanExecuteAgent' => [
                'type' => 'plan-execute',
                'description' => 'Agent that creates plans and executes them',
                'capabilities' => ['planning', 'execution', 'structured'],
                'factory_method' => 'createPlanExecuteAgent',
            ],
            'ReflectionAgent' => [
                'type' => 'reflection',
                'description' => 'Agent that reflects on and improves its outputs',
                'capabilities' => ['reflection', 'self-improvement', 'iteration'],
                'factory_method' => 'createReflectionAgent',
            ],
            'RAGAgent' => [
                'type' => 'rag',
                'description' => 'Retrieval Augmented Generation agent',
                'capabilities' => ['retrieval', 'context', 'knowledge'],
                'factory_method' => 'createRAGAgent',
            ],
            'DialogAgent' => [
                'type' => 'dialog',
                'description' => 'Conversational agent with memory',
                'capabilities' => ['conversation', 'memory', 'context'],
                'factory_method' => 'createDialogAgent',
            ],
            'AutonomousAgent' => [
                'type' => 'autonomous',
                'description' => 'Self-directed agent with goal pursuit',
                'capabilities' => ['autonomous', 'goal-driven', 'adaptive'],
                'factory_method' => 'createAutonomousAgent',
            ],
            'CoordinatorAgent' => [
                'type' => 'coordinator',
                'description' => 'Agent that coordinates multiple sub-agents',
                'capabilities' => ['coordination', 'multi-agent', 'orchestration'],
                'factory_method' => 'createCoordinatorAgent',
            ],
            'WorkerAgent' => [
                'type' => 'worker',
                'description' => 'Specialized agent for specific tasks',
                'capabilities' => ['specialized', 'task-focused'],
                'factory_method' => 'createWorkerAgent',
            ],
            'HierarchicalAgent' => [
                'type' => 'hierarchical',
                'description' => 'Agent with hierarchical task decomposition',
                'capabilities' => ['hierarchical', 'decomposition', 'coordination'],
                'factory_method' => 'createHierarchicalAgent',
            ],
            'CodeGenerationAgent' => [
                'type' => 'code-generation',
                'description' => 'Agent specialized in generating and validating code',
                'capabilities' => ['code-generation', 'validation', 'iteration'],
                'factory_method' => 'createCodeGenerationAgent',
            ],
            'MakerAgent' => [
                'type' => 'maker',
                'description' => 'Agent for creative problem solving and generation',
                'capabilities' => ['creative', 'generation', 'problem-solving'],
                'factory_method' => 'createMakerAgent',
            ],
            'LearningAgent' => [
                'type' => 'learning',
                'description' => 'Agent that learns from experience',
                'capabilities' => ['learning', 'adaptation', 'memory'],
                'factory_method' => 'createLearningAgent',
            ],
            'MonitoringAgent' => [
                'type' => 'monitoring',
                'description' => 'Agent for system monitoring and alerts',
                'capabilities' => ['monitoring', 'alerts', 'analysis'],
                'factory_method' => 'createMonitoringAgent',
            ],
            'SchedulerAgent' => [
                'type' => 'scheduler',
                'description' => 'Agent for task scheduling and execution',
                'capabilities' => ['scheduling', 'task-management', 'automation'],
                'factory_method' => 'createSchedulerAgent',
            ],
        ];

        foreach ($agentClasses as $name => $metadata) {
            $this->agents[$name] = array_merge($metadata, [
                'name' => $name,
                'class' => "ClaudeAgents\\Agents\\{$name}",
            ]);
        }
    }

    /**
     * Search agents by query.
     *
     * @return array<array<string, mixed>>
     */
    public function search(?string $query = null, ?string $type = null, ?array $capabilities = null): array
    {
        $results = $this->agents;

        // Filter by type
        if ($type !== null) {
            $results = array_filter($results, fn($agent) => $agent['type'] === $type);
        }

        // Filter by capabilities
        if ($capabilities !== null && !empty($capabilities)) {
            $results = array_filter($results, function ($agent) use ($capabilities) {
                return !empty(array_intersect($capabilities, $agent['capabilities']));
            });
        }

        // Filter by query
        if ($query !== null && $query !== '') {
            $query = strtolower($query);
            $results = array_filter($results, function ($agent) use ($query) {
                return str_contains(strtolower($agent['name']), $query)
                    || str_contains(strtolower($agent['description']), $query)
                    || str_contains(strtolower($agent['type']), $query);
            });
        }

        return array_values($results);
    }

    /**
     * Get agent by name.
     *
     * @return array<string, mixed>|null
     */
    public function getAgent(string $name): ?array
    {
        return $this->agents[$name] ?? null;
    }

    /**
     * Get all agent types.
     *
     * @return array<string>
     */
    public function getTypes(): array
    {
        $types = array_unique(array_column($this->agents, 'type'));
        sort($types);
        return $types;
    }

    /**
     * Count agents by type.
     */
    public function count(?string $type = null): int
    {
        if ($type === null) {
            return count($this->agents);
        }

        return count(array_filter($this->agents, fn($agent) => $agent['type'] === $type));
    }

    /**
     * Get all registered agents.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->agents;
    }

    /**
     * Get the agent factory.
     */
    public function getFactory(): AgentFactory
    {
        return $this->factory;
    }

    /**
     * Create an agent instance.
     *
     * @param array<string, mixed> $options
     */
    public function createAgent(string $name, array $options = []): ?AgentInterface
    {
        $agentInfo = $this->getAgent($name);
        
        if ($agentInfo === null) {
            return null;
        }

        $factoryMethod = $agentInfo['factory_method'];
        
        if (!method_exists($this->factory, $factoryMethod)) {
            return null;
        }

        return $this->factory->$factoryMethod($options);
    }
}
