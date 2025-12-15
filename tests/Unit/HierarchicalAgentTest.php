<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\HierarchicalAgent;
use ClaudeAgents\Agents\WorkerAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HierarchicalAgentTest extends TestCase
{
    private ClaudePhp $client;
    private Messages $messages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClaudePhp::class);
        $this->messages = $this->createMock(Messages::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockLlmResponse(string $text, int $inputTokens = 100, int $outputTokens = 50): Message
    {
        $usage = new Usage(
            input_tokens: $inputTokens,
            output_tokens: $outputTokens
        );

        return new Message(
            id: 'msg_test_' . uniqid(),
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => $text],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );
    }

    public function testConstructorWithDefaultOptions(): void
    {
        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new HierarchicalAgent($this->client);
        $this->assertEquals('master_agent', $agent->getName());
    }

    public function testConstructorWithCustomOptions(): void
    {
        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new HierarchicalAgent($this->client, [
            'name' => 'custom_master',
            'model' => 'claude-opus-4',
            'max_tokens' => 4096,
        ]);

        $this->assertEquals('custom_master', $agent->getName());
    }

    public function testRegisterWorker(): void
    {
        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('debug')->andReturn();

        $agent = new HierarchicalAgent($this->client, ['logger' => $mockLogger]);

        $worker = Mockery::mock(WorkerAgent::class);
        $worker->shouldReceive('getSpecialty')->andReturn('test specialty');

        $result = $agent->registerWorker('test_worker', $worker);

        $this->assertSame($agent, $result);
        $this->assertSame($worker, $agent->getWorker('test_worker'));
        $this->assertContains('test_worker', $agent->getWorkerNames());
    }

    public function testGetWorkerReturnsNullForUnregistered(): void
    {
        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new HierarchicalAgent($this->client);
        $this->assertNull($agent->getWorker('nonexistent'));
    }

    public function testGetWorkerNames(): void
    {
        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('debug')->andReturn();

        $agent = new HierarchicalAgent($this->client, ['logger' => $mockLogger]);

        $worker1 = Mockery::mock(WorkerAgent::class);
        $worker1->shouldReceive('getSpecialty')->andReturn('specialty 1');

        $worker2 = Mockery::mock(WorkerAgent::class);
        $worker2->shouldReceive('getSpecialty')->andReturn('specialty 2');

        $agent->registerWorker('worker1', $worker1);
        $agent->registerWorker('worker2', $worker2);

        $names = $agent->getWorkerNames();

        $this->assertCount(2, $names);
        $this->assertContains('worker1', $names);
        $this->assertContains('worker2', $names);
    }

    public function testRunSuccessfulExecution(): void
    {
        $decompositionResponse = $this->mockLlmResponse(
            "Agent: math_worker\nSubtask: Calculate sum\nAgent: writing_worker\nSubtask: Write summary",
            100,
            50
        );

        $synthesisResponse = $this->mockLlmResponse(
            'Final synthesized answer',
            150,
            75
        );

        $this->messages->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($decompositionResponse, $synthesisResponse);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('info')->andReturn();
        $mockLogger->shouldReceive('debug')->andReturn();
        $mockLogger->shouldReceive('error')->andReturn();

        $agent = new HierarchicalAgent($this->client, ['logger' => $mockLogger]);

        // Create mock workers
        $mathWorker = Mockery::mock(WorkerAgent::class);
        $mathWorker->shouldReceive('run')
            ->once()
            ->andReturn(AgentResult::success(
                answer: 'Math result: 42',
                messages: [],
                iterations: 1,
                metadata: ['token_usage' => ['input' => 50, 'output' => 25, 'total' => 75]]
            ));
        $mathWorker->shouldReceive('getSpecialty')->andReturn('mathematics');

        $writingWorker = Mockery::mock(WorkerAgent::class);
        $writingWorker->shouldReceive('run')
            ->once()
            ->andReturn(AgentResult::success(
                answer: 'Written summary',
                messages: [],
                iterations: 1,
                metadata: ['token_usage' => ['input' => 60, 'output' => 30, 'total' => 90]]
            ));
        $writingWorker->shouldReceive('getSpecialty')->andReturn('writing');

        $agent->registerWorker('math_worker', $mathWorker);
        $agent->registerWorker('writing_worker', $writingWorker);

        $result = $agent->run('Complex task requiring multiple agents');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Final synthesized answer', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertEquals(4, $result->getIterations());
        $this->assertEquals(2, $metadata['subtasks']);
        $this->assertArrayHasKey('token_usage', $metadata);
        $this->assertArrayHasKey('workers_used', $metadata);
        $this->assertArrayHasKey('duration_seconds', $metadata);
    }

    public function testRunWithNoSubtasks(): void
    {
        $decompositionResponse = $this->mockLlmResponse('No valid subtasks', 100, 50);

        $this->messages->expects($this->once())
            ->method('create')
            ->willReturn($decompositionResponse);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('info')->andReturn();
        $mockLogger->shouldReceive('debug')->andReturn();
        $mockLogger->shouldReceive('error')->andReturn();

        $agent = new HierarchicalAgent($this->client, ['logger' => $mockLogger]);

        $result = $agent->run('Task that cannot be decomposed');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Failed to decompose', $result->getError());
    }

    public function testRunWithMissingWorker(): void
    {
        $decompositionResponse = $this->mockLlmResponse(
            "Agent: nonexistent_worker\nSubtask: Do something",
            100,
            50
        );

        $synthesisResponse = $this->mockLlmResponse(
            'Synthesized with missing worker',
            150,
            75
        );

        $this->messages->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($decompositionResponse, $synthesisResponse);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('info')->andReturn();
        $mockLogger->shouldReceive('debug')->andReturn();
        $mockLogger->shouldReceive('error')->andReturn();

        $agent = new HierarchicalAgent($this->client, ['logger' => $mockLogger]);

        // Register a fallback worker
        $fallbackWorker = Mockery::mock(WorkerAgent::class);
        $fallbackWorker->shouldReceive('run')
            ->once()
            ->andReturn(AgentResult::success(
                answer: 'Fallback result',
                messages: [],
                iterations: 1,
                metadata: ['token_usage' => ['input' => 50, 'output' => 25, 'total' => 75]]
            ));
        $fallbackWorker->shouldReceive('getSpecialty')->andReturn('general tasks');

        $agent->registerWorker('fallback_worker', $fallbackWorker);

        $result = $agent->run('Task with missing worker');

        $this->assertTrue($result->isSuccess());
    }

    public function testRunWithNoWorkersRegistered(): void
    {
        $decompositionResponse = $this->mockLlmResponse(
            "Agent: any_worker\nSubtask: Do something",
            100,
            50
        );

        $synthesisResponse = $this->mockLlmResponse(
            'Synthesized without workers',
            150,
            75
        );

        $this->messages->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($decompositionResponse, $synthesisResponse);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('info')->andReturn();
        $mockLogger->shouldReceive('debug')->andReturn();
        $mockLogger->shouldReceive('error')->andReturn();

        $agent = new HierarchicalAgent($this->client, ['logger' => $mockLogger]);

        $result = $agent->run('Task with no workers');

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('workers_used', $metadata);
    }

    public function testRunWithApiException(): void
    {
        // First call (decomposition) throws exception, creates fallback subtask with no worker
        // Second call (synthesis) also throws exception but is caught and returns error string
        $this->messages->expects($this->exactly(2))
            ->method('create')
            ->willThrowException(new \Exception('API Error'));

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('info')->andReturn();
        $mockLogger->shouldReceive('debug')->andReturn();
        $mockLogger->shouldReceive('error')->andReturn();

        $agent = new HierarchicalAgent($this->client, ['logger' => $mockLogger]);

        $result = $agent->run('Task that fails');

        // When decomposition fails, it creates fallback subtask, and synthesis also fails
        // The synthesis error is caught and returned as the answer, so overall it succeeds
        // with an error message in the answer
        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Synthesis error', $result->getAnswer());
        $this->assertStringContainsString('API Error', $result->getAnswer());
    }

    public function testGetName(): void
    {
        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new HierarchicalAgent($this->client, ['name' => 'test_master']);
        $this->assertEquals('test_master', $agent->getName());
    }

    public function testTokenUsageAccumulation(): void
    {
        $decompositionResponse = $this->mockLlmResponse(
            "Agent: worker1\nSubtask: Task 1",
            100,
            50
        );

        $synthesisResponse = $this->mockLlmResponse(
            'Final answer',
            200,
            100
        );

        $this->messages->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($decompositionResponse, $synthesisResponse);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('info')->andReturn();
        $mockLogger->shouldReceive('debug')->andReturn();
        $mockLogger->shouldReceive('error')->andReturn();

        $agent = new HierarchicalAgent($this->client, ['logger' => $mockLogger]);

        $worker = Mockery::mock(WorkerAgent::class);
        $worker->shouldReceive('run')
            ->once()
            ->andReturn(AgentResult::success(
                answer: 'Worker answer',
                messages: [],
                iterations: 1,
                metadata: ['token_usage' => ['input' => 75, 'output' => 25, 'total' => 100]]
            ));
        $worker->shouldReceive('getSpecialty')->andReturn('specialty');

        $agent->registerWorker('worker1', $worker);

        $result = $agent->run('Test task');

        $this->assertTrue($result->isSuccess());

        $usage = $result->getTokenUsage();
        // 100 + 75 + 200 = 375 input
        // 50 + 25 + 100 = 175 output
        $this->assertEquals(375, $usage['input']);
        $this->assertEquals(175, $usage['output']);
        $this->assertEquals(550, $usage['total']);
    }

    public function testMultipleWorkersExecution(): void
    {
        $decompositionResponse = $this->mockLlmResponse(
            "Agent: worker1\nSubtask: Task 1\nAgent: worker2\nSubtask: Task 2\nAgent: worker3\nSubtask: Task 3",
            100,
            50
        );

        $synthesisResponse = $this->mockLlmResponse(
            'Combined result from all workers',
            200,
            100
        );

        $this->messages->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($decompositionResponse, $synthesisResponse);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('info')->andReturn();
        $mockLogger->shouldReceive('debug')->andReturn();
        $mockLogger->shouldReceive('error')->andReturn();

        $agent = new HierarchicalAgent($this->client, ['logger' => $mockLogger]);

        // Create 3 workers
        foreach (['worker1', 'worker2', 'worker3'] as $workerName) {
            $worker = Mockery::mock(WorkerAgent::class);
            $worker->shouldReceive('run')
                ->once()
                ->andReturn(AgentResult::success(
                    answer: "Result from {$workerName}",
                    messages: [],
                    iterations: 1,
                    metadata: ['token_usage' => ['input' => 50, 'output' => 25, 'total' => 75]]
                ));
            $worker->shouldReceive('getSpecialty')->andReturn("specialty {$workerName}");

            $agent->registerWorker($workerName, $worker);
        }

        $result = $agent->run('Complex multi-worker task');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        $this->assertEquals(3, $metadata['subtasks']);
        $this->assertCount(3, $metadata['workers_used']);
        $this->assertEquals(5, $result->getIterations());
    }
}
