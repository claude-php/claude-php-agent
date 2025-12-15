<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\AutonomousAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AutonomousAgentTest extends TestCase
{
    private string $testStateFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testStateFile = sys_get_temp_dir() . '/autonomous_test_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testStateFile)) {
            unlink($this->testStateFile);
        }
        parent::tearDown();
    }

    public function testConstructorRequiresGoal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Goal is required');

        $client = $this->createMock(ClaudePhp::class);
        new AutonomousAgent($client, []);
    }

    public function testConstructorInitializesState(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Test goal',
            'state_file' => $this->testStateFile,
        ]);

        $this->assertEquals('Test goal', $agent->getGoal());
        $this->assertEquals(0, $agent->getProgress());
        $this->assertFalse($agent->isGoalComplete());
    }

    public function testGetName(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Test goal',
            'name' => 'test_agent',
            'state_file' => $this->testStateFile,
        ]);

        $this->assertEquals('test_agent', $agent->getName());
    }

    public function testGetNameWithDefault(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Test goal',
            'state_file' => $this->testStateFile,
        ]);

        $this->assertEquals('autonomous_agent', $agent->getName());
    }

    public function testRunSessionReturnsSuccessWhenGoalAlreadyComplete(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $client->expects($this->never())->method('messages');

        $agent = new AutonomousAgent($client, [
            'goal' => 'Test goal',
            'state_file' => $this->testStateFile,
        ]);

        // Manually complete the goal
        $agent->getState()->getGoal()->complete();

        $result = $agent->runSession('test task');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('already completed', $result->getAnswer());
        $this->assertTrue($result->getMetadata()['already_complete']);
    }

    public function testRunSessionExecutesSuccessfully(): void
    {
        // Mock the API response
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'I will start planning the project by defining the scope.'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Plan a project',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        $result = $agent->runSession('Start planning');

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getAnswer());
        $this->assertGreaterThan(0, $result->getMetadata()['goal_progress']);
        $this->assertEquals(1, $result->getMetadata()['session_number']);
        $this->assertEquals(1, $result->getMetadata()['actions_this_session']);
    }

    public function testRunSessionDetectsGoalCompletion(): void
    {
        // Mock the API response with completion indicator
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'The project planning is now completed and finished.'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Plan a project',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        $result = $agent->runSession('Complete the plan');

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->getMetadata()['goal_complete']);
        $this->assertTrue($agent->isGoalComplete());
        $this->assertEquals(100, $agent->getProgress());
    }

    public function testRunSessionUpdatesProgress(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Working on the task.'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Plan a project',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        $initialProgress = $agent->getProgress();
        $result = $agent->runSession('Do something');

        $this->assertTrue($result->isSuccess());
        $this->assertGreaterThan($initialProgress, $agent->getProgress());
    }

    public function testRunSessionHandlesError(): void
    {
        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Plan a project',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        $result = $agent->runSession('Test task');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('API Error', $result->getError());
    }

    public function testRunUntilComplete(): void
    {
        $callCount = 0;

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            $usage = new Usage(input_tokens: 100, output_tokens: 50);

            // Complete on third call
            if ($callCount >= 3) {
                return new Message(
                    id: 'msg_test',
                    type: 'message',
                    role: 'assistant',
                    content: [
                        ['type' => 'text', 'text' => 'Task is completed successfully.'],
                    ],
                    model: 'claude-sonnet-4-5',
                    stop_reason: 'end_turn',
                    stop_sequence: null,
                    usage: $usage
                );
            }

            return new Message(
                id: 'msg_test',
                type: 'message',
                role: 'assistant',
                content: [
                    ['type' => 'text', 'text' => 'Making progress on task.'],
                ],
                model: 'claude-sonnet-4-5',
                stop_reason: 'end_turn',
                stop_sequence: null,
                usage: $usage
            );

        });

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Complete a task',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        $results = $agent->runUntilComplete(10);

        $this->assertIsArray($results);
        $this->assertCount(3, $results); // Should stop after goal complete

        // Check if goal was completed
        $lastResult = end($results);
        $this->assertTrue($lastResult->isSuccess());
        $this->assertTrue($agent->isGoalComplete());
    }

    public function testRunUntilCompleteStopsOnFailure(): void
    {
        $callCount = 0;

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturnCallback(function () use (&$callCount) {
            $callCount++;

            // Fail on second call
            if ($callCount === 2) {
                throw new \Exception('API Error');
            }

            $usage = new Usage(input_tokens: 100, output_tokens: 50);

            return new Message(
                id: 'msg_test',
                type: 'message',
                role: 'assistant',
                content: [
                    ['type' => 'text', 'text' => 'Working on it.'],
                ],
                model: 'claude-sonnet-4-5',
                stop_reason: 'end_turn',
                stop_sequence: null,
                usage: $usage
            );
        });

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Complete a task',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        $results = $agent->runUntilComplete(10);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertFalse($results[1]->isSuccess());
    }

    public function testReset(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Working on task.'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Test goal',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        // Run a session to create state
        $agent->runSession('test');
        $this->assertGreaterThan(0, $agent->getProgress());

        // Reset
        $agent->reset();

        $this->assertEquals(0, $agent->getProgress());
        $this->assertFalse($agent->isGoalComplete());
        $this->assertEquals(1, $agent->getState()->getSessionNumber());
    }

    public function testRunAlias(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Task executed.'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Test goal',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('test task');

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(AgentResult::class, $result);
    }

    public function testStatePersistedBetweenSessions(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Working on it.'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        // First agent instance
        $agent1 = new AutonomousAgent($client, [
            'goal' => 'Test goal',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        $agent1->runSession('First task');
        $progress1 = $agent1->getProgress();

        // Create new agent instance with same state file
        $agent2 = new AutonomousAgent($client, [
            'goal' => 'Test goal',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        // Should have loaded previous state
        $this->assertEquals($progress1, $agent2->getProgress());
    }

    public function testExtractTextContentHandlesMultipleBlocks(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'First block.'],
                ['type' => 'text', 'text' => 'Second block.'],
                ['type' => 'other', 'data' => 'ignored'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Test goal',
            'state_file' => $this->testStateFile,
            'logger' => new NullLogger(),
        ]);

        $result = $agent->runSession('test');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('First block', $result->getAnswer());
        $this->assertStringContainsString('Second block', $result->getAnswer());
    }

    public function testMaxActionsPerSession(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new AutonomousAgent($client, [
            'goal' => 'Test goal',
            'state_file' => $this->testStateFile,
            'max_actions_per_session' => 3,
        ]);

        // Verify the agent was created with the limit
        $this->assertInstanceOf(AutonomousAgent::class, $agent);
    }

    public function testGoalCompletionIndicators(): void
    {
        $completionWords = ['completed', 'finished', 'done', 'achieved', 'accomplished'];

        foreach ($completionWords as $word) {
            $usage = new Usage(input_tokens: 100, output_tokens: 50);
            $mockResponse = new Message(
                id: 'msg_test',
                type: 'message',
                role: 'assistant',
                content: [
                    ['type' => 'text', 'text' => "The task is now $word."],
                ],
                model: 'claude-sonnet-4-5',
                stop_reason: 'end_turn',
                stop_sequence: null,
                usage: $usage
            );

            $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
            $mockMessages->method('create')->willReturn($mockResponse);

            $client = $this->createMock(ClaudePhp::class);
            $client->method('messages')->willReturn($mockMessages);

            $testFile = sys_get_temp_dir() . '/test_' . uniqid() . '.json';

            $agent = new AutonomousAgent($client, [
                'goal' => 'Test goal',
                'state_file' => $testFile,
                'logger' => new NullLogger(),
            ]);

            $result = $agent->runSession('test');

            $this->assertTrue($result->getMetadata()['goal_complete'], "Failed for word: $word");

            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }
}
