<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Reasoning\CoTPrompts;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ChainOfThoughtAgentTest extends TestCase
{
    private ClaudePhp $client;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testConstructorWithDefaultOptions(): void
    {
        $agent = new ChainOfThoughtAgent($this->client);

        $this->assertSame('cot_agent', $agent->getName());
    }

    public function testConstructorWithCustomOptions(): void
    {
        $agent = new ChainOfThoughtAgent($this->client, [
            'name' => 'custom_cot',
            'mode' => 'few_shot',
            'trigger' => 'Think carefully',
            'examples' => CoTPrompts::logicExamples(),
            'logger' => $this->logger,
        ]);

        $this->assertSame('custom_cot', $agent->getName());
    }

    public function testGetName(): void
    {
        $agent = new ChainOfThoughtAgent($this->client, ['name' => 'test_agent']);

        $this->assertSame('test_agent', $agent->getName());
    }

    public function testZeroShotMode(): void
    {
        // Mock the messages resource
        $messagesResource = $this->createMock(Messages::class);

        // Create a proper response object
        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $response = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => "Step 1: Analyze the problem\nStep 2: Calculate\nFinal Answer: 42",
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                return $params['model'] === 'claude-sonnet-4-5'
                    && isset($params['system'])
                    && isset($params['messages'])
                    && is_array($params['messages'])
                    && count($params['messages']) === 1
                    && str_contains($params['messages'][0]['content'], "Let's think step by step");
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new ChainOfThoughtAgent($this->client, [
            'mode' => 'zero_shot',
            'logger' => $this->logger,
        ]);

        $result = $agent->run('What is the answer to the ultimate question?');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Step 1', $result->getAnswer());
        $this->assertStringContainsString('Final Answer: 42', $result->getAnswer());
        $this->assertSame(1, $result->getIterations());
        $this->assertSame('zero_shot', $result->getMetadata()['reasoning_mode']);
        $this->assertSame(100, $result->getMetadata()['tokens']['input']);
        $this->assertSame(50, $result->getMetadata()['tokens']['output']);
    }

    public function testFewShotMode(): void
    {
        // Mock the messages resource
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 200, output_tokens: 80);
        $response = new Message(
            id: 'msg_456',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => "Step 1: Calculate discount\nStep 2: Subtract from price\nFinal Answer: $35",
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                return $params['model'] === 'claude-sonnet-4-5'
                    && isset($params['system'])
                    && str_contains($params['system'], 'Example')
                    && isset($params['messages']);
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new ChainOfThoughtAgent($this->client, [
            'mode' => 'few_shot',
            'examples' => CoTPrompts::mathExamples(),
            'logger' => $this->logger,
        ]);

        $result = $agent->run('If an item costs $50 with 30% off, what is the price?');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Step', $result->getAnswer());
        $this->assertSame(1, $result->getIterations());
        $this->assertSame('few_shot', $result->getMetadata()['reasoning_mode']);
    }

    public function testRunWithApiError(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $messagesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new \Exception('API Error'));

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('API Error'));

        $agent = new ChainOfThoughtAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Test task');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('API Error', $result->getError());
    }

    public function testRunLogsTask(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 25);
        $response = new Message(
            id: 'msg_789',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Answer here',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->logicalOr(
                $this->stringContains('Starting cot_agent'),
                $this->stringContains('completed successfully')
            ));

        $agent = new ChainOfThoughtAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Solve this problem');

        $this->assertTrue($result->isSuccess());
    }

    public function testExtractTextContentFromMultipleBlocks(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 25);
        $response = new Message(
            id: 'msg_multi',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'First block',
                ],
                [
                    'type' => 'text',
                    'text' => 'Second block',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new ChainOfThoughtAgent($this->client);

        $result = $agent->run('Test');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('First block', $result->getAnswer());
        $this->assertStringContainsString('Second block', $result->getAnswer());
    }

    public function testCustomTriggerPhrase(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 25);
        $response = new Message(
            id: 'msg_trigger',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Reasoning...',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                return str_contains($params['messages'][0]['content'], 'Think carefully and systematically');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new ChainOfThoughtAgent($this->client, [
            'mode' => 'zero_shot',
            'trigger' => 'Think carefully and systematically',
        ]);

        $result = $agent->run('Solve this');

        $this->assertTrue($result->isSuccess());
    }

    public function testResultMetadata(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 123, output_tokens: 456);
        $response = new Message(
            id: 'msg_metadata',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Answer',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new ChainOfThoughtAgent($this->client, [
            'mode' => 'few_shot',
        ]);

        $result = $agent->run('Test');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('reasoning_mode', $result->getMetadata());
        $this->assertArrayHasKey('tokens', $result->getMetadata());
        $this->assertSame('few_shot', $result->getMetadata()['reasoning_mode']);
        $this->assertSame(123, $result->getMetadata()['tokens']['input']);
        $this->assertSame(456, $result->getMetadata()['tokens']['output']);
    }
}
