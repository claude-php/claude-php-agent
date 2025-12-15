<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\Agents\UtilityBasedAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class UtilityBasedAgentTest extends TestCase
{
    public function testConstructorInitializesWithDefaults(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new UtilityBasedAgent($client);

        $this->assertEquals('utility_agent', $agent->getName());
    }

    public function testConstructorInitializesWithOptions(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $logger = new NullLogger();

        $utilityFn = fn ($action) => 0.5;

        $agent = new UtilityBasedAgent($client, [
            'name' => 'test_agent',
            'utility_function' => $utilityFn,
            'logger' => $logger,
        ]);

        $this->assertEquals('test_agent', $agent->getName());
    }

    public function testGetName(): void
    {
        $client = $this->createMock(ClaudePhp::class);

        $agent = new UtilityBasedAgent($client, [
            'name' => 'my_utility_agent',
        ]);

        $this->assertEquals('my_utility_agent', $agent->getName());
    }

    public function testSetUtilityFunction(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new UtilityBasedAgent($client);

        $newUtilityFn = fn ($action) => ($action['estimated_value'] ?? 50) / 100;
        $agent->setUtilityFunction($newUtilityFn);

        // Utility function is set internally
        $this->assertInstanceOf(UtilityBasedAgent::class, $agent);
    }

    public function testAddObjective(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new UtilityBasedAgent($client);

        $objectiveFn = fn ($action) => $action['estimated_value'] ?? 50;
        $agent->addObjective('value', $objectiveFn, weight: 0.7);

        // Objective is added internally
        $this->assertInstanceOf(UtilityBasedAgent::class, $agent);
    }

    public function testAddMultipleObjectives(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new UtilityBasedAgent($client);

        $agent->addObjective('value', fn ($action) => $action['estimated_value'] ?? 50, 0.6);
        $agent->addObjective('cost', fn ($action) => 100 - ($action['estimated_cost'] ?? 50), 0.4);

        $this->assertInstanceOf(UtilityBasedAgent::class, $agent);
    }

    public function testAddConstraint(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new UtilityBasedAgent($client);

        $constraintFn = fn ($action) => ($action['estimated_cost'] ?? 100) <= 80;
        $agent->addConstraint('budget_limit', $constraintFn);

        $this->assertInstanceOf(UtilityBasedAgent::class, $agent);
    }

    public function testRunGeneratesAndEvaluatesActions(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    [
                        'description' => 'Use microservices architecture',
                        'estimated_value' => 80,
                        'estimated_cost' => 70,
                        'risk' => 'medium',
                    ],
                    [
                        'description' => 'Use monolithic architecture',
                        'estimated_value' => 60,
                        'estimated_cost' => 40,
                        'risk' => 'low',
                    ],
                    [
                        'description' => 'Use serverless architecture',
                        'estimated_value' => 85,
                        'estimated_cost' => 60,
                        'risk' => 'medium',
                    ],
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

        $agent = new UtilityBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Choose the best architecture');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Utility-Based Decision', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('actions_evaluated', $metadata);
        $this->assertArrayHasKey('best_action', $metadata);
        $this->assertArrayHasKey('best_utility', $metadata);
        $this->assertEquals(3, $metadata['actions_evaluated']);
    }

    public function testRunWithObjectives(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    [
                        'description' => 'High value, high cost',
                        'estimated_value' => 90,
                        'estimated_cost' => 80,
                        'risk' => 'high',
                    ],
                    [
                        'description' => 'Medium value, low cost',
                        'estimated_value' => 60,
                        'estimated_cost' => 30,
                        'risk' => 'low',
                    ],
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

        $agent = new UtilityBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        // Add objectives with different weights
        $agent->addObjective('value', fn ($action) => $action['estimated_value'] ?? 0, 0.7);
        $agent->addObjective('cost', fn ($action) => 100 - ($action['estimated_cost'] ?? 50), 0.3);

        $result = $agent->run('Choose the best option');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('all_evaluations', $metadata);

        // Check that evaluations contain objective scores
        if (! empty($metadata['all_evaluations'])) {
            $this->assertArrayHasKey('objective_scores', $metadata['all_evaluations'][0]);
        }
    }

    public function testRunWithConstraints(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    [
                        'description' => 'Expensive option',
                        'estimated_value' => 90,
                        'estimated_cost' => 95,
                        'risk' => 'high',
                    ],
                    [
                        'description' => 'Affordable option',
                        'estimated_value' => 70,
                        'estimated_cost' => 50,
                        'risk' => 'low',
                    ],
                    [
                        'description' => 'Budget option',
                        'estimated_value' => 60,
                        'estimated_cost' => 30,
                        'risk' => 'low',
                    ],
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

        $agent = new UtilityBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        // Add constraint: cost must be <= 80
        $agent->addConstraint('budget', fn ($action) => ($action['estimated_cost'] ?? 100) <= 80);

        $result = $agent->run('Choose within budget');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        // The expensive option should be filtered out (2 remain)
        // But all 3 actions were evaluated before filtering
        $this->assertEquals(3, $metadata['actions_evaluated']);
        $this->assertLessThanOrEqual(2, count($metadata['all_evaluations']));
    }

    public function testRunWithNoValidActions(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
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
        $mockMessages->method('create')->willReturn($mockResponse);

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new UtilityBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Invalid task');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No valid actions', $result->getError());
    }

    public function testRunHandlesException(): void
    {
        $mockMessages = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $mockMessages->method('create')->willThrowException(new \Exception('API Error'));

        $client = $this->createMock(ClaudePhp::class);
        $client->method('messages')->willReturn($mockMessages);

        $agent = new UtilityBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test task');

        $this->assertFalse($result->isSuccess());
        // The exception is caught in generateActions() which returns empty array
        // This then results in "No valid actions generated" error
        $this->assertStringContainsString('No valid actions', $result->getError());
    }

    public function testSingleUtilityFunctionWhenNoObjectives(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    [
                        'description' => 'Option A',
                        'estimated_value' => 80,
                    ],
                    [
                        'description' => 'Option B',
                        'estimated_value' => 90,
                    ],
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

        $agent = new UtilityBasedAgent($client, [
            'utility_function' => fn ($action) => ($action['estimated_value'] ?? 50) / 100,
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Choose the best option');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        $this->assertGreaterThan(0, $metadata['best_utility']);
    }

    public function testMultiObjectiveOptimization(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    [
                        'description' => 'Balanced option',
                        'estimated_value' => 70,
                        'estimated_cost' => 60,
                        'risk' => 'medium',
                    ],
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

        $agent = new UtilityBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        // Add multiple objectives
        $agent->addObjective('value', fn ($action) => $action['estimated_value'] ?? 0, 0.5);
        $agent->addObjective('cost', fn ($action) => 100 - ($action['estimated_cost'] ?? 50), 0.3);
        $agent->addObjective('risk', fn ($action) => match($action['risk'] ?? 'high') {
            'low' => 100,
            'medium' => 60,
            'high' => 20,
        }, 0.2);

        $result = $agent->run('Find balanced solution');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Objective Scores:', $result->getAnswer());
    }

    public function testConstraintFiltersActions(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    [
                        'description' => 'Safe option',
                        'risk' => 'low',
                        'estimated_value' => 60,
                    ],
                    [
                        'description' => 'Risky option',
                        'risk' => 'high',
                        'estimated_value' => 90,
                    ],
                    [
                        'description' => 'Medium risk option',
                        'risk' => 'medium',
                        'estimated_value' => 75,
                    ],
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

        $agent = new UtilityBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        // Add constraint: only low and medium risk
        $agent->addConstraint('risk_limit', fn ($action) => in_array($action['risk'] ?? 'high', ['low', 'medium']));

        $result = $agent->run('Choose safe option');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        // actions_evaluated is count of generated actions (3), not filtered actions
        $this->assertEquals(3, $metadata['actions_evaluated']);
        // But all_evaluations should only have 2 (high risk filtered out)
        $this->assertLessThanOrEqual(2, count($metadata['all_evaluations']));
    }

    public function testFormattedDecisionOutput(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    [
                        'description' => 'Best option',
                        'estimated_value' => 85,
                        'estimated_cost' => 50,
                        'risk' => 'low',
                    ],
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

        $agent = new UtilityBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test task');

        $this->assertTrue($result->isSuccess());

        $answer = $result->getAnswer();
        $this->assertStringContainsString('Utility-Based Decision', $answer);
        $this->assertStringContainsString('Selected Action:', $answer);
        $this->assertStringContainsString('Utility Score:', $answer);
        $this->assertStringContainsString('Action Details:', $answer);
    }

    public function testEmptyActionsArray(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([])],
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

        $agent = new UtilityBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        $result = $agent->run('Test task');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No valid actions', $result->getError());
    }

    public function testAllActionsFilteredByConstraints(): void
    {
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => json_encode([
                    ['description' => 'Expensive 1', 'estimated_cost' => 100],
                    ['description' => 'Expensive 2', 'estimated_cost' => 95],
                    ['description' => 'Expensive 3', 'estimated_cost' => 90],
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

        $agent = new UtilityBasedAgent($client, [
            'logger' => new NullLogger(),
        ]);

        // Add strict constraint
        $agent->addConstraint('budget', fn ($action) => ($action['estimated_cost'] ?? 100) <= 50);

        $result = $agent->run('Find cheap option');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        // All actions filtered, should return default "no valid actions"
        $this->assertStringContainsString('No valid actions', $metadata['best_action']['description']);
    }
}
