<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Loops;

use ClaudeAgents\AgentContext;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Context\ContextManager;
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
            model: 'claude-sonnet-4-5',
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
            ->handler(fn(array $input): int => $input['a'] + $input['b']);

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
            ->handler(fn(): string => 'result');

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

    public function testToolUseWithEmptyInputEncodesAsJsonObject(): void
    {
        $tool = Tool::create('get_time')
            ->handler(fn(): string => '2024-01-01 12:00:00');

        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'What time is it?',
            tools: [$tool],
            config: $config
        );

        // Simulate API response with empty input {} (decoded as [] by json_decode)
        $toolUseResponse = $this->createMockMessage(
            [
                [
                    'type' => 'tool_use',
                    'id' => 'tool_456',
                    'name' => 'get_time',
                    'input' => [], // This is what json_decode($json, true) produces for {}
                ],
            ],
            'tool_use'
        );

        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'The time is 2024-01-01 12:00:00']],
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

        // Verify that the assistant message content normalizes tool_use.input
        // so json_encode produces {} instead of []
        $messages = $result->getMessages();
        $assistantMessage = $messages[1];
        $this->assertEquals('assistant', $assistantMessage['role']);

        $toolUseBlock = $assistantMessage['content'][0];
        $this->assertEquals('tool_use', $toolUseBlock['type']);

        // The input should be a stdClass (object) so json_encode outputs {}
        $this->assertInstanceOf(\stdClass::class, $toolUseBlock['input']);
        $this->assertEquals('{}', json_encode($toolUseBlock['input']));
    }

    public function testToolUseWithNonEmptyInputPreservesValues(): void
    {
        $tool = Tool::create('calculator')
            ->numberParam('a', 'First number')
            ->handler(fn(array $input): int => $input['a'] * 2);

        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Double 5',
            tools: [$tool],
            config: $config
        );

        $toolUseResponse = $this->createMockMessage(
            [
                [
                    'type' => 'tool_use',
                    'id' => 'tool_789',
                    'name' => 'calculator',
                    'input' => ['a' => 5],
                ],
            ],
            'tool_use'
        );

        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'The answer is 10']],
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

        // Verify non-empty input is still correctly encoded as a JSON object
        $messages = $result->getMessages();
        $assistantMessage = $messages[1];
        $toolUseBlock = $assistantMessage['content'][0];

        $this->assertInstanceOf(\stdClass::class, $toolUseBlock['input']);
        $encoded = json_encode($toolUseBlock['input']);
        $this->assertEquals('{"a":5}', $encoded);
    }

    public function testToolExceptionProducesErrorToolResult(): void
    {
        $tool = Tool::create('failing_tool')
            ->handler(function (array $input): string {
                throw new \RuntimeException('Something went wrong');
            });

        $config = new AgentConfig();
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Use failing tool',
            tools: [$tool],
            config: $config
        );

        $toolUseResponse = $this->createMockMessage(
            [
                [
                    'type' => 'tool_use',
                    'id' => 'tool_err',
                    'name' => 'failing_tool',
                    'input' => [],
                ],
            ],
            'tool_use'
        );

        $finalResponse = $this->createMockMessage(
            [['type' => 'text', 'text' => 'The tool failed, sorry.']],
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

        // Verify the tool_result was still added despite the exception
        $messages = $result->getMessages();
        // Messages: [0] user task, [1] assistant tool_use, [2] user tool_result, [3] assistant final
        $this->assertCount(4, $messages);

        $toolResultMessage = $messages[2];
        $this->assertEquals('user', $toolResultMessage['role']);
        $this->assertCount(1, $toolResultMessage['content']);
        $this->assertEquals('tool_result', $toolResultMessage['content'][0]['type']);
        $this->assertEquals('tool_err', $toolResultMessage['content'][0]['tool_use_id']);
        $this->assertTrue($toolResultMessage['content'][0]['is_error']);
        $this->assertStringContainsString('Something went wrong', $toolResultMessage['content'][0]['content']);
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

    /**
     * Regression test: compaction must not fire between adding assistant
     * tool_use and user tool_result messages, as this corrupts message
     * pairing and causes API validation errors.
     */
    public function testMultiIterationWithCompactionPreservesToolUsePairing(): void
    {
        $tool = Tool::create('read_file')
            ->stringParam('path', 'File path')
            ->handler(fn(array $input): string => str_repeat('file content ', 50));

        // Use a small context limit so compaction fires during the loop
        $contextManager = new ContextManager(
            maxContextTokens: 200,
            options: ['compact_threshold' => 0.5, 'clear_tool_results' => true]
        );

        $config = AgentConfig::fromArray(['max_iterations' => 10]);
        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Analyze this large document',
            tools: [$tool],
            config: $config,
            contextManager: $contextManager,
        );

        // Build a sequence: 9 tool_use iterations, then a final end_turn
        $responses = [];
        for ($i = 1; $i <= 9; $i++) {
            $responses[] = $this->createMockMessage(
                [
                    ['type' => 'text', 'text' => "Reading section {$i}"],
                    [
                        'type' => 'tool_use',
                        'id' => "toolu_{$i}",
                        'name' => 'read_file',
                        'input' => ['path' => "/doc/section{$i}.txt"],
                    ],
                ],
                'tool_use'
            );
        }
        $responses[] = $this->createMockMessage(
            [['type' => 'text', 'text' => 'Analysis complete']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->times(10)
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->times(10)
            ->andReturn(...$responses);

        $result = $this->loop->execute($context);

        // Should complete successfully despite compaction
        $this->assertTrue($result->isCompleted());
        $this->assertFalse($result->hasFailed(), 'Should not fail: ' . ($result->getError() ?? ''));
        $this->assertEquals('Analysis complete', $result->getAnswer());

        // Verify message structure integrity
        $messages = $result->getMessages();

        // First message must be the user task
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('Analyze this large document', $messages[0]['content']);

        // Verify every tool_use has a matching tool_result immediately after
        for ($i = 0; $i < count($messages); $i++) {
            $msg = $messages[$i];
            if (! is_array($msg['content'] ?? null)) {
                continue;
            }

            $toolUseIds = [];
            foreach ($msg['content'] as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'tool_use') {
                    $toolUseIds[] = $block['id'];
                }
            }

            if (empty($toolUseIds)) {
                continue;
            }

            $this->assertArrayHasKey($i + 1, $messages,
                "tool_use at message {$i} has no following message (IDs: " . implode(', ', $toolUseIds) . ')');
            $next = $messages[$i + 1];
            $this->assertEquals('user', $next['role'],
                "Message after tool_use at {$i} must be user");
            $this->assertIsArray($next['content']);

            $resultIds = [];
            foreach ($next['content'] as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'tool_result') {
                    $resultIds[] = $block['tool_use_id'];
                }
            }

            foreach ($toolUseIds as $id) {
                $this->assertContains($id, $resultIds,
                    "tool_use id {$id} at message {$i} has no matching tool_result");
            }
        }
    }

    /**
     * Regression test: verify that messages sent to the API always start
     * with a user message, even after compaction drops older messages.
     */
    public function testApiCallsAlwaysStartWithUserMessage(): void
    {
        $tool = Tool::create('search')
            ->stringParam('query', 'Search query')
            ->handler(fn(array $input): string => str_repeat('search result ', 100));

        $contextManager = new ContextManager(
            maxContextTokens: 150,
            options: ['compact_threshold' => 0.5, 'clear_tool_results' => true]
        );

        $config = AgentConfig::fromArray(['max_iterations' => 6]);

        $context = new AgentContext(
            client: $this->mockClient,
            task: 'Search for information',
            tools: [$tool],
            config: $config,
            contextManager: $contextManager,
        );

        // Track the messages param passed to each API call
        $apiCallMessages = [];

        $responses = [];
        for ($i = 1; $i <= 5; $i++) {
            $responses[] = $this->createMockMessage(
                [
                    ['type' => 'tool_use', 'id' => "tool_{$i}", 'name' => 'search', 'input' => ['query' => "q{$i}"]],
                ],
                'tool_use'
            );
        }
        $responses[] = $this->createMockMessage(
            [['type' => 'text', 'text' => 'Done']],
            'end_turn'
        );

        $this->mockClient->shouldReceive('messages')
            ->times(6)
            ->andReturn($this->mockMessages);

        $this->mockMessages->shouldReceive('create')
            ->times(6)
            ->andReturnUsing(function (array $params) use (&$apiCallMessages, &$responses) {
                $apiCallMessages[] = $params['messages'];
                return array_shift($responses);
            });

        $result = $this->loop->execute($context);

        $this->assertTrue($result->isCompleted());
        $this->assertFalse($result->hasFailed(), 'Should not fail: ' . ($result->getError() ?? ''));

        // Every API call must have messages starting with a user message
        foreach ($apiCallMessages as $callIndex => $msgs) {
            $this->assertEquals('user', $msgs[0]['role'],
                "API call {$callIndex}: messages must start with user role, got '{$msgs[0]['role']}'");
        }
    }
}
