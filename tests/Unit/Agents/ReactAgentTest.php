<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\Agent;
use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Progress\AgentUpdate;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReactAgentTest extends TestCase
{
    private ClaudePhp $mockClient;
    private Messages $mockMessages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(ClaudePhp::class);
        $this->mockMessages = Mockery::mock(Messages::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createMockMessage(
        array $content,
        string $stopReason = 'end_turn',
        int $inputTokens = 10,
        int $outputTokens = 5
    ): Message {
        return new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: $content,
            model: 'claude-3-5-sonnet-20241022',
            stop_reason: $stopReason,
            stop_sequence: null,
            usage: new Usage(
                input_tokens: $inputTokens,
                output_tokens: $outputTokens
            )
        );
    }

    public function testConstructorWithDefaultOptions(): void
    {
        $agent = new ReactAgent($this->mockClient);

        $this->assertEquals('react_agent', $agent->getName());
        $this->assertInstanceOf(Agent::class, $agent->getAgent());
    }

    public function testConstructorWithCustomName(): void
    {
        $agent = new ReactAgent($this->mockClient, [
            'name' => 'custom_react',
        ]);

        $this->assertEquals('custom_react', $agent->getName());
    }

    public function testConstructorWithTools(): void
    {
        $tool = Tool::create('test_tool')
            ->description('A test tool')
            ->handler(fn (): string => 'result');

        $agent = new ReactAgent($this->mockClient, [
            'tools' => [$tool],
        ]);

        $this->assertNotNull($agent->getAgent());
    }

    public function testConstructorWithSystemPrompt(): void
    {
        $systemPrompt = 'You are a helpful assistant.';
        $agent = new ReactAgent($this->mockClient, [
            'system' => $systemPrompt,
        ]);

        $config = $agent->getAgent()->getConfig();
        $this->assertEquals($systemPrompt, $config->getSystemPrompt());
    }

    public function testConstructorWithModel(): void
    {
        $model = 'claude-3-opus-20240229';
        $agent = new ReactAgent($this->mockClient, [
            'model' => $model,
        ]);

        $config = $agent->getAgent()->getConfig();
        $this->assertEquals($model, $config->getModel());
    }

    public function testConstructorWithMaxIterations(): void
    {
        $agent = new ReactAgent($this->mockClient, [
            'max_iterations' => 15,
        ]);

        $config = $agent->getAgent()->getConfig();
        $this->assertEquals(15, $config->getMaxIterations());
    }

    public function testConstructorWithMaxTokens(): void
    {
        $agent = new ReactAgent($this->mockClient, [
            'max_tokens' => 2048,
        ]);

        $config = $agent->getAgent()->getConfig();
        $this->assertEquals(2048, $config->getMaxTokens());
    }

    public function testConstructorWithThinking(): void
    {
        $agent = new ReactAgent($this->mockClient, [
            'thinking' => [
                'type' => 'enabled',
                'budget_tokens' => 5000,
            ],
        ]);

        $config = $agent->getAgent()->getConfig();
        $thinking = $config->getThinking();
        $this->assertEquals('enabled', $thinking['type']);
        $this->assertEquals(5000, $thinking['budget_tokens']);
    }

    public function testConstructorWithLogger(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $agent = new ReactAgent($this->mockClient, [
            'logger' => $logger,
        ]);

        $this->assertNotNull($agent->getAgent());
    }

    public function testAddTool(): void
    {
        $tool = Tool::create('calculator')
            ->description('A calculator')
            ->handler(fn (): int => 42);

        $agent = new ReactAgent($this->mockClient);
        $result = $agent->addTool($tool);

        $this->assertSame($agent, $result); // Fluent interface

        $tools = $agent->getAgent()->getTools();
        $this->assertTrue($tools->has('calculator'));
    }

    public function testAddMultipleTools(): void
    {
        $tool1 = Tool::create('tool1')->handler(fn (): string => 'a');
        $tool2 = Tool::create('tool2')->handler(fn (): string => 'b');

        $agent = new ReactAgent($this->mockClient);
        $agent->addTool($tool1)
              ->addTool($tool2);

        $tools = $agent->getAgent()->getTools();
        $this->assertTrue($tools->has('tool1'));
        $this->assertTrue($tools->has('tool2'));
    }

    public function testOnIteration(): void
    {
        $callbackCalled = false;
        $agent = new ReactAgent($this->mockClient);

        $result = $agent->onIteration(function () use (&$callbackCalled) {
            $callbackCalled = true;
        });

        $this->assertSame($agent, $result); // Fluent interface
    }

    public function testOnToolExecution(): void
    {
        $callbackCalled = false;
        $agent = new ReactAgent($this->mockClient);

        $result = $agent->onToolExecution(function () use (&$callbackCalled) {
            $callbackCalled = true;
        });

        $this->assertSame($agent, $result); // Fluent interface
    }

    public function testOnUpdate(): void
    {
        $callbackCalled = false;
        $agent = new ReactAgent($this->mockClient);

        $result = $agent->onUpdate(function () use (&$callbackCalled) {
            $callbackCalled = true;
        });

        $this->assertSame($agent, $result); // Fluent interface
    }

    public function testUpdateCallbackEmitsEventsDuringRun(): void
    {
        $response = $this->createMockMessage([
            ['type' => 'text', 'text' => 'Task completed'],
        ]);

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $updates = [];
        $types = [];

        $agent = new ReactAgent($this->mockClient);
        $agent->onUpdate(function (AgentUpdate $update) use (&$updates, &$types): void {
            $updates[] = $update;
            $types[] = $update->getType();
        });

        $agent->run('Test task');

        $this->assertContains('agent.start', $types);
        $this->assertContains('llm.iteration', $types);
        $this->assertContains('agent.completed', $types);

        // Ensure iteration payload is present
        $iterationUpdates = array_values(array_filter($updates, fn (AgentUpdate $u) => $u->getType() === 'llm.iteration'));
        $this->assertNotEmpty($iterationUpdates);
        $data = $iterationUpdates[0]->getData();
        $this->assertSame(1, $data['iteration']);
        $this->assertArrayHasKey('text', $data);
    }

    public function testRun(): void
    {
        $response = $this->createMockMessage([
            ['type' => 'text', 'text' => 'Task completed'],
        ]);

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $agent = new ReactAgent($this->mockClient);
        $result = $agent->run('Test task');

        $this->assertInstanceOf(AgentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Task completed', $result->getAnswer());
    }

    public function testRunWithTool(): void
    {
        $tool = Tool::create('calculator')
            ->numberParam('a', 'First number')
            ->numberParam('b', 'Second number')
            ->handler(fn (array $input): int => $input['a'] + $input['b']);

        $toolUseResponse = $this->createMockMessage(
            [
                ['type' => 'text', 'text' => 'Let me calculate'],
                [
                    'type' => 'tool_use',
                    'id' => 'tool_123',
                    'name' => 'calculator',
                    'input' => ['a' => 5, 'b' => 3],
                ],
            ],
            'tool_use'
        );

        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'The result is 8']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->twice()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->twice()
            ->andReturn($toolUseResponse, $finalResponse);

        $agent = new ReactAgent($this->mockClient);
        $agent->addTool($tool);

        $result = $agent->run('Calculate 5 + 3');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('The result is 8', $result->getAnswer());
        $this->assertEquals(2, $result->getIterations());

        $toolCalls = $result->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertEquals('calculator', $toolCalls[0]['tool']);
    }

    public function testIterationCallback(): void
    {
        $response = $this->createMockMessage([
            ['type' => 'text', 'text' => 'Done'],
        ]);

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $callbackCalled = false;
        $callbackIteration = 0;

        $agent = new ReactAgent($this->mockClient);
        $agent->onIteration(function ($iteration) use (&$callbackCalled, &$callbackIteration) {
            $callbackCalled = true;
            $callbackIteration = $iteration;
        });

        $agent->run('Test');

        $this->assertTrue($callbackCalled);
        $this->assertEquals(1, $callbackIteration);
    }

    public function testToolExecutionCallback(): void
    {
        $tool = Tool::create('test')
            ->handler(fn (): string => 'result');

        $toolUseResponse = $this->createMockMessage(
            [
                [
                    'type' => 'tool_use',
                    'id' => 'tool_123',
                    'name' => 'test',
                    'input' => [],
                ],
            ],
            'tool_use'
        );

        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'Done']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->twice()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->twice()
            ->andReturn($toolUseResponse, $finalResponse);

        $callbackCalled = false;
        $callbackToolName = '';

        $agent = new ReactAgent($this->mockClient, ['tools' => [$tool]]);
        $agent->onToolExecution(function ($toolName) use (&$callbackCalled, &$callbackToolName) {
            $callbackCalled = true;
            $callbackToolName = $toolName;
        });

        $agent->run('Test');

        $this->assertTrue($callbackCalled);
        $this->assertEquals('test', $callbackToolName);
    }

    public function testGetName(): void
    {
        $agent = new ReactAgent($this->mockClient, ['name' => 'my_agent']);
        $this->assertEquals('my_agent', $agent->getName());
    }

    public function testGetAgent(): void
    {
        $agent = new ReactAgent($this->mockClient);
        $underlyingAgent = $agent->getAgent();

        $this->assertInstanceOf(Agent::class, $underlyingAgent);
    }

    public function testFluentInterface(): void
    {
        $tool = Tool::create('test')->handler(fn (): string => 'result');

        $agent = (new ReactAgent($this->mockClient))
            ->addTool($tool)
            ->onIteration(fn () => null)
            ->onToolExecution(fn () => null);

        $this->assertInstanceOf(ReactAgent::class, $agent);
    }

    public function testTokenUsageTracking(): void
    {
        $response = $this->createMockMessage(
            [['type' => 'text', 'text' => 'Done']],
            'end_turn',
            100,
            50
        );

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $agent = new ReactAgent($this->mockClient);
        $result = $agent->run('Test');

        $usage = $result->getTokenUsage();
        $this->assertEquals(100, $usage['input']);
        $this->assertEquals(50, $usage['output']);
        $this->assertEquals(150, $usage['total']);
    }

    public function testMaxIterationsLimit(): void
    {
        $response = $this->createMockMessage(
            [
                [
                    'type' => 'tool_use',
                    'id' => 'tool_123',
                    'name' => 'test',
                    'input' => [],
                ],
            ],
            'tool_use'
        );

        $this->mockClient->shouldReceive('messages')
            ->times(2)
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->times(2)
            ->andReturn($response, $response);

        $agent = new ReactAgent($this->mockClient, [
            'max_iterations' => 2,
        ]);

        $result = $agent->run('Test');

        // Agent should complete but may have an error due to max iterations
        $this->assertEquals(2, $result->getIterations());
    }

    public function testComplexScenarioWithMultipleToolCalls(): void
    {
        $searchTool = Tool::create('search')
            ->stringParam('query', 'Search query')
            ->handler(fn (array $input): string => "Results for: {$input['query']}");

        $analyzeTool = Tool::create('analyze')
            ->stringParam('data', 'Data to analyze')
            ->handler(fn (array $input): string => 'Analysis complete');

        // First iteration: search
        $response1 = $this->createMockMessage(
            [
                ['type' => 'text', 'text' => 'Searching...'],
                [
                    'type' => 'tool_use',
                    'id' => 'tool_1',
                    'name' => 'search',
                    'input' => ['query' => 'PHP agents'],
                ],
            ],
            'tool_use'
        );

        // Second iteration: analyze
        $response2 = $this->createMockMessage(
            [
                ['type' => 'text', 'text' => 'Analyzing...'],
                [
                    'type' => 'tool_use',
                    'id' => 'tool_2',
                    'name' => 'analyze',
                    'input' => ['data' => 'search results'],
                ],
            ],
            'tool_use'
        );

        // Final iteration: answer
        $response3 = $this->createMockMessage(
            [['type' => 'text', 'text' => 'Analysis shows interesting patterns']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->times(3)
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->times(3)
            ->andReturn($response1, $response2, $response3);

        $agent = new ReactAgent($this->mockClient, [
            'tools' => [$searchTool, $analyzeTool],
        ]);

        $result = $agent->run('Search and analyze PHP agents');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(3, $result->getIterations());
        $this->assertCount(2, $result->getToolCalls());
    }
}
