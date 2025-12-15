<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\Agents\WorkerAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;

class WorkerAgentTest extends TestCase
{
    private ClaudePhp $client;
    private Messages $messages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClaudePhp::class);
        $this->messages = $this->createMock(Messages::class);
    }

    private function mockLlmResponse(string $text, int $inputTokens = 100, int $outputTokens = 50): Message
    {
        $usage = new Usage(
            input_tokens: $inputTokens,
            output_tokens: $outputTokens
        );

        return new Message(
            id: 'msg_test_' . uniqid(),
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => $text],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );
    }

    public function testConstructorWithDefaultOptions(): void
    {
        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client);

        $this->assertEquals('worker', $agent->getName());
        $this->assertEquals('general tasks', $agent->getSpecialty());
    }

    public function testConstructorWithCustomOptions(): void
    {
        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client, [
            'name' => 'math_specialist',
            'specialty' => 'advanced mathematics',
            'system' => 'You are a math expert',
            'model' => 'claude-opus-4',
            'max_tokens' => 4096,
        ]);

        $this->assertEquals('math_specialist', $agent->getName());
        $this->assertEquals('advanced mathematics', $agent->getSpecialty());
    }

    public function testRunSuccessful(): void
    {
        $response = $this->mockLlmResponse('Worker response to task', 100, 50);

        $this->messages->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                return isset($params['model'])
                    && isset($params['max_tokens'])
                    && isset($params['system'])
                    && isset($params['messages'])
                    && $params['messages'][0]['role'] === 'user'
                    && $params['messages'][0]['content'] === 'Test task';
            }))
            ->willReturn($response);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client, [
            'name' => 'test_worker',
            'specialty' => 'testing',
        ]);

        $result = $agent->run('Test task');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Worker response to task', $result->getAnswer());
        $this->assertEquals(1, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertEquals('test_worker', $metadata['worker']);
        $this->assertEquals('testing', $metadata['specialty']);

        $usage = $result->getTokenUsage();
        $this->assertEquals(100, $usage['input']);
        $this->assertEquals(50, $usage['output']);
        $this->assertEquals(150, $usage['total']);
    }

    public function testRunWithMultipleContentBlocks(): void
    {
        $usage = new Usage(input_tokens: 150, output_tokens: 75);
        $response = new Message(
            id: 'msg_multi',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'First part'],
                ['type' => 'text', 'text' => 'Second part'],
                ['type' => 'tool_use', 'id' => 'tool_123'], // Should be ignored
                ['type' => 'text', 'text' => 'Third part'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $this->messages->expects($this->once())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client);
        $result = $agent->run('Multi-block task');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals("First part\nSecond part\nThird part", $result->getAnswer());
    }

    public function testRunWithEmptyContent(): void
    {
        $usage = new Usage(input_tokens: 50, output_tokens: 0);
        $response = new Message(
            id: 'msg_empty',
            type: 'message',
            role: 'assistant',
            content: [],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $this->messages->expects($this->once())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client);
        $result = $agent->run('Empty response task');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('', $result->getAnswer());
    }

    public function testRunWithApiException(): void
    {
        $this->messages->expects($this->once())
            ->method('create')
            ->willThrowException(new \RuntimeException('API connection failed'));

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client, [
            'name' => 'error_worker',
        ]);

        $result = $agent->run('Failing task');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('error_worker', $result->getError());
        $this->assertStringContainsString('API connection failed', $result->getError());

        $metadata = $result->getMetadata();
        $this->assertEquals('error_worker', $metadata['worker']);
    }

    public function testGetName(): void
    {
        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client, [
            'name' => 'my_worker',
        ]);

        $this->assertEquals('my_worker', $agent->getName());
    }

    public function testGetSpecialty(): void
    {
        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client, [
            'specialty' => 'data analysis',
        ]);

        $this->assertEquals('data analysis', $agent->getSpecialty());
    }

    public function testCustomSystemPrompt(): void
    {
        $response = $this->mockLlmResponse('Response');

        $this->messages->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                return $params['system'] === 'Custom system prompt for testing';
            }))
            ->willReturn($response);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client, [
            'system' => 'Custom system prompt for testing',
        ]);

        $result = $agent->run('Test task');
        $this->assertTrue($result->isSuccess());
    }

    public function testDefaultSystemPromptIncludesSpecialty(): void
    {
        $response = $this->mockLlmResponse('Response');

        $this->messages->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                return strpos($params['system'], 'machine learning') !== false;
            }))
            ->willReturn($response);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client, [
            'specialty' => 'machine learning',
        ]);

        $result = $agent->run('Test task');
        $this->assertTrue($result->isSuccess());
    }

    public function testMultipleRuns(): void
    {
        $response1 = $this->mockLlmResponse('First response', 100, 50);
        $response2 = $this->mockLlmResponse('Second response', 120, 60);

        $this->messages->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($response1, $response2);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client);

        $result1 = $agent->run('First task');
        $this->assertTrue($result1->isSuccess());
        $this->assertEquals('First response', $result1->getAnswer());

        $result2 = $agent->run('Second task');
        $this->assertTrue($result2->isSuccess());
        $this->assertEquals('Second response', $result2->getAnswer());
    }

    public function testMessagesInResult(): void
    {
        $response = $this->mockLlmResponse('Response');

        $this->messages->expects($this->once())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->any())
            ->method('messages')
            ->willReturn($this->messages);

        $agent = new WorkerAgent($this->client);
        $result = $agent->run('Test task for messages');

        $this->assertTrue($result->isSuccess());

        $messages = $result->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('Test task for messages', $messages[0]['content']);
    }
}
