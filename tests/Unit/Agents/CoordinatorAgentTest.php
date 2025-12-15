<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\CoordinatorAgent;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoordinatorAgentTest extends TestCase
{
    private ClaudePhp $mockClient;
    private CoordinatorAgent $coordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = $this->createMock(ClaudePhp::class);
        $this->coordinator = new CoordinatorAgent($this->mockClient, [
            'name' => 'test_coordinator',
            'logger' => new NullLogger(),
        ]);
    }

    public function testConstructorWithDefaults(): void
    {
        $coordinator = new CoordinatorAgent($this->mockClient);

        $this->assertEquals('coordinator_agent', $coordinator->getName());
    }

    public function testConstructorWithCustomName(): void
    {
        $coordinator = new CoordinatorAgent($this->mockClient, ['name' => 'custom']);

        $this->assertEquals('custom', $coordinator->getName());
    }

    public function testRegisterAgent(): void
    {
        $mockAgent = $this->createMock(AgentInterface::class);
        $capabilities = ['coding', 'testing'];

        $this->coordinator->registerAgent('test_agent', $mockAgent, $capabilities);

        $this->assertContains('test_agent', $this->coordinator->getAgentIds());
        $this->assertEquals($capabilities, $this->coordinator->getAgentCapabilities('test_agent'));
    }

    public function testRegisterMultipleAgents(): void
    {
        $agent1 = $this->createMock(AgentInterface::class);
        $agent2 = $this->createMock(AgentInterface::class);

        $this->coordinator->registerAgent('agent1', $agent1, ['coding']);
        $this->coordinator->registerAgent('agent2', $agent2, ['testing']);

        $agentIds = $this->coordinator->getAgentIds();

        $this->assertCount(2, $agentIds);
        $this->assertContains('agent1', $agentIds);
        $this->assertContains('agent2', $agentIds);
    }

    public function testGetAgentCapabilities(): void
    {
        $mockAgent = $this->createMock(AgentInterface::class);
        $capabilities = ['research', 'analysis', 'writing'];

        $this->coordinator->registerAgent('researcher', $mockAgent, $capabilities);

        $this->assertEquals($capabilities, $this->coordinator->getAgentCapabilities('researcher'));
    }

    public function testGetAgentCapabilitiesForNonExistentAgent(): void
    {
        $this->assertEquals([], $this->coordinator->getAgentCapabilities('nonexistent'));
    }

    public function testRunWithNoRegisteredAgents(): void
    {
        // Mock the API response for requirement analysis
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '["coding"]']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 10)
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $this->mockClient->method('messages')->willReturn($mockMessages);

        $result = $this->coordinator->run('Write some code');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No suitable agent found', $result->getError());
    }

    public function testRunSuccessfulDelegation(): void
    {
        // Register a capable agent
        $mockAgent = $this->createMock(AgentInterface::class);
        $mockAgent->method('run')
            ->willReturn(AgentResult::success(
                answer: 'Task completed by agent',
                messages: [],
                iterations: 1
            ));

        $this->coordinator->registerAgent('coder', $mockAgent, ['coding', 'implementation']);

        // Mock the API response for requirement analysis
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '["coding"]']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 10)
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $this->mockClient->method('messages')->willReturn($mockMessages);

        $result = $this->coordinator->run('Write a function');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Task completed by agent', $result->getAnswer());
        $this->assertEquals('coder', $result->getMetadata()['delegated_to']);
    }

    public function testRunWithLoadBalancing(): void
    {
        // Register two agents with same capabilities
        $agent1 = $this->createMock(AgentInterface::class);
        $agent1->method('run')
            ->willReturn(AgentResult::success(answer: 'Agent 1 result', messages: [], iterations: 1));

        $agent2 = $this->createMock(AgentInterface::class);
        $agent2->method('run')
            ->willReturn(AgentResult::success(answer: 'Agent 2 result', messages: [], iterations: 1));

        $this->coordinator->registerAgent('agent1', $agent1, ['coding']);
        $this->coordinator->registerAgent('agent2', $agent2, ['coding']);

        // Mock the API response
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '["coding"]']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 10)
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $this->mockClient->method('messages')->willReturn($mockMessages);

        // First task should go to agent with lower workload
        $result1 = $this->coordinator->run('Task 1');
        $firstAgent = $result1->getMetadata()['delegated_to'];

        // Second task should go to the other agent (load balancing)
        $result2 = $this->coordinator->run('Task 2');
        $secondAgent = $result2->getMetadata()['delegated_to'];

        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());

        // Both agents should have been used
        $workload = $this->coordinator->getWorkload();
        $this->assertGreaterThan(0, $workload['agent1']);
        $this->assertGreaterThan(0, $workload['agent2']);
    }

    public function testRunSelectsAgentWithBestCapabilityMatch(): void
    {
        // Register agents with different capabilities
        $coderAgent = $this->createMock(AgentInterface::class);
        $coderAgent->method('run')
            ->willReturn(AgentResult::success(answer: 'Code written', messages: [], iterations: 1));

        $testerAgent = $this->createMock(AgentInterface::class);

        $this->coordinator->registerAgent('coder', $coderAgent, ['coding', 'implementation']);
        $this->coordinator->registerAgent('tester', $testerAgent, ['testing', 'quality assurance']);

        // Mock API to return coding requirement
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '["coding", "implementation"]']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 10)
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $this->mockClient->method('messages')->willReturn($mockMessages);

        $result = $this->coordinator->run('Write a function');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('coder', $result->getMetadata()['delegated_to']);
    }

    public function testGetWorkload(): void
    {
        $agent1 = $this->createMock(AgentInterface::class);
        $agent1->method('run')
            ->willReturn(AgentResult::success(answer: 'Done', messages: [], iterations: 1));

        $this->coordinator->registerAgent('agent1', $agent1, ['coding']);

        $initialWorkload = $this->coordinator->getWorkload();
        $this->assertEquals(0, $initialWorkload['agent1']);

        // Mock API response
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '["coding"]']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 10)
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $this->mockClient->method('messages')->willReturn($mockMessages);

        $this->coordinator->run('Task');

        $workload = $this->coordinator->getWorkload();
        $this->assertEquals(1, $workload['agent1']);
    }

    public function testGetPerformance(): void
    {
        $mockAgent = $this->createMock(AgentInterface::class);
        $mockAgent->method('run')
            ->willReturn(AgentResult::success(answer: 'Done', messages: [], iterations: 1));

        $this->coordinator->registerAgent('agent1', $mockAgent, ['coding']);

        $initialPerf = $this->coordinator->getPerformance();
        $this->assertEquals(0, $initialPerf['agent1']['total_tasks']);
        $this->assertEquals(0, $initialPerf['agent1']['successful_tasks']);

        // Mock API response
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '["coding"]']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 10)
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $this->mockClient->method('messages')->willReturn($mockMessages);

        $this->coordinator->run('Task');

        $perf = $this->coordinator->getPerformance();
        $this->assertEquals(1, $perf['agent1']['total_tasks']);
        $this->assertEquals(1, $perf['agent1']['successful_tasks']);
        $this->assertGreaterThan(0, $perf['agent1']['average_duration']);
    }

    public function testRunHandlesApiError(): void
    {
        $mockAgent = $this->createMock(AgentInterface::class);
        $mockAgent->method('run')
            ->willReturn(AgentResult::success(answer: 'Done', messages: [], iterations: 1));

        $this->coordinator->registerAgent('agent1', $mockAgent, ['coding']);

        // Mock API to throw exception during requirement analysis
        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $this->mockClient->method('messages')->willReturn($mockMessages);

        // When API fails, it should fall back to keyword extraction
        // Since task contains "code", it should match agent1 with 'coding' capability
        $result = $this->coordinator->run('Write some code');

        // Should succeed using fallback mechanism
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Done', $result->getAnswer());
    }

    public function testRunWithFallbackKeywordExtraction(): void
    {
        // Test the fallback when API fails but task has obvious keywords
        $mockAgent = $this->createMock(AgentInterface::class);
        $mockAgent->method('run')
            ->willReturn(AgentResult::success(answer: 'Test written', messages: [], iterations: 1));

        $this->coordinator->registerAgent('tester', $mockAgent, ['testing', 'quality assurance']);

        // Mock API to return non-JSON response (triggers fallback)
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'The task needs testing capabilities']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 10)
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $this->mockClient->method('messages')->willReturn($mockMessages);

        $result = $this->coordinator->run('Write tests for the authentication module');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('tester', $result->getMetadata()['delegated_to']);
    }

    public function testMetadataIncludesRequirements(): void
    {
        $mockAgent = $this->createMock(AgentInterface::class);
        $mockAgent->method('run')
            ->willReturn(AgentResult::success(answer: 'Done', messages: [], iterations: 1));

        $this->coordinator->registerAgent('agent', $mockAgent, ['coding']);

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '["coding", "implementation"]']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 10)
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $this->mockClient->method('messages')->willReturn($mockMessages);

        $result = $this->coordinator->run('Task');

        $this->assertArrayHasKey('requirements', $result->getMetadata());
        $this->assertArrayHasKey('workload', $result->getMetadata());
        $this->assertArrayHasKey('duration', $result->getMetadata());
        $this->assertArrayHasKey('agent_performance', $result->getMetadata());
    }

    public function testPerformanceMetricsAccumulate(): void
    {
        $mockAgent = $this->createMock(AgentInterface::class);
        $mockAgent->method('run')
            ->willReturn(AgentResult::success(answer: 'Done', messages: [], iterations: 1));

        $this->coordinator->registerAgent('agent', $mockAgent, ['coding']);

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '["coding"]']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 10)
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $this->mockClient->method('messages')->willReturn($mockMessages);

        // Run multiple tasks
        $this->coordinator->run('Task 1');
        $this->coordinator->run('Task 2');
        $this->coordinator->run('Task 3');

        $perf = $this->coordinator->getPerformance();

        $this->assertEquals(3, $perf['agent']['total_tasks']);
        $this->assertEquals(3, $perf['agent']['successful_tasks']);
    }

    public function testGetName(): void
    {
        $this->assertEquals('test_coordinator', $this->coordinator->getName());
    }
}
