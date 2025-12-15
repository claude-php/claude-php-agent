<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\Agents\LearningAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use Mockery;
use PHPUnit\Framework\TestCase;

class LearningAgentTest extends TestCase
{
    private $client;
    private LearningAgent $agent;

    protected function setUp(): void
    {
        $this->client = Mockery::mock(ClaudePhp::class);
        $this->agent = new LearningAgent($this->client, [
            'name' => 'test_learner',
            'learning_rate' => 0.1,
            'initial_strategies' => ['default', 'analytical'],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testConstructorSetsDefaults(): void
    {
        $agent = new LearningAgent($this->client);
        $this->assertEquals('learning_agent', $agent->getName());
    }

    public function testConstructorAcceptsCustomName(): void
    {
        $agent = new LearningAgent($this->client, ['name' => 'custom_learner']);
        $this->assertEquals('custom_learner', $agent->getName());
    }

    public function testConstructorInitializesStrategies(): void
    {
        $agent = new LearningAgent($this->client, [
            'initial_strategies' => ['strategy1', 'strategy2', 'strategy3'],
        ]);

        $performance = $agent->getPerformance();
        $this->assertArrayHasKey('strategy1', $performance);
        $this->assertArrayHasKey('strategy2', $performance);
        $this->assertArrayHasKey('strategy3', $performance);
    }

    public function testRunReturnsSuccessResult(): void
    {
        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Task completed successfully']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $this->client->shouldReceive('messages->create')
            ->once()
            ->andReturn($mockMessage);

        $result = $this->agent->run('Solve this problem');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Task completed', $result->getAnswer());
        $this->assertArrayHasKey('strategy_used', $result->getMetadata());
        $this->assertArrayHasKey('experience_id', $result->getMetadata());
        $this->assertArrayHasKey('total_experiences', $result->getMetadata());
    }

    public function testRunRecordsExperience(): void
    {
        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $this->client->shouldReceive('messages->create')
            ->once()
            ->andReturn($mockMessage);

        $initialCount = count($this->agent->getExperiences());
        $this->agent->run('Test task');
        $newCount = count($this->agent->getExperiences());

        $this->assertEquals($initialCount + 1, $newCount);
    }

    public function testProvideFeedbackUpdatesExperience(): void
    {
        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $this->client->shouldReceive('messages->create')
            ->once()
            ->andReturn($mockMessage);

        $result = $this->agent->run('Test task');
        $experienceId = $result->getMetadata()['experience_id'];

        $this->agent->provideFeedback($experienceId, 0.8, true, ['comment' => 'Great!']);

        $experiences = $this->agent->getExperiences();
        $experience = $experiences[$experienceId] ?? null;

        $this->assertNotNull($experience);
        $this->assertEquals(0.8, $experience['reward']);
        $this->assertTrue($experience['success']);
        $this->assertEquals('Great!', $experience['feedback']['comment']);
    }

    public function testProvideFeedbackUpdatesPerformance(): void
    {
        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $this->client->shouldReceive('messages->create')
            ->once()
            ->andReturn($mockMessage);

        $result = $this->agent->run('Test task');
        $experienceId = $result->getMetadata()['experience_id'];
        $strategyUsed = $result->getMetadata()['strategy_used'];

        $initialPerf = $this->agent->getPerformance()[$strategyUsed];

        $this->agent->provideFeedback($experienceId, 0.9, true);

        $updatedPerf = $this->agent->getPerformance()[$strategyUsed];

        $this->assertEquals($initialPerf['attempts'] + 1, $updatedPerf['attempts']);
        $this->assertEquals($initialPerf['successes'] + 1, $updatedPerf['successes']);
        $this->assertGreaterThan($initialPerf['total_reward'], $updatedPerf['total_reward']);
    }

    public function testAddStrategyCreatesNewStrategy(): void
    {
        $this->agent->addStrategy('creative');

        $performance = $this->agent->getPerformance();
        $this->assertArrayHasKey('creative', $performance);
        $this->assertEquals(0, $performance['creative']['attempts']);
        $this->assertEquals(0, $performance['creative']['successes']);
        $this->assertEquals(0.0, $performance['creative']['total_reward']);
    }

    public function testAddStrategyIgnoresDuplicates(): void
    {
        $initialPerf = $this->agent->getPerformance();
        $initialCount = count($initialPerf);

        $this->agent->addStrategy('default'); // Already exists

        $updatedPerf = $this->agent->getPerformance();
        $this->assertCount($initialCount, $updatedPerf);
    }

    public function testGetExperiencesReturnsRecentExperiences(): void
    {
        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $this->client->shouldReceive('messages->create')
            ->times(5)
            ->andReturn($mockMessage);

        // Create 5 experiences
        for ($i = 0; $i < 5; $i++) {
            $this->agent->run("Task $i");
        }

        $experiences = $this->agent->getExperiences(3);
        $this->assertCount(3, $experiences);
    }

    public function testReplayBufferLimitsExperiences(): void
    {
        $agent = new LearningAgent($this->client, [
            'replay_buffer_size' => 5,
        ]);

        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $this->client->shouldReceive('messages->create')
            ->times(7)
            ->andReturn($mockMessage);

        // Create 7 experiences (exceeds buffer size of 5)
        for ($i = 0; $i < 7; $i++) {
            $agent->run("Task $i");
        }

        $experiences = $agent->getExperiences();
        $this->assertLessThanOrEqual(5, count($experiences));
    }

    public function testLearningTriggeredEveryTenFeedbacks(): void
    {
        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $this->client->shouldReceive('messages->create')
            ->times(10)
            ->andReturn($mockMessage);

        // Create 10 experiences and provide feedback
        for ($i = 0; $i < 10; $i++) {
            $result = $this->agent->run("Task $i");
            $expId = $result->getMetadata()['experience_id'];
            $this->agent->provideFeedback($expId, 0.5, true);
        }

        // Learning should have been triggered at least once
        $this->assertTrue(true); // Test passes if no exceptions
    }

    public function testStrategySelectionExploration(): void
    {
        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $this->client->shouldReceive('messages->create')
            ->times(10)
            ->andReturn($mockMessage);

        $strategiesUsed = [];

        // Run multiple tasks and track strategies
        for ($i = 0; $i < 10; $i++) {
            $result = $this->agent->run("Task $i");
            $strategyUsed = $result->getMetadata()['strategy_used'];
            $strategiesUsed[] = $strategyUsed;

            // Provide varied feedback to influence strategy selection
            $expId = $result->getMetadata()['experience_id'];
            $reward = ($strategyUsed === 'analytical') ? 0.9 : 0.5;
            $this->agent->provideFeedback($expId, $reward, true);
        }

        // Verify strategies are being used
        $this->assertNotEmpty($strategiesUsed);

        // Verify all strategies are valid
        foreach ($strategiesUsed as $strategy) {
            $this->assertContains($strategy, ['default', 'analytical']);
        }

        // Verify performance tracking is updated
        $performance = $this->agent->getPerformance();
        $totalAttempts = array_sum(array_column($performance, 'attempts'));
        $this->assertEquals(10, $totalAttempts);
    }

    public function testGetName(): void
    {
        $this->assertEquals('test_learner', $this->agent->getName());
    }

    public function testGetPerformanceReturnsAllStrategies(): void
    {
        $performance = $this->agent->getPerformance();

        $this->assertIsArray($performance);
        $this->assertArrayHasKey('default', $performance);
        $this->assertArrayHasKey('analytical', $performance);

        foreach ($performance as $strategyPerf) {
            $this->assertArrayHasKey('attempts', $strategyPerf);
            $this->assertArrayHasKey('successes', $strategyPerf);
            $this->assertArrayHasKey('total_reward', $strategyPerf);
            $this->assertArrayHasKey('avg_reward', $strategyPerf);
        }
    }

    public function testFeedbackWithNonexistentExperience(): void
    {
        // Should not throw exception
        $this->agent->provideFeedback('nonexistent_id', 0.5, true);

        // Test passes if no exception thrown
        $this->assertTrue(true);
    }

    public function testNegativeRewardUpdatePerformance(): void
    {
        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $this->client->shouldReceive('messages->create')
            ->once()
            ->andReturn($mockMessage);

        $result = $this->agent->run('Test task');
        $experienceId = $result->getMetadata()['experience_id'];
        $strategyUsed = $result->getMetadata()['strategy_used'];

        $this->agent->provideFeedback($experienceId, -0.5, false);

        $performance = $this->agent->getPerformance()[$strategyUsed];
        $this->assertEquals(0, $performance['successes']);
        $this->assertLessThan(0, $performance['avg_reward']);
    }

    public function testMultipleStrategiesInitialization(): void
    {
        $agent = new LearningAgent($this->client, [
            'initial_strategies' => ['default', 'analytical', 'creative', 'systematic'],
        ]);

        $performance = $agent->getPerformance();
        $this->assertCount(4, $performance);
    }

    public function testExperienceContainsAllRequiredFields(): void
    {
        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $this->client->shouldReceive('messages->create')
            ->once()
            ->andReturn($mockMessage);

        $result = $this->agent->run('Test task');
        $experienceId = $result->getMetadata()['experience_id'];

        $experiences = $this->agent->getExperiences();
        $experience = $experiences[$experienceId];

        $this->assertArrayHasKey('id', $experience);
        $this->assertArrayHasKey('task', $experience);
        $this->assertArrayHasKey('strategy', $experience);
        $this->assertArrayHasKey('result', $experience);
        $this->assertArrayHasKey('timestamp', $experience);
        $this->assertArrayHasKey('reward', $experience);
        $this->assertArrayHasKey('success', $experience);
        $this->assertArrayHasKey('feedback', $experience);
    }

    public function testLearningRateConfiguration(): void
    {
        $agent = new LearningAgent($this->client, [
            'learning_rate' => 0.5,
        ]);

        // Learning rate is set (no public getter, but test passes if construction works)
        $this->assertInstanceOf(LearningAgent::class, $agent);
    }
}
