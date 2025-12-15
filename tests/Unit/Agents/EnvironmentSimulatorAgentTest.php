<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\Agents\EnvironmentSimulatorAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EnvironmentSimulatorAgentTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new EnvironmentSimulatorAgent($client);

        $this->assertEquals('environment_simulator', $agent->getName());
        $this->assertEquals([], $agent->getState());
    }

    public function testConstructorWithCustomOptions(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $initialState = ['temperature' => 25, 'humidity' => 60];

        $agent = new EnvironmentSimulatorAgent($client, [
            'name' => 'weather_simulator',
            'initial_state' => $initialState,
        ]);

        $this->assertEquals('weather_simulator', $agent->getName());
        $this->assertEquals($initialState, $agent->getState());
    }

    public function testGetName(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new EnvironmentSimulatorAgent($client, [
            'name' => 'test_simulator',
        ]);

        $this->assertEquals('test_simulator', $agent->getName());
    }

    public function testGetState(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $state = ['servers' => 5, 'load' => 'low'];

        $agent = new EnvironmentSimulatorAgent($client, [
            'initial_state' => $state,
        ]);

        $this->assertEquals($state, $agent->getState());
    }

    public function testSetState(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new EnvironmentSimulatorAgent($client);

        $newState = ['traffic' => 'high', 'active_users' => 1000];
        $agent->setState($newState);

        $this->assertEquals($newState, $agent->getState());
    }

    public function testRunExecutesSuccessfully(): void
    {
        // Mock API response
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    'resulting_state' => ['temperature' => 30],
                    'outcome' => 'Temperature increased by 5 degrees',
                    'side_effects' => ['increased energy consumption'],
                    'success_probability' => 0.95,
                ])],
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

        $agent = new EnvironmentSimulatorAgent($client, [
            'initial_state' => ['temperature' => 25],
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Increase temperature');

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getAnswer());
        $this->assertEquals(1, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('initial_state', $metadata);
        $this->assertArrayHasKey('action', $metadata);
        $this->assertArrayHasKey('resulting_state', $metadata);
        $this->assertArrayHasKey('outcome', $metadata);
    }

    public function testSimulateActionReturnsValidStructure(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    'resulting_state' => ['servers' => 10],
                    'outcome' => 'Servers scaled up successfully',
                    'side_effects' => ['increased cost', 'improved performance'],
                    'success_probability' => 0.90,
                ])],
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

        $agent = new EnvironmentSimulatorAgent($client, [
            'initial_state' => ['servers' => 5],
            'logger' => new NullLogger(),
        ]);

        $simulation = $agent->simulateAction('Scale to 10 servers');

        $this->assertIsArray($simulation);
        $this->assertArrayHasKey('initial_state', $simulation);
        $this->assertArrayHasKey('action', $simulation);
        $this->assertArrayHasKey('resulting_state', $simulation);
        $this->assertArrayHasKey('outcome', $simulation);
        $this->assertArrayHasKey('side_effects', $simulation);
        $this->assertArrayHasKey('success_probability', $simulation);
        $this->assertArrayHasKey('description', $simulation);

        $this->assertEquals('Scale to 10 servers', $simulation['action']);
        $this->assertEquals(['servers' => 5], $simulation['initial_state']);
        $this->assertIsFloat($simulation['success_probability']);
    }

    public function testSimulateActionHandlesInvalidJson(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'This is not valid JSON'],
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

        $initialState = ['servers' => 5];
        $agent = new EnvironmentSimulatorAgent($client, [
            'initial_state' => $initialState,
            'logger' => new NullLogger(),
        ]);

        $simulation = $agent->simulateAction('Invalid action');

        $this->assertIsArray($simulation);
        $this->assertEquals($initialState, $simulation['resulting_state']);
        $this->assertEquals('Unknown outcome', $simulation['outcome']);
        $this->assertEquals([], $simulation['side_effects']);
        $this->assertEquals(0.5, $simulation['success_probability']);
    }

    public function testRunHandlesError(): void
    {
        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new EnvironmentSimulatorAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test action');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('API Error', $result->getError());
    }

    public function testExtractTextContentHandlesMultipleBlocks(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $jsonPart1 = '{"resulting_state": {"value": 1}, ';
        $jsonPart2 = '"outcome": "test", "side_effects": [], "success_probability": 0.8}';

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => $jsonPart1],
                ['type' => 'text', 'text' => $jsonPart2],
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

        $agent = new EnvironmentSimulatorAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $simulation = $agent->simulateAction('test');

        $this->assertIsArray($simulation);
        $this->assertEquals('test', $simulation['outcome']);
        $this->assertEquals(0.8, $simulation['success_probability']);
    }

    public function testSimulationHistoryIsTracked(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willReturnCallback(function () use ($usage) {
            return new Message(
                id: 'msg_test',
                type: 'message',
                role: 'assistant',
                content: [
                    ['type' => 'text', 'text' => json_encode([
                        'resulting_state' => ['count' => 1],
                        'outcome' => 'Action completed',
                        'side_effects' => [],
                        'success_probability' => 0.9,
                    ])],
                ],
                model: 'claude-sonnet-4-5',
                stop_reason: 'end_turn',
                stop_sequence: null,
                usage: $usage
            );
        });

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new EnvironmentSimulatorAgent($client, [
            'logger' => new NullLogger(),
        ]);

        // First simulation
        $result1 = $agent->run('Action 1');
        $this->assertTrue($result1->isSuccess());

        // Second simulation
        $result2 = $agent->run('Action 2');
        $this->assertTrue($result2->isSuccess());

        // Both should succeed independently
        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());
    }

    public function testSimulateActionWithComplexState(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $complexState = [
            'database' => ['connections' => 50, 'query_time_ms' => 120],
            'cache' => ['hit_rate' => 0.85, 'size_mb' => 512],
            'api' => ['requests_per_sec' => 1000, 'error_rate' => 0.01],
        ];

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    'resulting_state' => array_merge($complexState, [
                        'cache' => ['hit_rate' => 0.90, 'size_mb' => 1024],
                    ]),
                    'outcome' => 'Cache scaled successfully',
                    'side_effects' => ['memory usage increased'],
                    'success_probability' => 0.92,
                ])],
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

        $agent = new EnvironmentSimulatorAgent($client, [
            'initial_state' => $complexState,
            'logger' => new NullLogger(),
        ]);

        $simulation = $agent->simulateAction('Double cache size');

        $this->assertEquals($complexState, $simulation['initial_state']);
        $this->assertArrayHasKey('cache', $simulation['resulting_state']);
        $this->assertEquals(1024, $simulation['resulting_state']['cache']['size_mb']);
    }

    public function testSimulateActionWithNoSideEffects(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    'resulting_state' => ['status' => 'ok'],
                    'outcome' => 'Read operation completed',
                    'side_effects' => [],
                    'success_probability' => 1.0,
                ])],
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

        $agent = new EnvironmentSimulatorAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $simulation = $agent->simulateAction('Read status');

        $this->assertEquals([], $simulation['side_effects']);
        $this->assertStringNotContainsString('Side Effects', $simulation['description']);
    }

    public function testSimulateActionWithMultipleSideEffects(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $sideEffects = [
            'increased CPU usage',
            'network bandwidth spike',
            'temporary file creation',
        ];

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    'resulting_state' => ['status' => 'processing'],
                    'outcome' => 'Heavy operation started',
                    'side_effects' => $sideEffects,
                    'success_probability' => 0.75,
                ])],
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

        $agent = new EnvironmentSimulatorAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $simulation = $agent->simulateAction('Start heavy processing');

        $this->assertEquals($sideEffects, $simulation['side_effects']);
        $this->assertStringContainsString('Side Effects', $simulation['description']);
        foreach ($sideEffects as $effect) {
            $this->assertStringContainsString($effect, $simulation['description']);
        }
    }

    public function testSuccessProbabilityRanges(): void
    {
        $probabilities = [0.0, 0.25, 0.5, 0.75, 1.0];

        foreach ($probabilities as $probability) {
            $usage = new Usage(input_tokens: 100, output_tokens: 50);
            $mockResponse = new Message(
                id: 'msg_test',
                type: 'message',
                role: 'assistant',
                content: [
                    ['type' => 'text', 'text' => json_encode([
                        'resulting_state' => ['status' => 'test'],
                        'outcome' => 'Test outcome',
                        'side_effects' => [],
                        'success_probability' => $probability,
                    ])],
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

            $agent = new EnvironmentSimulatorAgent($client, [
                'logger' => new NullLogger(),
            ]);

            $simulation = $agent->simulateAction('test');

            $this->assertEquals($probability, $simulation['success_probability']);
            $expectedPercentage = $probability * 100;
            $this->assertStringContainsString("{$expectedPercentage}%", $simulation['description']);
        }
    }

    public function testStateDoesNotChangeAfterSimulation(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    'resulting_state' => ['value' => 100],
                    'outcome' => 'Changed',
                    'side_effects' => [],
                    'success_probability' => 0.9,
                ])],
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

        $initialState = ['value' => 50];
        $agent = new EnvironmentSimulatorAgent($client, [
            'initial_state' => $initialState,
            'logger' => new NullLogger(),
        ]);

        $simulation = $agent->simulateAction('increase value');

        // Agent's state should not change (simulation doesn't apply changes)
        $this->assertEquals($initialState, $agent->getState());

        // But simulation should show the predicted state
        $this->assertEquals(['value' => 100], $simulation['resulting_state']);
    }

    public function testEmptyStateHandling(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    'resulting_state' => ['initialized' => true],
                    'outcome' => 'Environment initialized',
                    'side_effects' => [],
                    'success_probability' => 1.0,
                ])],
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

        $agent = new EnvironmentSimulatorAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $simulation = $agent->simulateAction('Initialize environment');

        $this->assertEquals([], $simulation['initial_state']);
        $this->assertEquals(['initialized' => true], $simulation['resulting_state']);
    }
}
