<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Factory;

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
use ClaudeAgents\Factory\AgentFactory;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AgentFactoryTest extends TestCase
{
    private ClaudePhp $client;
    private AgentFactory $factory;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
        $this->factory = new AgentFactory($this->client);
    }

    public function test_create_react_agent(): void
    {
        $agent = $this->factory->createReactAgent(['name' => 'test']);

        $this->assertInstanceOf(ReactAgent::class, $agent);
        $this->assertSame('test', $agent->getName());
    }

    public function test_create_chain_of_thought_agent(): void
    {
        $agent = $this->factory->createChainOfThoughtAgent();

        $this->assertInstanceOf(ChainOfThoughtAgent::class, $agent);
    }

    public function test_create_tree_of_thoughts_agent(): void
    {
        $agent = $this->factory->createTreeOfThoughtsAgent();

        $this->assertInstanceOf(TreeOfThoughtsAgent::class, $agent);
    }

    public function test_create_plan_execute_agent(): void
    {
        $agent = $this->factory->createPlanExecuteAgent();

        $this->assertInstanceOf(PlanExecuteAgent::class, $agent);
    }

    public function test_create_reflection_agent(): void
    {
        $agent = $this->factory->createReflectionAgent();

        $this->assertInstanceOf(ReflectionAgent::class, $agent);
    }

    public function test_create_worker_agent(): void
    {
        $agent = $this->factory->createWorkerAgent();

        $this->assertInstanceOf(WorkerAgent::class, $agent);
    }

    public function test_create_hierarchical_agent(): void
    {
        $agent = $this->factory->createHierarchicalAgent();

        $this->assertInstanceOf(HierarchicalAgent::class, $agent);
    }

    public function test_create_by_type_react(): void
    {
        $agent = $this->factory->create('react');

        $this->assertInstanceOf(ReactAgent::class, $agent);
    }

    public function test_create_by_type_cot(): void
    {
        $agent = $this->factory->create('cot');

        $this->assertInstanceOf(ChainOfThoughtAgent::class, $agent);
    }

    public function test_create_by_type_chain_of_thought(): void
    {
        $agent = $this->factory->create('chain-of-thought');

        $this->assertInstanceOf(ChainOfThoughtAgent::class, $agent);
    }

    public function test_create_by_type_worker(): void
    {
        $agent = $this->factory->create('worker');

        $this->assertInstanceOf(WorkerAgent::class, $agent);
    }

    public function test_create_with_unknown_type_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown agent type: unknown');

        $this->factory->create('unknown');
    }

    public function test_factory_injects_logger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $factory = new AgentFactory($this->client, $logger);

        $agent = $factory->createReactAgent();

        // Agent should be created with the logger
        $this->assertInstanceOf(ReactAgent::class, $agent);
    }

    public function test_create_rag_agent(): void
    {
        $agent = $this->factory->createRAGAgent();

        $this->assertInstanceOf(RAGAgent::class, $agent);
    }

    public function test_create_autonomous_agent(): void
    {
        $agent = $this->factory->createAutonomousAgent(['goal' => 'Test goal']);

        $this->assertInstanceOf(AutonomousAgent::class, $agent);
    }

    public function test_create_dialog_agent(): void
    {
        $agent = $this->factory->createDialogAgent();

        $this->assertInstanceOf(DialogAgent::class, $agent);
    }

    public function test_create_alert_agent(): void
    {
        $agent = $this->factory->createAlertAgent();

        $this->assertInstanceOf(AlertAgent::class, $agent);
    }

    public function test_create_monitoring_agent(): void
    {
        $agent = $this->factory->createMonitoringAgent();

        $this->assertInstanceOf(MonitoringAgent::class, $agent);
    }

    public function test_create_coordinator_agent(): void
    {
        $agent = $this->factory->createCoordinatorAgent();

        $this->assertInstanceOf(CoordinatorAgent::class, $agent);
    }

    public function test_create_scheduler_agent(): void
    {
        $agent = $this->factory->createSchedulerAgent();

        $this->assertInstanceOf(SchedulerAgent::class, $agent);
    }

    public function test_create_task_prioritization_agent(): void
    {
        $agent = $this->factory->createTaskPrioritizationAgent();

        $this->assertInstanceOf(TaskPrioritizationAgent::class, $agent);
    }

    public function test_create_intent_classifier_agent(): void
    {
        $agent = $this->factory->createIntentClassifierAgent();

        $this->assertInstanceOf(IntentClassifierAgent::class, $agent);
    }

    public function test_create_memory_manager_agent(): void
    {
        $agent = $this->factory->createMemoryManagerAgent();

        $this->assertInstanceOf(MemoryManagerAgent::class, $agent);
    }

    public function test_create_solution_discriminator_agent(): void
    {
        $agent = $this->factory->createSolutionDiscriminatorAgent();

        $this->assertInstanceOf(SolutionDiscriminatorAgent::class, $agent);
    }

    public function test_create_reflex_agent(): void
    {
        $agent = $this->factory->createReflexAgent();

        $this->assertInstanceOf(ReflexAgent::class, $agent);
    }

    public function test_create_environment_simulator_agent(): void
    {
        $agent = $this->factory->createEnvironmentSimulatorAgent();

        $this->assertInstanceOf(EnvironmentSimulatorAgent::class, $agent);
    }

    public function test_create_utility_based_agent(): void
    {
        $agent = $this->factory->createUtilityBasedAgent();

        $this->assertInstanceOf(UtilityBasedAgent::class, $agent);
    }

    public function test_create_model_based_agent(): void
    {
        $agent = $this->factory->createModelBasedAgent();

        $this->assertInstanceOf(ModelBasedAgent::class, $agent);
    }

    public function test_create_learning_agent(): void
    {
        $agent = $this->factory->createLearningAgent();

        $this->assertInstanceOf(LearningAgent::class, $agent);
    }

    public function test_create_maker_agent(): void
    {
        $agent = $this->factory->createMakerAgent();

        $this->assertInstanceOf(MakerAgent::class, $agent);
    }

    public function test_create_adaptive_agent_service(): void
    {
        $agent = $this->factory->createAdaptiveAgentService();

        $this->assertInstanceOf(AdaptiveAgentService::class, $agent);
    }

    public function test_create_by_type_rag(): void
    {
        $agent = $this->factory->create('rag');

        $this->assertInstanceOf(RAGAgent::class, $agent);
    }

    public function test_create_by_type_autonomous(): void
    {
        $agent = $this->factory->create('autonomous', ['goal' => 'Test goal']);

        $this->assertInstanceOf(AutonomousAgent::class, $agent);
    }

    public function test_create_by_type_dialog(): void
    {
        $agent = $this->factory->create('dialog');

        $this->assertInstanceOf(DialogAgent::class, $agent);
    }

    public function test_create_by_type_conversation(): void
    {
        $agent = $this->factory->create('conversation');

        $this->assertInstanceOf(DialogAgent::class, $agent);
    }

    public function test_create_by_type_alert(): void
    {
        $agent = $this->factory->create('alert');

        $this->assertInstanceOf(AlertAgent::class, $agent);
    }

    public function test_create_by_type_monitoring(): void
    {
        $agent = $this->factory->create('monitoring');

        $this->assertInstanceOf(MonitoringAgent::class, $agent);
    }

    public function test_create_by_type_coordinator(): void
    {
        $agent = $this->factory->create('coordinator');

        $this->assertInstanceOf(CoordinatorAgent::class, $agent);
    }

    public function test_create_by_type_scheduler(): void
    {
        $agent = $this->factory->create('scheduler');

        $this->assertInstanceOf(SchedulerAgent::class, $agent);
    }

    public function test_create_by_type_task_prioritization(): void
    {
        $agent = $this->factory->create('task-prioritization');

        $this->assertInstanceOf(TaskPrioritizationAgent::class, $agent);
    }

    public function test_create_by_type_intent_classifier(): void
    {
        $agent = $this->factory->create('intent-classifier');

        $this->assertInstanceOf(IntentClassifierAgent::class, $agent);
    }

    public function test_create_by_type_memory_manager(): void
    {
        $agent = $this->factory->create('memory-manager');

        $this->assertInstanceOf(MemoryManagerAgent::class, $agent);
    }

    public function test_create_by_type_solution_discriminator(): void
    {
        $agent = $this->factory->create('solution-discriminator');

        $this->assertInstanceOf(SolutionDiscriminatorAgent::class, $agent);
    }

    public function test_create_by_type_reflex(): void
    {
        $agent = $this->factory->create('reflex');

        $this->assertInstanceOf(ReflexAgent::class, $agent);
    }

    public function test_create_by_type_environment_simulator(): void
    {
        $agent = $this->factory->create('environment-simulator');

        $this->assertInstanceOf(EnvironmentSimulatorAgent::class, $agent);
    }

    public function test_create_by_type_utility_based(): void
    {
        $agent = $this->factory->create('utility-based');

        $this->assertInstanceOf(UtilityBasedAgent::class, $agent);
    }

    public function test_create_by_type_model_based(): void
    {
        $agent = $this->factory->create('model-based');

        $this->assertInstanceOf(ModelBasedAgent::class, $agent);
    }

    public function test_create_by_type_learning(): void
    {
        $agent = $this->factory->create('learning');

        $this->assertInstanceOf(LearningAgent::class, $agent);
    }

    public function test_create_by_type_maker(): void
    {
        $agent = $this->factory->create('maker');

        $this->assertInstanceOf(MakerAgent::class, $agent);
    }

    public function test_create_by_type_adaptive(): void
    {
        $agent = $this->factory->create('adaptive');

        $this->assertInstanceOf(AdaptiveAgentService::class, $agent);
    }
}
