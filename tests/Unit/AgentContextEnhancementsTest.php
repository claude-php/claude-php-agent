<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\AgentContext;
use ClaudeAgents\Config\AgentConfig;
use ClaudePhp\ClaudePhp;
use Mockery;
use PHPUnit\Framework\TestCase;

class AgentContextEnhancementsTest extends TestCase
{
    private ClaudePhp $mockClient;
    private AgentConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(ClaudePhp::class);
        $this->config = new AgentConfig();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testTimeTracking(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        // Should have a start time immediately
        $this->assertGreaterThan(0, $context->getStartTime());
        $this->assertNull($context->getEndTime());

        // Execution time should be very small initially
        $executionTime = $context->getExecutionTime();
        $this->assertGreaterThanOrEqual(0, $executionTime);
        $this->assertLessThan(1, $executionTime); // Should be less than 1 second

        usleep(100000); // Sleep 100ms

        // Execution time should have increased
        $this->assertGreaterThan($executionTime, $context->getExecutionTime());
    }

    public function testTimeTrackingOnComplete(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        usleep(1000); // Small delay to ensure different timestamps
        $context->complete('Done');

        $this->assertNotNull($context->getEndTime());
        $this->assertGreaterThanOrEqual($context->getStartTime(), $context->getEndTime());
    }

    public function testTimePerIteration(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        // No iterations yet
        $this->assertEquals(0.0, $context->getTimePerIteration());

        $context->incrementIteration();
        usleep(50000); // 50ms
        $context->incrementIteration();

        $timePerIter = $context->getTimePerIteration();
        $this->assertGreaterThan(0, $timePerIter);
    }

    public function testSetMessages(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $newMessages = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi'],
        ];

        $context->setMessages($newMessages);

        $this->assertCount(2, $context->getMessages());
        $this->assertEquals($newMessages, $context->getMessages());
    }

    public function testClearMessages(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Original task',
            tools: [],
            config: $this->config,
        );

        $context->addMessage(['role' => 'assistant', 'content' => 'Response']);
        $context->addMessage(['role' => 'user', 'content' => 'Follow-up']);

        $this->assertCount(3, $context->getMessages());

        $context->clearMessages();

        // Should only have the original task message
        $this->assertCount(1, $context->getMessages());
        $this->assertEquals('user', $context->getMessages()[0]['role']);
        $this->assertEquals('Original task', $context->getMessages()[0]['content']);
    }

    public function testRemoveMessage(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $context->addMessage(['role' => 'assistant', 'content' => 'First']);
        $context->addMessage(['role' => 'user', 'content' => 'Second']);

        $this->assertCount(3, $context->getMessages());

        $context->removeMessage(1); // Remove 'First'

        $this->assertCount(2, $context->getMessages());
        $this->assertEquals('Second', $context->getMessages()[1]['content']);
    }

    public function testReplaceLastMessage(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $context->addMessage(['role' => 'assistant', 'content' => 'Wrong']);

        $context->replaceLastMessage(['role' => 'assistant', 'content' => 'Correct']);

        $messages = $context->getMessages();
        $this->assertEquals('Correct', $messages[count($messages) - 1]['content']);
    }

    public function testCheckpoints(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $context->addMessage(['role' => 'assistant', 'content' => 'First']);
        $context->incrementIteration();

        $checkpointId = $context->createCheckpoint();

        $this->assertTrue($context->hasCheckpoint($checkpointId));
        $this->assertNotEmpty($context->getCheckpoints());

        // Make changes
        $context->addMessage(['role' => 'user', 'content' => 'Second']);
        $context->incrementIteration();

        $this->assertCount(3, $context->getMessages());
        $this->assertEquals(2, $context->getIteration());

        // Restore checkpoint
        $context->restoreCheckpoint($checkpointId);

        $this->assertCount(2, $context->getMessages());
        $this->assertEquals(1, $context->getIteration());
    }

    public function testCheckpointWithCustomId(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $id = $context->createCheckpoint('my-checkpoint');

        $this->assertEquals('my-checkpoint', $id);
        $this->assertTrue($context->hasCheckpoint('my-checkpoint'));
    }

    public function testRestoreNonExistentCheckpoint(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Checkpoint 'missing' not found");

        $context->restoreCheckpoint('missing');
    }

    public function testDeleteCheckpoint(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $id = $context->createCheckpoint();
        $this->assertTrue($context->hasCheckpoint($id));

        $context->deleteCheckpoint($id);
        $this->assertFalse($context->hasCheckpoint($id));
    }

    public function testFork(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $context->addMessage(['role' => 'assistant', 'content' => 'Original']);
        $context->incrementIteration();
        $context->addTokenUsage(100, 50);

        $forked = $context->fork();

        // Should be different instances
        $this->assertNotSame($context, $forked);

        // But have the same state
        $this->assertEquals($context->getTask(), $forked->getTask());
        $this->assertEquals($context->getMessages(), $forked->getMessages());
        $this->assertEquals($context->getIteration(), $forked->getIteration());
        $this->assertEquals($context->getTokenUsage(), $forked->getTokenUsage());

        // Changes to fork don't affect original
        $forked->addMessage(['role' => 'user', 'content' => 'Forked']);

        $this->assertCount(2, $context->getMessages());
        $this->assertCount(3, $forked->getMessages());
    }

    public function testToArray(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test task',
            tools: [],
            config: $this->config,
        );

        $context->incrementIteration();
        $context->addTokenUsage(100, 50);
        $context->complete('Done');

        $array = $context->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Test task', $array['task']);
        $this->assertEquals(1, $array['iteration']);
        $this->assertTrue($array['completed']);
        $this->assertEquals('Done', $array['answer']);
        $this->assertArrayHasKey('execution_time', $array);
        $this->assertArrayHasKey('token_usage', $array);
    }

    public function testToString(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $context->incrementIteration();
        $context->addMessage(['role' => 'assistant', 'content' => 'Response']);

        $string = (string) $context;

        $this->assertStringContainsString('AgentContext', $string);
        $this->assertStringContainsString('In Progress', $string);
        $this->assertStringContainsString('1 iterations', $string);
        $this->assertStringContainsString('2 messages', $string);

        $context->complete('Done');
        $string = (string) $context;

        $this->assertStringContainsString('Completed', $string);
    }

    public function testToResultIncludesExecutionTime(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        usleep(10000); // 10ms
        $context->complete('Done');

        $result = $context->toResult();

        $this->assertTrue($result->hasMetadata('execution_time'));
        $this->assertGreaterThan(0, $result->getMetadataValue('execution_time'));
        $this->assertTrue($result->hasMetadata('start_time'));
        $this->assertTrue($result->hasMetadata('end_time'));
    }
}
