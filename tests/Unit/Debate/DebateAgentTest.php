<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Debate;

use ClaudeAgents\Debate\DebateAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DebateAgentTest extends TestCase
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
        $agent = new DebateAgent(
            $this->client,
            'Test Agent',
            'test-perspective',
            'You are a test agent'
        );

        $this->assertSame('Test Agent', $agent->getName());
        $this->assertSame('test-perspective', $agent->getPerspective());
        $this->assertSame('You are a test agent', $agent->getSystemPrompt());
    }

    public function testConstructorWithCustomLogger(): void
    {
        $agent = new DebateAgent(
            $this->client,
            'Test Agent',
            'test-perspective',
            'You are a test agent',
            ['logger' => $this->logger]
        );

        $this->assertSame('Test Agent', $agent->getName());
    }

    public function testGetName(): void
    {
        $agent = new DebateAgent(
            $this->client,
            'Proponent',
            'support',
            'You support the proposal'
        );

        $this->assertSame('Proponent', $agent->getName());
    }

    public function testGetPerspective(): void
    {
        $agent = new DebateAgent(
            $this->client,
            'Opponent',
            'oppose',
            'You oppose the proposal'
        );

        $this->assertSame('oppose', $agent->getPerspective());
    }

    public function testGetSystemPrompt(): void
    {
        $systemPrompt = 'You are a critical thinker who analyzes proposals carefully.';
        $agent = new DebateAgent(
            $this->client,
            'Analyst',
            'analytical',
            $systemPrompt
        );

        $this->assertSame($systemPrompt, $agent->getSystemPrompt());
    }

    public function testSpeakWithBasicTopic(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $response = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'I strongly support this proposal because it offers significant benefits.',
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
                    && $params['max_tokens'] === 1024
                    && $params['system'] === 'You support the proposal'
                    && isset($params['messages'])
                    && count($params['messages']) === 1
                    && str_contains($params['messages'][0]['content'], 'Topic:')
                    && str_contains($params['messages'][0]['content'], 'Provide your perspective');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new DebateAgent(
            $this->client,
            'Proponent',
            'support',
            'You support the proposal',
            ['logger' => $this->logger]
        );

        $result = $agent->speak('Should we adopt remote work?');

        $this->assertIsString($result);
        $this->assertStringContainsString('support', $result);
    }

    public function testSpeakWithContext(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 150, output_tokens: 75);
        $response = new Message(
            id: 'msg_124',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Building on the previous points, I would add that...',
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
                return str_contains($params['messages'][0]['content'], 'Previous discussion:')
                    && str_contains($params['messages'][0]['content'], 'Other agent said something');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new DebateAgent(
            $this->client,
            'Responder',
            'analytical',
            'You analyze carefully'
        );

        $result = $agent->speak(
            'Should we adopt remote work?',
            'Other agent said something',
            ''
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('previous', $result);
    }

    public function testSpeakWithInstruction(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 120, output_tokens: 60);
        $response = new Message(
            id: 'msg_125',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Following your instruction, here is my summary...',
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
                return str_contains($params['messages'][0]['content'], 'Please summarize your position');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new DebateAgent(
            $this->client,
            'Summarizer',
            'synthesis',
            'You synthesize discussions'
        );

        $result = $agent->speak(
            'Should we adopt remote work?',
            '',
            'Please summarize your position'
        );

        $this->assertIsString($result);
    }

    public function testSpeakWithAllParameters(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 200, output_tokens: 100);
        $response = new Message(
            id: 'msg_126',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Considering all previous points and your instruction...',
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
                $content = $params['messages'][0]['content'];

                return str_contains($content, 'Topic:')
                    && str_contains($content, 'Previous discussion:')
                    && str_contains($content, 'Respond thoughtfully')
                    && str_contains($content, 'Provide your perspective');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new DebateAgent(
            $this->client,
            'Comprehensive Agent',
            'balanced',
            'You provide balanced perspectives'
        );

        $result = $agent->speak(
            'Should we adopt remote work?',
            'Agent A said X. Agent B said Y.',
            'Respond thoughtfully'
        );

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testSpeakHandlesApiError(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $messagesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new \RuntimeException('API Error'));

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('API Error'));

        $agent = new DebateAgent(
            $this->client,
            'Error Agent',
            'test',
            'Test prompt',
            ['logger' => $this->logger]
        );

        $result = $agent->speak('Test topic');

        $this->assertStringContainsString('Error from Error Agent', $result);
        $this->assertStringContainsString('API Error', $result);
    }

    public function testSpeakExtractsMultipleTextBlocks(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $response = new Message(
            id: 'msg_127',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'First part of response.',
                ],
                [
                    'type' => 'text',
                    'text' => 'Second part of response.',
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

        $agent = new DebateAgent(
            $this->client,
            'Multi-block Agent',
            'test',
            'Test prompt'
        );

        $result = $agent->speak('Test topic');

        $this->assertStringContainsString('First part', $result);
        $this->assertStringContainsString('Second part', $result);
    }

    public function testSpeakLogsDebugMessages(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 100, output_tokens: 50);
        $response = new Message(
            id: 'msg_128',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Response text',
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

        $this->logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Agent Test Agent speaking on topic'));

        $agent = new DebateAgent(
            $this->client,
            'Test Agent',
            'test',
            'Test prompt',
            ['logger' => $this->logger]
        );

        $agent->speak('Test topic');
    }
}
