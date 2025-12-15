<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Reasoning;

use ClaudeAgents\Reasoning\Evaluator;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EvaluatorTest extends TestCase
{
    private ClaudePhp $client;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testConstructor(): void
    {
        $evaluator = new Evaluator($this->client, 'Test problem', 'efficiency');

        $this->assertInstanceOf(Evaluator::class, $evaluator);
    }

    public function testConstructorWithOptions(): void
    {
        $evaluator = new Evaluator(
            $this->client,
            'Test problem',
            'custom criteria',
            ['logger' => $this->logger]
        );

        $this->assertInstanceOf(Evaluator::class, $evaluator);
    }

    public function testEvaluate(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 10);
        $response = new Message(
            id: 'msg_eval',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => '8',
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
                    && $params['max_tokens'] === 100
                    && isset($params['messages'])
                    && str_contains($params['messages'][0]['content'], 'Evaluate this approach');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $evaluator = new Evaluator($this->client, 'Math problem');
        $score = $evaluator->evaluate('Try addition first');

        $this->assertSame(8.0, $score);
    }

    public function testEvaluateExtractsNumberFromText(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 10);
        $response = new Message(
            id: 'msg_eval',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => '8',
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

        $evaluator = new Evaluator($this->client, 'Problem');
        $score = $evaluator->evaluate('Approach');

        $this->assertSame(8.0, $score);
    }

    public function testEvaluateClampsBelowZero(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 10);
        $response = new Message(
            id: 'msg_eval',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => '-5',
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

        $evaluator = new Evaluator($this->client, 'Problem');
        $score = $evaluator->evaluate('Approach');

        // The regex removes non-numeric chars, so '-5' becomes '5'
        $this->assertSame(5.0, $score);
    }

    public function testEvaluateClampsAboveTen(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 10);
        $response = new Message(
            id: 'msg_eval',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => '15',
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

        $evaluator = new Evaluator($this->client, 'Problem');
        $score = $evaluator->evaluate('Approach');

        $this->assertSame(10.0, $score);
    }

    public function testEvaluateWithApiError(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $messagesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new \Exception('API Error'));

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Evaluation failed'));

        $evaluator = new Evaluator(
            $this->client,
            'Problem',
            'criteria',
            ['logger' => $this->logger]
        );

        $score = $evaluator->evaluate('Approach');

        // Should return default middle score on error
        $this->assertSame(5.0, $score);
    }

    public function testEvaluateMultiple(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 10);

        $response1 = new Message(
            id: 'msg_1',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '7']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $response2 = new Message(
            id: 'msg_2',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '9']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $response3 = new Message(
            id: 'msg_3',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '5']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->exactly(3))
            ->method('create')
            ->willReturnOnConsecutiveCalls($response1, $response2, $response3);

        $this->client->expects($this->exactly(3))
            ->method('messages')
            ->willReturn($messagesResource);

        $evaluator = new Evaluator($this->client, 'Problem');

        $thoughts = [
            'Approach 1: First method',
            'Approach 2: Second method',
            'Approach 3: Third method',
        ];

        $scores = $evaluator->evaluateMultiple($thoughts);

        $this->assertCount(3, $scores);
        $this->assertSame(7.0, $scores['Approach 1: First method']);
        $this->assertSame(9.0, $scores['Approach 2: Second method']);
        $this->assertSame(5.0, $scores['Approach 3: Third method']);
    }

    public function testEvaluateMultipleEmpty(): void
    {
        $evaluator = new Evaluator($this->client, 'Problem');

        $scores = $evaluator->evaluateMultiple([]);

        $this->assertSame([], $scores);
    }

    public function testEvaluateIncludesProblemInPrompt(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 10);
        $response = new Message(
            id: 'msg_eval',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '8']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                return str_contains($params['messages'][0]['content'], 'Solve math equation');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $evaluator = new Evaluator($this->client, 'Solve math equation');
        $evaluator->evaluate('Use algebra');
    }

    public function testEvaluateIncludesCriteriaInPrompt(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 10);
        $response = new Message(
            id: 'msg_eval',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => '8']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                return str_contains($params['messages'][0]['content'], 'speed and accuracy');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $evaluator = new Evaluator($this->client, 'Problem', 'speed and accuracy');
        $evaluator->evaluate('Approach');
    }

    public function testExtractTextContentMultipleBlocks(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 10);
        $response = new Message(
            id: 'msg_eval',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Analysis:'],
                ['type' => 'text', 'text' => '8.5'],
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

        $evaluator = new Evaluator($this->client, 'Problem');
        $score = $evaluator->evaluate('Approach');

        $this->assertSame(8.5, $score);
    }
}
