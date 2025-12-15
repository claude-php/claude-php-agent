<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\Agents\TaskPrioritizationAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TaskPrioritizationAgentTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new TaskPrioritizationAgent($client);

        $this->assertEquals('task_prioritization_agent', $agent->getName());
    }

    public function testConstructorWithCustomOptions(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new TaskPrioritizationAgent($client, [
            'name' => 'custom_task_agent',
            'goal' => 'Custom goal',
        ]);

        $this->assertEquals('custom_task_agent', $agent->getName());
    }

    public function testRunGeneratesAndExecutesTasks(): void
    {
        // Mock response for initial task generation
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $taskGenerationResponse = new Message(
            id: 'msg_tasks',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    ['description' => 'Task 1', 'priority' => 10, 'estimated_effort' => 3],
                    ['description' => 'Task 2', 'priority' => 8, 'estimated_effort' => 2],
                    ['description' => 'Task 3', 'priority' => 6, 'estimated_effort' => 4],
                ])],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $taskExecutionResponse = new Message(
            id: 'msg_exec',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Task completed successfully'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $additionalTasksResponse = new Message(
            id: 'msg_additional',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '[]'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $taskGenerationResponse,
            $taskExecutionResponse,
            $additionalTasksResponse,
            $taskExecutionResponse,
            $additionalTasksResponse,
            $taskExecutionResponse,
            $additionalTasksResponse
        );

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'goal' => 'Test goal',
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Complete project planning');

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getAnswer());
        $this->assertArrayHasKey('goal', $result->getMetadata());
        $this->assertArrayHasKey('tasks_completed', $result->getMetadata());
        $this->assertArrayHasKey('tasks_remaining', $result->getMetadata());
        $this->assertEquals('Complete project planning', $result->getMetadata()['goal']);
    }

    public function testRunHandlesEmptyTaskGeneration(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $taskGenerationResponse = new Message(
            id: 'msg_tasks',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '[]'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($taskGenerationResponse);

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test task');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(0, $result->getMetadata()['tasks_completed']);
    }

    public function testRunHandlesInvalidJsonInTaskGeneration(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $taskGenerationResponse = new Message(
            id: 'msg_tasks',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'invalid json'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($taskGenerationResponse);

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test task');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(0, $result->getMetadata()['tasks_completed']);
    }

    public function testRunPrioritizesTasksByPriority(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        // Tasks with different priorities (should be executed in priority order)
        $taskGenerationResponse = new Message(
            id: 'msg_tasks',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    ['description' => 'Low priority task', 'priority' => 3, 'estimated_effort' => 2],
                    ['description' => 'High priority task', 'priority' => 10, 'estimated_effort' => 2],
                    ['description' => 'Medium priority task', 'priority' => 6, 'estimated_effort' => 2],
                ])],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $taskExecutionResponse = new Message(
            id: 'msg_exec',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Task executed'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $noAdditionalTasksResponse = new Message(
            id: 'msg_additional',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '[]'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $taskGenerationResponse,
            $taskExecutionResponse,
            $noAdditionalTasksResponse,
            $taskExecutionResponse,
            $noAdditionalTasksResponse,
            $taskExecutionResponse,
            $noAdditionalTasksResponse
        );

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test prioritization');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(3, $result->getMetadata()['tasks_completed']);
    }

    public function testRunGeneratesAdditionalTasks(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        // Initial tasks
        $taskGenerationResponse = new Message(
            id: 'msg_tasks',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    ['description' => 'Task 1', 'priority' => 10, 'estimated_effort' => 2],
                ])],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $taskExecutionResponse = new Message(
            id: 'msg_exec',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Task completed'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        // Additional task generated
        $additionalTasksResponse = new Message(
            id: 'msg_additional',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    ['description' => 'Follow-up task', 'priority' => 8, 'estimated_effort' => 1],
                ])],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $noMoreTasksResponse = new Message(
            id: 'msg_no_more',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '[]'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $taskGenerationResponse,
            $taskExecutionResponse,
            $additionalTasksResponse,
            $taskExecutionResponse,
            $noMoreTasksResponse
        );

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test dynamic tasks');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(2, $result->getMetadata()['tasks_completed']);
    }

    public function testRunHandlesMaxIterations(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        // Generate many tasks to exceed max iterations
        $tasks = [];
        for ($i = 1; $i <= 30; $i++) {
            $tasks[] = ['description' => "Task $i", 'priority' => 10, 'estimated_effort' => 1];
        }

        $taskGenerationResponse = new Message(
            id: 'msg_tasks',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode($tasks)],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $taskExecutionResponse = new Message(
            id: 'msg_exec',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Task executed'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $noAdditionalTasksResponse = new Message(
            id: 'msg_additional',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '[]'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->will(
            $this->onConsecutiveCalls(
                $taskGenerationResponse,
                ...\array_fill(0, 40, $taskExecutionResponse),
                ...\array_fill(0, 40, $noAdditionalTasksResponse)
            )
        );

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test max iterations');

        $this->assertTrue($result->isSuccess());
        // Should stop at maxIterations (20)
        $this->assertEquals(20, $result->getIterations());
        $this->assertEquals(20, $result->getMetadata()['tasks_completed']);
        // Should have 10 tasks remaining (30 - 20)
        $this->assertEquals(10, $result->getMetadata()['tasks_remaining']);
    }

    public function testRunHandlesApiError(): void
    {
        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test error');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('API Error', $result->getError());
    }

    public function testRunHandlesErrorInTaskExecution(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $taskGenerationResponse = new Message(
            id: 'msg_tasks',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    ['description' => 'Task 1', 'priority' => 10, 'estimated_effort' => 2],
                ])],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $taskGenerationResponse,
            $this->throwException(new \Exception('Execution error'))
        );

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test execution error');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Execution error', $result->getError());
    }

    public function testRunHandlesErrorInAdditionalTaskGeneration(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $taskGenerationResponse = new Message(
            id: 'msg_tasks',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    ['description' => 'Task 1', 'priority' => 10, 'estimated_effort' => 2],
                ])],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $taskExecutionResponse = new Message(
            id: 'msg_exec',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Task completed'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $taskGenerationResponse,
            $taskExecutionResponse,
            $this->throwException(new \Exception('Additional task error'))
        );

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test additional task error');

        // Should continue despite error in additional task generation
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $result->getMetadata()['tasks_completed']);
    }

    public function testExtractTextContentHandlesMultipleBlocks(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $taskGenerationResponse = new Message(
            id: 'msg_tasks',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    ['description' => 'Task 1', 'priority' => 10, 'estimated_effort' => 2],
                ])],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $taskExecutionResponse = new Message(
            id: 'msg_exec',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'First part of result.'],
                ['type' => 'text', 'text' => ' Second part of result.'],
                ['type' => 'other', 'data' => 'ignored'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $noAdditionalTasksResponse = new Message(
            id: 'msg_additional',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '[]'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $taskGenerationResponse,
            $taskExecutionResponse,
            $noAdditionalTasksResponse
        );

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test multiple content blocks');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('First part', $result->getAnswer());
        $this->assertStringContainsString('Second part', $result->getAnswer());
    }

    public function testRunFormatsResultsCorrectly(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $taskGenerationResponse = new Message(
            id: 'msg_tasks',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    ['description' => 'Task Alpha', 'priority' => 10, 'estimated_effort' => 2],
                    ['description' => 'Task Beta', 'priority' => 9, 'estimated_effort' => 1],
                ])],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $taskExecutionResponse = new Message(
            id: 'msg_exec',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Task result content here'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $noAdditionalTasksResponse = new Message(
            id: 'msg_additional',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '[]'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturnOnConsecutiveCalls(
            $taskGenerationResponse,
            $taskExecutionResponse,
            $noAdditionalTasksResponse,
            $taskExecutionResponse,
            $noAdditionalTasksResponse
        );

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new TaskPrioritizationAgent($client, [
            'goal' => 'My specific goal',
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Format test');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Task Prioritization Results', $result->getAnswer());
        // The goal gets set by run() method, so it should be 'Format test'
        $this->assertStringContainsString('Goal: Format test', $result->getAnswer());
        $this->assertStringContainsString('Completed Tasks: 2', $result->getAnswer());
    }
}
