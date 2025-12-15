<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Loops;

use ClaudeAgents\AgentContext;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Loops\ReactLoop;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReactLoopTest extends TestCase
{
    private ReactLoop $loop;
    private ClaudePhp $mockClient;
    private Messages $mockMessages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loop = new ReactLoop();
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

    public function testGetName(): void
    {
        $this->assertEquals('react', $this->loop->getName());
    }

    public function testExecuteCompletesOnEndTurn(): void
    {
        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test task',
            tools: [],
            config: $config
        );

        $response = $this->createMockMessage([
            ['type' => 'text', 'text' => 'Final answer'],
        ]);

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $result = $this->loop->execute($context);

        $this->assertTrue($result->isCompleted());
        $this->assertEquals('Final answer', $result->getAnswer());
        $this->assertEquals(1, $result->getIteration());
    }

    public function testExecuteTracksTokenUsage(): void
    {
        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $config
        );

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

        $result = $this->loop->execute($context);

        $usage = $result->getTokenUsage();
        $this->assertEquals(100, $usage['input']);
        $this->assertEquals(50, $usage['output']);
        $this->assertEquals(150, $usage['total']);
    }

    public function testExecuteWithToolUse(): void
    {
        $tool = Tool::create('calculator')
            ->numberParam('a', 'First number')
            ->numberParam('b', 'Second number')
            ->handler(fn (array $input): int => $input['a'] + $input['b']);

        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Calculate 5+3',
            tools: [$tool],
            config: $config
        );

        // First response with tool use
        $toolUseResponse = $this->createMockMessage(
            [
                ['type' => 'text', 'text' => 'Let me calculate that'],
                [
                    'type' => 'tool_use',
                    'id' => 'tool_123',
                    'name' => 'calculator',
                    'input' => ['a' => 5, 'b' => 3],
                ],
            ],
            'tool_use'
        );

        // Second response with final answer
        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'The answer is 8']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->twice()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->twice()
            ->andReturn($toolUseResponse, $finalResponse);

        $result = $this->loop->execute($context);

        $this->assertTrue($result->isCompleted());
        $this->assertEquals('The answer is 8', $result->getAnswer());
        $this->assertEquals(2, $result->getIteration());

        $toolCalls = $result->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertEquals('calculator', $toolCalls[0]['tool']);
        $this->assertEquals(8, (int)$toolCalls[0]['result']);
    }

    public function testExecuteWithUnknownTool(): void
    {
        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $config
        );

        $toolUseResponse = $this->createMockMessage(
            [
                [
                    'type' => 'tool_use',
                    'id' => 'tool_123',
                    'name' => 'unknown_tool',
                    'input' => [],
                ],
            ],
            'tool_use'
        );

        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'Error handled']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->twice()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->twice()
            ->andReturn($toolUseResponse, $finalResponse);

        $result = $this->loop->execute($context);

        $toolCalls = $result->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertTrue($toolCalls[0]['is_error']);
        $this->assertStringContainsString('Unknown tool', $toolCalls[0]['result']);
    }

    public function testExecuteStopsAtMaxIterations(): void
    {
        $config = AgentConfig::fromArray(['max_iterations' => 2]);
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $config
        );

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
            ->twice()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->twice()
            ->andReturn($response, $response);

        $result = $this->loop->execute($context);

        $this->assertTrue($result->isCompleted());
        $this->assertTrue($result->hasFailed());
        $this->assertStringContainsString('Maximum iterations', $result->getError());
        $this->assertEquals(2, $result->getIteration());
    }

    public function testIterationCallback(): void
    {
        $callbackCalled = false;
        $callbackIteration = 0;

        $this->loop->onIteration(function ($iteration, $response, $context) use (&$callbackCalled, &$callbackIteration) {
            $callbackCalled = true;
            $callbackIteration = $iteration;
        });

        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $config
        );

        $response = $this->createMockMessage([
            ['type' => 'text', 'text' => 'Done'],
        ]);

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $this->loop->execute($context);

        $this->assertTrue($callbackCalled);
        $this->assertEquals(1, $callbackIteration);
    }

    public function testToolExecutionCallback(): void
    {
        $tool = Tool::create('test')
            ->handler(fn (): string => 'result');

        $callbackCalled = false;
        $callbackToolName = '';

        $this->loop->onToolExecution(function ($toolName, $input, $result) use (&$callbackCalled, &$callbackToolName) {
            $callbackCalled = true;
            $callbackToolName = $toolName;
        });

        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [$tool],
            config: $config
        );

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

        $this->loop->execute($context);

        $this->assertTrue($callbackCalled);
        $this->assertEquals('test', $callbackToolName);
    }

    public function testExecuteHandlesException(): void
    {
        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $config
        );

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andThrow(new \RuntimeException('API Error'));

        $result = $this->loop->execute($context);

        $this->assertTrue($result->hasFailed());
        $this->assertStringContainsString('API Error', $result->getError());
    }

    public function testExtractTextContentFromMultipleBlocks(): void
    {
        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $config
        );

        $response = $this->createMockMessage([
            ['type' => 'text', 'text' => 'First part'],
            ['type' => 'text', 'text' => 'Second part'],
            ['type' => 'text', 'text' => 'Third part'],
        ]);

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $result = $this->loop->execute($context);

        $this->assertEquals("First part\nSecond part\nThird part", $result->getAnswer());
    }

    public function testMessagesAccumulate(): void
    {
        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Test',
            tools: [],
            config: $config
        );

        $response = $this->createMockMessage([
            ['type' => 'text', 'text' => 'Done'],
        ]);

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $result = $this->loop->execute($context);

        $messages = $result->getMessages();
        $this->assertCount(2, $messages); // User task + assistant response
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('assistant', $messages[1]['role']);
    }
}
