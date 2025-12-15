<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\AgentContext;
use ClaudeAgents\AgentResult;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Mockery;
use PHPUnit\Framework\TestCase;

class AgentContextTest extends TestCase
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

    public function testConstructorInitializesWithTask(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test task',
            tools: [],
            config: $this->config,
        );

        $this->assertEquals('Test task', $context->getTask());
        $this->assertCount(1, $context->getMessages());
        $this->assertEquals('user', $context->getMessages()[0]['role']);
        $this->assertEquals('Test task', $context->getMessages()[0]['content']);
    }

    public function testGetClient(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $this->assertSame($this->mockClient, $context->getClient());
    }

    public function testGetTools(): void
    {
        $tool1 = Tool::create('tool1');
        $tool2 = Tool::create('tool2');
        $tools = [$tool1, $tool2];

        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: $tools,
            config: $this->config,
        );

        $this->assertCount(2, $context->getTools());
        $this->assertSame($tool1, $context->getTools()[0]);
    }

    public function testGetToolDefinitions(): void
    {
        $tool = Tool::create('calculator')
            ->description('Performs calculations')
            ->numberParam('a', 'First number')
            ->numberParam('b', 'Second number');

        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [$tool],
            config: $this->config,
        );

        $definitions = $context->getToolDefinitions();

        $this->assertCount(1, $definitions);
        $this->assertEquals('calculator', $definitions[0]['name']);
        $this->assertArrayHasKey('input_schema', $definitions[0]);
    }

    public function testGetToolByName(): void
    {
        $tool = Tool::create('search')->description('Search tool');

        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [$tool],
            config: $this->config,
        );

        $found = $context->getTool('search');
        $this->assertNotNull($found);
        $this->assertEquals('search', $found->getName());

        $notFound = $context->getTool('nonexistent');
        $this->assertNull($notFound);
    }

    public function testAddMessage(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $context->addMessage(['role' => 'assistant', 'content' => 'Response']);

        $messages = $context->getMessages();
        $this->assertCount(2, $messages);
        $this->assertEquals('assistant', $messages[1]['role']);
        $this->assertEquals('Response', $messages[1]['content']);
    }

    public function testIterationTracking(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $this->assertEquals(0, $context->getIteration());

        $context->incrementIteration();
        $this->assertEquals(1, $context->getIteration());

        $context->incrementIteration();
        $this->assertEquals(2, $context->getIteration());
    }

    public function testMaxIterationsCheck(): void
    {
        $config = AgentConfig::fromArray(['max_iterations' => 3]);

        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $config,
        );

        $this->assertFalse($context->hasReachedMaxIterations());

        $context->incrementIteration();
        $context->incrementIteration();
        $context->incrementIteration();

        $this->assertTrue($context->hasReachedMaxIterations());
    }

    public function testCompleteWithAnswer(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $this->assertFalse($context->isCompleted());
        $this->assertNull($context->getAnswer());

        $context->complete('Final answer');

        $this->assertTrue($context->isCompleted());
        $this->assertEquals('Final answer', $context->getAnswer());
        $this->assertFalse($context->hasFailed());
    }

    public function testFailWithError(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $this->assertFalse($context->hasFailed());
        $this->assertNull($context->getError());

        $context->fail('Error occurred');

        $this->assertTrue($context->isCompleted());
        $this->assertTrue($context->hasFailed());
        $this->assertEquals('Error occurred', $context->getError());
    }

    public function testRecordToolCall(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $context->recordToolCall('calculator', ['a' => 1, 'b' => 2], '3', false);

        $toolCalls = $context->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertEquals('calculator', $toolCalls[0]['tool']);
        $this->assertEquals(['a' => 1, 'b' => 2], $toolCalls[0]['input']);
        $this->assertEquals('3', $toolCalls[0]['result']);
        $this->assertFalse($toolCalls[0]['is_error']);
        $this->assertEquals(0, $toolCalls[0]['iteration']);
        $this->assertArrayHasKey('timestamp', $toolCalls[0]);
    }

    public function testTokenUsageTracking(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $usage = $context->getTokenUsage();
        $this->assertEquals(0, $usage['input']);
        $this->assertEquals(0, $usage['output']);
        $this->assertEquals(0, $usage['total']);

        $context->addTokenUsage(100, 50);
        $usage = $context->getTokenUsage();
        $this->assertEquals(100, $usage['input']);
        $this->assertEquals(50, $usage['output']);
        $this->assertEquals(150, $usage['total']);

        $context->addTokenUsage(20, 10);
        $usage = $context->getTokenUsage();
        $this->assertEquals(120, $usage['input']);
        $this->assertEquals(60, $usage['output']);
        $this->assertEquals(180, $usage['total']);
    }

    public function testToResultSuccess(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $context->incrementIteration();
        $context->addTokenUsage(100, 50);
        $context->recordToolCall('test', [], 'result', false);
        $context->complete('Success');

        $result = $context->toResult();

        $this->assertInstanceOf(AgentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Success', $result->getAnswer());
        $this->assertEquals(1, $result->getIterations());
        $this->assertEquals(150, $result->getTokenUsage()['total']);
        $this->assertCount(1, $result->getToolCalls());
    }

    public function testToResultFailure(): void
    {
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $this->config,
        );

        $context->incrementIteration();
        $context->fail('Test error');

        $result = $context->toResult();

        $this->assertInstanceOf(AgentResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Test error', $result->getError());
        $this->assertEquals(1, $result->getIterations());
    }
}
