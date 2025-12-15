<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\Agents\ModelBasedAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ModelBasedAgentTest extends TestCase
{
    public function testConstructorInitializesWithDefaults(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new ModelBasedAgent($client);

        $this->assertEquals('model_based_agent', $agent->getName());
        $this->assertEquals([], $agent->getState());
        $this->assertEquals([], $agent->getStateHistory());
    }

    public function testConstructorInitializesWithOptions(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $logger = new NullLogger();

        $initialState = ['location' => 'home', 'time' => 'morning'];

        $agent = new ModelBasedAgent($client, [
            'name' => 'test_agent',
            'initial_state' => $initialState,
            'logger' => $logger,
        ]);

        $this->assertEquals('test_agent', $agent->getName());
        $this->assertEquals($initialState, $agent->getState());
    }

    public function testGetName(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new ModelBasedAgent($client, [
            'name' => 'my_agent',
        ]);

        $this->assertEquals('my_agent', $agent->getName());
    }

    public function testSetGoal(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new ModelBasedAgent($client);

        $agent->setGoal('Reach the treasure');

        // Goal is set internally, we'll verify through run()
        $this->assertInstanceOf(ModelBasedAgent::class, $agent);
    }

    public function testUpdateState(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['x' => 0, 'y' => 0],
        ]);

        $agent->updateState(['x' => 5]);

        $state = $agent->getState();
        $this->assertEquals(5, $state['x']);
        $this->assertEquals(0, $state['y']);

        // Check history was recorded
        $history = $agent->getStateHistory();
        $this->assertCount(1, $history);
        $this->assertEquals(0, $history[0]['previous']['x']);
        $this->assertEquals(5, $history[0]['current']['x']);
    }

    public function testUpdateStateMultipleTimes(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['health' => 100],
        ]);

        $agent->updateState(['health' => 90]);
        $agent->updateState(['health' => 80]);
        $agent->updateState(['health' => 70]);

        $history = $agent->getStateHistory();
        $this->assertCount(3, $history);

        // Current state should reflect last update
        $state = $agent->getState();
        $this->assertEquals(70, $state['health']);
    }

    public function testUpdateStateAddsNewProperties(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['x' => 0],
        ]);

        $agent->updateState(['y' => 5, 'z' => 10]);

        $state = $agent->getState();
        $this->assertEquals(0, $state['x']);
        $this->assertEquals(5, $state['y']);
        $this->assertEquals(10, $state['z']);
    }

    public function testStateHistoryLimitedTo100(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new ModelBasedAgent($client);

        // Add 150 state updates
        for ($i = 0; $i < 150; $i++) {
            $agent->updateState(['counter' => $i]);
        }

        $history = $agent->getStateHistory();
        $this->assertCount(100, $history);

        // Should have kept the most recent 100
        $this->assertEquals(149, $history[99]['current']['counter']);
    }

    public function testGetState(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $initialState = ['position' => 'start', 'items' => []];

        $agent = new ModelBasedAgent($client, [
            'initial_state' => $initialState,
        ]);

        $this->assertEquals($initialState, $agent->getState());
    }

    public function testGetStateHistory(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new ModelBasedAgent($client);

        $this->assertEquals([], $agent->getStateHistory());

        $agent->updateState(['test' => 1]);
        $history = $agent->getStateHistory();

        $this->assertCount(1, $history);
        $this->assertArrayHasKey('timestamp', $history[0]);
        $this->assertArrayHasKey('previous', $history[0]);
        $this->assertArrayHasKey('current', $history[0]);
    }

    public function testAddTransitionRule(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['x' => 0],
        ]);

        $agent->addTransitionRule('move_right', function (array $state): array {
            return array_merge($state, ['x' => $state['x'] + 1]);
        });

        $nextState = $agent->predictNextState('move_right');

        $this->assertEquals(1, $nextState['x']);
    }

    public function testPredictNextStateWithRule(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['health' => 100, 'energy' => 100],
        ]);

        $agent->addTransitionRule('rest', function (array $state): array {
            return array_merge($state, [
                'health' => min(100, $state['health'] + 20),
                'energy' => min(100, $state['energy'] + 30),
            ]);
        });

        $agent->updateState(['health' => 70, 'energy' => 50]);
        $nextState = $agent->predictNextState('rest');

        $this->assertEquals(90, $nextState['health']);
        $this->assertEquals(80, $nextState['energy']);
    }

    public function testPredictNextStateWithoutRuleUsesLLM(): void
    {
        $usage = new Usage(input_tokens: 50, output_tokens: 30);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '{"x": 5, "y": 10}'],
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

        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['x' => 0, 'y' => 0],
            'logger' => new NullLogger(),
        ]);

        $nextState = $agent->predictNextState('unknown_action');

        $this->assertEquals(5, $nextState['x']);
        $this->assertEquals(10, $nextState['y']);
    }

    public function testRunWithGoalStatementCreatesPlan(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => "1. Move to location A\n2. Pick up key\n3. Unlock door"],
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

        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['position' => 'start'],
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Goal: reach the treasure');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Plan:', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('goal', $metadata);
        $this->assertArrayHasKey('planned_actions', $metadata);
        $this->assertIsArray($metadata['planned_actions']);
        $this->assertCount(3, $metadata['planned_actions']);
    }

    public function testRunWithObservationUpdatesState(): void
    {
        $usage = new Usage(input_tokens: 50, output_tokens: 30);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '{"temperature": 75}'],
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

        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['temperature' => 70],
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('The temperature increased by 5 degrees');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('State updated', $result->getAnswer());

        $state = $agent->getState();
        $this->assertEquals(75, $state['temperature']);
    }

    public function testRunWithGoalKeywords(): void
    {
        $goalKeywords = ['achieve', 'goal', 'reach', 'get to', 'make', 'plan'];

        foreach ($goalKeywords as $keyword) {
            $usage = new Usage(input_tokens: 100, output_tokens: 50);
            $mockResponse = new Message(
                id: 'msg_test',
                type: 'message',
                role: 'assistant',
                content: [
                    ['type' => 'text', 'text' => "1. Step one\n2. Step two"],
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

            $agent = new ModelBasedAgent($client, [
                'logger' => new NullLogger(),
            ]);

            $result = $agent->run("I want to $keyword something");

            $this->assertTrue($result->isSuccess(), "Failed for keyword: $keyword");
            $this->assertStringContainsString('Plan:', $result->getAnswer());
        }
    }

    public function testRunHandlesErrorInPlanning(): void
    {
        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new ModelBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        // Test with a goal statement that will trigger LLM call in planning
        $result = $agent->run('I want to achieve test goal');

        // Planning error is caught and returns empty plan, which is still a success
        // The agent just returns an empty plan when planning fails
        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $this->assertEmpty($metadata['planned_actions']);
    }

    public function testRunHandlesErrorInObservation(): void
    {
        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['x' => 5],
            'logger' => new NullLogger(),
        ]);

        // Test with observation (not a goal statement)
        $result = $agent->run('Something happened');

        // Observation error is caught internally and state remains unchanged
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['x' => 5], $agent->getState());
    }

    public function testRunHandlesEmptyObservation(): void
    {
        $usage = new Usage(input_tokens: 50, output_tokens: 20);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '{}'],
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

        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['x' => 5],
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Nothing changed');

        $this->assertTrue($result->isSuccess());
        // State should remain unchanged
        $this->assertEquals(['x' => 5], $agent->getState());
    }

    public function testPredictNextStateHandlesLLMError(): void
    {
        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willThrowException(new \Exception('LLM Error'));

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['x' => 5],
            'logger' => new NullLogger(),
        ]);

        // Should return current state when prediction fails
        $nextState = $agent->predictNextState('unknown_action');

        $this->assertEquals(['x' => 5], $nextState);
    }

    public function testPredictNextStateHandlesInvalidJSON(): void
    {
        $usage = new Usage(input_tokens: 50, output_tokens: 30);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'invalid json {{{'],
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

        $agent = new ModelBasedAgent($client, [
            'initial_state' => ['x' => 5],
            'logger' => new NullLogger(),
        ]);

        $nextState = $agent->predictNextState('action');

        // Should return current state when JSON is invalid
        $this->assertEquals(['x' => 5], $nextState);
    }

    public function testComplexStateTransitions(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new ModelBasedAgent($client, [
            'initial_state' => [
                'player' => ['x' => 0, 'y' => 0, 'health' => 100],
                'inventory' => [],
            ],
        ]);

        // Add multiple transition rules
        $agent->addTransitionRule('move_north', function (array $state): array {
            $state['player']['y'] += 1;

            return $state;
        });

        $agent->addTransitionRule('collect_item', function (array $state): array {
            $state['inventory'][] = 'key';

            return $state;
        });

        $agent->addTransitionRule('take_damage', function (array $state): array {
            $state['player']['health'] -= 10;

            return $state;
        });

        // Execute sequence of actions
        $state1 = $agent->predictNextState('move_north');
        $this->assertEquals(1, $state1['player']['y']);

        // Update the full player state after moving
        $agent->updateState(['player' => ['x' => 0, 'y' => 1, 'health' => 100]]);
        $state2 = $agent->predictNextState('collect_item');
        $this->assertContains('key', $state2['inventory']);

        // Update with inventory and keep player state
        $agent->updateState(['player' => ['x' => 0, 'y' => 1, 'health' => 100], 'inventory' => ['key']]);
        $state3 = $agent->predictNextState('take_damage');
        $this->assertEquals(90, $state3['player']['health']);
    }

    public function testExtractTextContentHandlesMultipleBlocks(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '1. First action'],
                ['type' => 'text', 'text' => '2. Second action'],
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

        $agent = new ModelBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Goal: accomplish task');

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $this->assertCount(2, $metadata['planned_actions']);
    }

    public function testParseActionsHandlesVariousFormats(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => "1. First action\n2. Second action\nThird action\n# Comment\n4. Fourth action"],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturn($mockResponse);

        $client->method('messages')->willReturn($mockMessages);

        $agent = new ModelBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Goal: test');

        $metadata = $result->getMetadata();
        $actions = $metadata['planned_actions'];

        $this->assertCount(4, $actions);
        $this->assertEquals('First action', $actions[0]);
        $this->assertEquals('Second action', $actions[1]);
        $this->assertEquals('Third action', $actions[2]);
        $this->assertEquals('Fourth action', $actions[3]);
    }
}
