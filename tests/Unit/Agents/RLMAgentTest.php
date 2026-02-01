<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\RLM\REPLContext;
use ClaudeAgents\Agents\RLMAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RLMAgentTest extends TestCase
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
            model: 'claude-sonnet-4-5',
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
        $agent = new RLMAgent($this->mockClient);

        $this->assertEquals('rlm_agent', $agent->getName());
    }

    public function testConstructorWithCustomName(): void
    {
        $agent = new RLMAgent($this->mockClient, [
            'name' => 'custom_rlm',
        ]);

        $this->assertEquals('custom_rlm', $agent->getName());
    }

    public function testConstructorWithAllOptions(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $tool = Tool::create('test')->handler(fn() => 'result');

        $agent = new RLMAgent($this->mockClient, [
            'name' => 'my_rlm',
            'model' => 'claude-opus-4-5',
            'max_tokens' => 2048,
            'max_iterations' => 15,
            'max_recursion_depth' => 5,
            'tools' => [$tool],
            'system' => 'Custom system prompt',
            'thinking' => ['type' => 'enabled', 'budget_tokens' => 5000],
            'logger' => $logger,
        ]);

        $this->assertEquals('my_rlm', $agent->getName());
    }

    public function testAddTool(): void
    {
        $tool = Tool::create('calculator')
            ->description('A calculator')
            ->handler(fn(): int => 42);

        $agent = new RLMAgent($this->mockClient);
        $result = $agent->addTool($tool);

        $this->assertSame($agent, $result); // Fluent interface
    }

    public function testOnIterationCallback(): void
    {
        $agent = new RLMAgent($this->mockClient);
        $result = $agent->onIteration(fn() => null);

        $this->assertSame($agent, $result);
    }

    public function testOnToolExecutionCallback(): void
    {
        $agent = new RLMAgent($this->mockClient);
        $result = $agent->onToolExecution(fn() => null);

        $this->assertSame($agent, $result);
    }

    public function testOnRecursionCallback(): void
    {
        $agent = new RLMAgent($this->mockClient);
        $result = $agent->onRecursion(fn() => null);

        $this->assertSame($agent, $result);
    }

    public function testOnUpdateCallback(): void
    {
        $agent = new RLMAgent($this->mockClient);
        $result = $agent->onUpdate(fn() => null);

        $this->assertSame($agent, $result);
    }

    public function testRunWithInputSuccess(): void
    {
        $response = $this->createMockMessage([
            ['type' => 'text', 'text' => 'The answer is 42'],
        ]);

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $agent = new RLMAgent($this->mockClient);
        $result = $agent->runWithInput(
            'What is the meaning of life?',
            'Some long input data here'
        );

        $this->assertInstanceOf(AgentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('The answer is 42', $result->getAnswer());

        // Check RLM metadata
        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('rlm', $metadata);
        $this->assertArrayHasKey('input_chars', $metadata['rlm']);
        $this->assertArrayHasKey('input_lines', $metadata['rlm']);
    }

    public function testRunWithEmptyInput(): void
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

        $agent = new RLMAgent($this->mockClient);
        $result = $agent->runWithInput('Simple task', '');

        $this->assertTrue($result->isSuccess());
    }

    public function testRunUsesSameAsRunWithInput(): void
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

        $agent = new RLMAgent($this->mockClient);
        $result = $agent->run('A task');

        $this->assertTrue($result->isSuccess());
    }

    public function testRunWithToolUse(): void
    {
        // First response: use get_input_info tool
        $toolUseResponse = $this->createMockMessage(
            [
                ['type' => 'text', 'text' => 'Let me check the input'],
                [
                    'type' => 'tool_use',
                    'id' => 'tool_123',
                    'name' => 'get_input_info',
                    'input' => [],
                ],
            ],
            'tool_use'
        );

        // Final response
        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'The input has 4 lines']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->twice()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->twice()
            ->andReturn($toolUseResponse, $finalResponse);

        $agent = new RLMAgent($this->mockClient);
        $result = $agent->runWithInput(
            'How many lines?',
            "Line 1\nLine 2\nLine 3\nLine 4"
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(2, $result->getIterations());
    }

    public function testRunWithPeekTool(): void
    {
        // First response: use peek_input tool
        $toolUseResponse = $this->createMockMessage(
            [
                [
                    'type' => 'tool_use',
                    'id' => 'tool_123',
                    'name' => 'peek_input',
                    'input' => ['start' => 0, 'length' => 20],
                ],
            ],
            'tool_use'
        );

        // Final response
        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'The input starts with "Hello World"']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->twice()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->twice()
            ->andReturn($toolUseResponse, $finalResponse);

        $agent = new RLMAgent($this->mockClient);
        $result = $agent->runWithInput(
            'What does the input start with?',
            'Hello World, this is a test'
        );

        $this->assertTrue($result->isSuccess());
    }

    public function testRunWithSearchTool(): void
    {
        // First response: use search_input tool
        $toolUseResponse = $this->createMockMessage(
            [
                [
                    'type' => 'tool_use',
                    'id' => 'tool_123',
                    'name' => 'search_input',
                    'input' => ['pattern' => '/error/i'],
                ],
            ],
            'tool_use'
        );

        // Final response
        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'Found error on line 2']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->twice()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->twice()
            ->andReturn($toolUseResponse, $finalResponse);

        $agent = new RLMAgent($this->mockClient);
        $result = $agent->runWithInput(
            'Find errors in the log',
            "INFO: Started\nERROR: Something went wrong\nINFO: Finished"
        );

        $this->assertTrue($result->isSuccess());
    }

    public function testGetCurrentContextIsNullWhenNotExecuting(): void
    {
        $agent = new RLMAgent($this->mockClient);

        $this->assertNull($agent->getCurrentContext());
    }

    public function testResolveInputSourceFull(): void
    {
        $agent = new RLMAgent($this->mockClient);
        $context = new REPLContext('test input');

        $result = $agent->resolveInputSource('full', $context);

        $this->assertEquals('test input', $result);
    }

    public function testResolveInputSourceSlice(): void
    {
        $agent = new RLMAgent($this->mockClient);
        $context = new REPLContext("Line 1\nLine 2\nLine 3");

        $result = $agent->resolveInputSource('slice:2:3', $context);

        $this->assertStringContainsString('Line 2', $result);
        $this->assertStringContainsString('Line 3', $result);
    }

    public function testResolveInputSourceVariable(): void
    {
        $agent = new RLMAgent($this->mockClient);
        $context = new REPLContext('original');
        $context->setVariable('extracted', 'stored value');

        $result = $agent->resolveInputSource('variable:extracted', $context);

        $this->assertEquals('stored value', $result);
    }

    public function testResolveInputSourceInvalid(): void
    {
        $agent = new RLMAgent($this->mockClient);
        $context = new REPLContext('test');

        $result = $agent->resolveInputSource('invalid:format', $context);

        $this->assertNull($result);
    }

    public function testMetadataContainsRLMInfo(): void
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

        $agent = new RLMAgent($this->mockClient);
        $result = $agent->runWithInput('Task', "Input with\nmultiple\nlines here");

        $metadata = $result->getMetadata();

        $this->assertArrayHasKey('rlm', $metadata);
        $this->assertEquals(3, $metadata['rlm']['input_lines']);
        $this->assertGreaterThan(0, $metadata['rlm']['input_words']);
        $this->assertArrayHasKey('recursion_depth', $metadata['rlm']);
        $this->assertArrayHasKey('recursion_history', $metadata['rlm']);
        $this->assertArrayHasKey('variables', $metadata['rlm']);
    }

    public function testFluentInterface(): void
    {
        $tool = Tool::create('test')->handler(fn(): string => 'result');

        $agent = (new RLMAgent($this->mockClient))
            ->addTool($tool)
            ->onIteration(fn() => null)
            ->onToolExecution(fn() => null)
            ->onRecursion(fn() => null)
            ->onUpdate(fn() => null);

        $this->assertInstanceOf(RLMAgent::class, $agent);
    }

    public function testWithCustomTools(): void
    {
        $customTool = Tool::create('custom_analyzer')
            ->description('Custom analysis tool')
            ->stringParam('data', 'Data to analyze')
            ->handler(fn(array $input): string => 'Analysis result');

        // First response: use custom tool
        $toolUseResponse = $this->createMockMessage(
            [
                [
                    'type' => 'tool_use',
                    'id' => 'tool_123',
                    'name' => 'custom_analyzer',
                    'input' => ['data' => 'test'],
                ],
            ],
            'tool_use'
        );

        // Final response
        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'Custom analysis complete']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->twice()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->twice()
            ->andReturn($toolUseResponse, $finalResponse);

        $agent = new RLMAgent($this->mockClient, [
            'tools' => [$customTool],
        ]);

        $result = $agent->runWithInput('Analyze this', 'Some data');

        $this->assertTrue($result->isSuccess());
    }

    public function testMaxRecursionDepthSetting(): void
    {
        $agent = new RLMAgent($this->mockClient, [
            'max_recursion_depth' => 3,
        ]);

        // We can't directly verify this, but we can ensure the agent creates correctly
        $this->assertInstanceOf(RLMAgent::class, $agent);
    }

    public function testWithThinkingEnabled(): void
    {
        $response = $this->createMockMessage([
            ['type' => 'text', 'text' => 'Done with thinking'],
        ]);

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $agent = new RLMAgent($this->mockClient, [
            'thinking' => [
                'type' => 'enabled',
                'budget_tokens' => 10000,
            ],
        ]);

        $result = $agent->run('Complex task');

        $this->assertTrue($result->isSuccess());
    }
}
