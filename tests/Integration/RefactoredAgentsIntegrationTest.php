<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration;

use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Agents\HierarchicalAgent;
use ClaudeAgents\Agents\WorkerAgent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for refactored agents.
 *
 * These tests verify that refactored agents maintain their expected behavior.
 * They use mock responses to avoid hitting the actual API.
 */
class RefactoredAgentsIntegrationTest extends TestCase
{
    private ClaudePhp $client;

    protected function setUp(): void
    {
        // Create a mock client to avoid real API calls
        $this->client = $this->createMock(ClaudePhp::class);
    }

    public function test_chain_of_thought_agent_initialization(): void
    {
        $agent = new ChainOfThoughtAgent($this->client, [
            'name' => 'test_cot',
            'mode' => 'zero_shot',
        ]);

        $this->assertSame('test_cot', $agent->getName());
    }

    public function test_worker_agent_initialization(): void
    {
        $agent = new WorkerAgent($this->client, [
            'name' => 'test_worker',
            'specialty' => 'testing',
        ]);

        $this->assertSame('test_worker', $agent->getName());
        $this->assertSame('testing', $agent->getSpecialty());
    }

    public function test_hierarchical_agent_initialization(): void
    {
        $agent = new HierarchicalAgent($this->client, [
            'name' => 'test_master',
        ]);

        $this->assertSame('test_master', $agent->getName());
    }

    public function test_hierarchical_agent_with_workers(): void
    {
        $masterAgent = new HierarchicalAgent($this->client, [
            'name' => 'master',
        ]);

        $worker1 = new WorkerAgent($this->client, [
            'name' => 'worker1',
            'specialty' => 'coding',
        ]);

        $worker2 = new WorkerAgent($this->client, [
            'name' => 'worker2',
            'specialty' => 'testing',
        ]);

        $masterAgent->registerWorker('worker1', $worker1);
        $masterAgent->registerWorker('worker2', $worker2);

        $this->assertSame(['worker1', 'worker2'], $masterAgent->getWorkerNames());
        $this->assertSame($worker1, $masterAgent->getWorker('worker1'));
        $this->assertSame($worker2, $masterAgent->getWorker('worker2'));
    }

    public function test_agents_use_default_configuration(): void
    {
        $agent = new ChainOfThoughtAgent($this->client);

        $this->assertSame('cot_agent', $agent->getName());
    }

    public function test_agents_accept_custom_logger(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $agent = new WorkerAgent($this->client, [
            'logger' => $logger,
        ]);

        // Agent should be created successfully without errors
        $this->assertInstanceOf(WorkerAgent::class, $agent);
    }

    public function test_multiple_agents_can_coexist(): void
    {
        $agent1 = new ChainOfThoughtAgent($this->client, ['name' => 'cot1']);
        $agent2 = new WorkerAgent($this->client, ['name' => 'worker1']);
        $agent3 = new HierarchicalAgent($this->client, ['name' => 'master1']);

        $this->assertSame('cot1', $agent1->getName());
        $this->assertSame('worker1', $agent2->getName());
        $this->assertSame('master1', $agent3->getName());
    }
}
