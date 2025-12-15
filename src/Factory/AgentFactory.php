<?php

declare(strict_types=1);

namespace ClaudeAgents\Factory;

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Agents\AutonomousAgent;
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Agents\CoordinatorAgent;
use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Agents\EnvironmentSimulatorAgent;
use ClaudeAgents\Agents\HierarchicalAgent;
use ClaudeAgents\Agents\IntentClassifierAgent;
use ClaudeAgents\Agents\LearningAgent;
use ClaudeAgents\Agents\MakerAgent;
use ClaudeAgents\Agents\MemoryManagerAgent;
use ClaudeAgents\Agents\ModelBasedAgent;
use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudeAgents\Agents\RAGAgent;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudeAgents\Agents\ReflexAgent;
use ClaudeAgents\Agents\SchedulerAgent;
use ClaudeAgents\Agents\SolutionDiscriminatorAgent;
use ClaudeAgents\Agents\TaskPrioritizationAgent;
use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudeAgents\Agents\UtilityBasedAgent;
use ClaudeAgents\Agents\WorkerAgent;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating agents with consistent configuration.
 *
 * Implements the Factory Pattern to centralize agent creation,
 * ensuring consistent initialization and reducing coupling.
 *
 * @example
 * ```php
 * $factory = new AgentFactory($client, $logger);
 * $agent = $factory->createReactAgent(['name' => 'my_agent']);
 * ```
 */
class AgentFactory
{
    public function __construct(
        private readonly ClaudePhp $client,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Create a ReAct agent.
     *
     * @param array<string, mixed> $options
     */
    public function createReactAgent(array $options = []): ReactAgent
    {
        return new ReactAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Chain-of-Thought agent.
     *
     * @param array<string, mixed> $options
     */
    public function createChainOfThoughtAgent(array $options = []): ChainOfThoughtAgent
    {
        return new ChainOfThoughtAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Tree-of-Thoughts agent.
     *
     * @param array<string, mixed> $options
     */
    public function createTreeOfThoughtsAgent(array $options = []): TreeOfThoughtsAgent
    {
        return new TreeOfThoughtsAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Plan-Execute agent.
     *
     * @param array<string, mixed> $options
     */
    public function createPlanExecuteAgent(array $options = []): PlanExecuteAgent
    {
        return new PlanExecuteAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Reflection agent.
     *
     * @param array<string, mixed> $options
     */
    public function createReflectionAgent(array $options = []): ReflectionAgent
    {
        return new ReflectionAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Worker agent.
     *
     * @param array<string, mixed> $options
     */
    public function createWorkerAgent(array $options = []): WorkerAgent
    {
        return new WorkerAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Hierarchical agent.
     *
     * @param array<string, mixed> $options
     */
    public function createHierarchicalAgent(array $options = []): HierarchicalAgent
    {
        return new HierarchicalAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a RAG agent.
     *
     * @param array<string, mixed> $options
     */
    public function createRAGAgent(array $options = []): RAGAgent
    {
        return new RAGAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create an Autonomous agent.
     *
     * @param array<string, mixed> $options
     */
    public function createAutonomousAgent(array $options = []): AutonomousAgent
    {
        return new AutonomousAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Dialog agent.
     *
     * @param array<string, mixed> $options
     */
    public function createDialogAgent(array $options = []): DialogAgent
    {
        return new DialogAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create an Alert agent.
     *
     * @param array<string, mixed> $options
     */
    public function createAlertAgent(array $options = []): AlertAgent
    {
        return new AlertAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Monitoring agent.
     *
     * @param array<string, mixed> $options
     */
    public function createMonitoringAgent(array $options = []): MonitoringAgent
    {
        return new MonitoringAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Coordinator agent.
     *
     * @param array<string, mixed> $options
     */
    public function createCoordinatorAgent(array $options = []): CoordinatorAgent
    {
        return new CoordinatorAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Scheduler agent.
     *
     * @param array<string, mixed> $options
     */
    public function createSchedulerAgent(array $options = []): SchedulerAgent
    {
        return new SchedulerAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Task Prioritization agent.
     *
     * @param array<string, mixed> $options
     */
    public function createTaskPrioritizationAgent(array $options = []): TaskPrioritizationAgent
    {
        return new TaskPrioritizationAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create an Intent Classifier agent.
     *
     * @param array<string, mixed> $options
     */
    public function createIntentClassifierAgent(array $options = []): IntentClassifierAgent
    {
        return new IntentClassifierAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Memory Manager agent.
     *
     * @param array<string, mixed> $options
     */
    public function createMemoryManagerAgent(array $options = []): MemoryManagerAgent
    {
        return new MemoryManagerAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Solution Discriminator agent.
     *
     * @param array<string, mixed> $options
     */
    public function createSolutionDiscriminatorAgent(array $options = []): SolutionDiscriminatorAgent
    {
        return new SolutionDiscriminatorAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Reflex agent.
     *
     * @param array<string, mixed> $options
     */
    public function createReflexAgent(array $options = []): ReflexAgent
    {
        return new ReflexAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create an Environment Simulator agent.
     *
     * @param array<string, mixed> $options
     */
    public function createEnvironmentSimulatorAgent(array $options = []): EnvironmentSimulatorAgent
    {
        return new EnvironmentSimulatorAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Utility-Based agent.
     *
     * @param array<string, mixed> $options
     */
    public function createUtilityBasedAgent(array $options = []): UtilityBasedAgent
    {
        return new UtilityBasedAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Model-Based agent.
     *
     * @param array<string, mixed> $options
     */
    public function createModelBasedAgent(array $options = []): ModelBasedAgent
    {
        return new ModelBasedAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Learning agent.
     *
     * @param array<string, mixed> $options
     */
    public function createLearningAgent(array $options = []): LearningAgent
    {
        return new LearningAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create a Maker agent.
     *
     * @param array<string, mixed> $options
     */
    public function createMakerAgent(array $options = []): MakerAgent
    {
        return new MakerAgent(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create an Adaptive Agent Service.
     *
     * @param array<string, mixed> $options
     */
    public function createAdaptiveAgentService(array $options = []): AdaptiveAgentService
    {
        return new AdaptiveAgentService(
            $this->client,
            $this->mergeDefaultOptions($options)
        );
    }

    /**
     * Create an agent by type name.
     *
     * @param string $type Agent type ('react', 'cot', 'tot', 'rag', 'autonomous', etc.)
     * @param array<string, mixed> $options
     * @throws \InvalidArgumentException If type is unknown
     */
    public function create(string $type, array $options = []): AgentInterface
    {
        return match (strtolower($type)) {
            'react' => $this->createReactAgent($options),
            'cot', 'chain-of-thought' => $this->createChainOfThoughtAgent($options),
            'tot', 'tree-of-thoughts' => $this->createTreeOfThoughtsAgent($options),
            'plan-execute', 'plan' => $this->createPlanExecuteAgent($options),
            'reflection', 'reflect' => $this->createReflectionAgent($options),
            'worker' => $this->createWorkerAgent($options),
            'hierarchical', 'master' => $this->createHierarchicalAgent($options),
            'rag' => $this->createRAGAgent($options),
            'autonomous' => $this->createAutonomousAgent($options),
            'dialog', 'conversation' => $this->createDialogAgent($options),
            'alert' => $this->createAlertAgent($options),
            'monitoring', 'monitor' => $this->createMonitoringAgent($options),
            'coordinator' => $this->createCoordinatorAgent($options),
            'scheduler' => $this->createSchedulerAgent($options),
            'task-prioritization', 'prioritization' => $this->createTaskPrioritizationAgent($options),
            'intent-classifier', 'intent' => $this->createIntentClassifierAgent($options),
            'memory-manager', 'memory' => $this->createMemoryManagerAgent($options),
            'solution-discriminator', 'discriminator' => $this->createSolutionDiscriminatorAgent($options),
            'reflex' => $this->createReflexAgent($options),
            'environment-simulator', 'simulator' => $this->createEnvironmentSimulatorAgent($options),
            'utility-based', 'utility' => $this->createUtilityBasedAgent($options),
            'model-based', 'model' => $this->createModelBasedAgent($options),
            'learning' => $this->createLearningAgent($options),
            'maker' => $this->createMakerAgent($options),
            'adaptive' => $this->createAdaptiveAgentService($options),
            default => throw new \InvalidArgumentException("Unknown agent type: {$type}"),
        };
    }

    /**
     * Merge default options with provided options.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function mergeDefaultOptions(array $options): array
    {
        if ($this->logger !== null && ! isset($options['logger'])) {
            $options['logger'] = $this->logger;
        }

        return $options;
    }
}
