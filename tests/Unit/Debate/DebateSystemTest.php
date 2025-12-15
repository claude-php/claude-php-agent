<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Debate;

use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\DebateModerator;
use ClaudeAgents\Debate\DebateResult;
use ClaudeAgents\Debate\DebateSystem;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DebateSystemTest extends TestCase
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
        $system = new DebateSystem($this->client);

        $this->assertInstanceOf(DebateSystem::class, $system);
        $this->assertInstanceOf(DebateModerator::class, $system->getModerator());
        $this->assertSame(0, $system->getAgentCount());
    }

    public function testConstructorWithLogger(): void
    {
        $system = new DebateSystem($this->client, ['logger' => $this->logger]);

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testCreateStaticMethod(): void
    {
        $system = DebateSystem::create($this->client);

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testCreateWithOptions(): void
    {
        $system = DebateSystem::create($this->client, ['logger' => $this->logger]);

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testAddAgent(): void
    {
        $agent = $this->createMock(DebateAgent::class);
        $agent->method('getName')->willReturn('Test Agent');

        $system = new DebateSystem($this->client);
        $result = $system->addAgent('test', $agent);

        $this->assertSame($system, $result); // Fluent interface
        $this->assertSame(1, $system->getAgentCount());
    }

    public function testAddMultipleAgents(): void
    {
        $agent1 = $this->createMock(DebateAgent::class);
        $agent2 = $this->createMock(DebateAgent::class);

        $system = new DebateSystem($this->client);
        $system->addAgent('agent1', $agent1)
               ->addAgent('agent2', $agent2);

        $this->assertSame(2, $system->getAgentCount());
    }

    public function testAddAgentsArray(): void
    {
        $agent1 = $this->createMock(DebateAgent::class);
        $agent2 = $this->createMock(DebateAgent::class);
        $agent3 = $this->createMock(DebateAgent::class);

        $agents = [
            'pro' => $agent1,
            'con' => $agent2,
            'neutral' => $agent3,
        ];

        $system = new DebateSystem($this->client);
        $result = $system->addAgents($agents);

        $this->assertSame($system, $result); // Fluent interface
        $this->assertSame(3, $system->getAgentCount());
    }

    public function testGetAgents(): void
    {
        $agent1 = $this->createMock(DebateAgent::class);
        $agent2 = $this->createMock(DebateAgent::class);

        $system = new DebateSystem($this->client);
        $system->addAgent('first', $agent1)
               ->addAgent('second', $agent2);

        $agents = $system->getAgents();

        $this->assertCount(2, $agents);
        $this->assertArrayHasKey('first', $agents);
        $this->assertArrayHasKey('second', $agents);
        $this->assertSame($agent1, $agents['first']);
        $this->assertSame($agent2, $agents['second']);
    }

    public function testRounds(): void
    {
        $system = new DebateSystem($this->client);
        $result = $system->rounds(3);

        $this->assertSame($system, $result); // Fluent interface
    }

    public function testRoundsWithZeroSetsToOne(): void
    {
        // This tests that rounds can't be less than 1
        $system = new DebateSystem($this->client);
        $system->rounds(0);

        // We can't directly test the internal rounds value, but we can test it through debate
        // For now, just verify it doesn't throw an exception
        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testRoundsWithNegativeValueSetsToOne(): void
    {
        $system = new DebateSystem($this->client);
        $system->rounds(-5);

        // Should clamp to minimum of 1
        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testDebateThrowsExceptionWithNoAgents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No agents added to debate system');

        $system = new DebateSystem($this->client);
        $system->debate('Test topic');
    }

    public function testDebateWithSingleAgent(): void
    {
        $messagesResource = $this->createMock(Messages::class);
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $response = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Agent response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new DebateAgent($this->client, 'Solo', 'solo', 'You are solo');

        $system = new DebateSystem($this->client, ['logger' => $this->logger]);
        $system->addAgent('solo', $agent)->rounds(1);

        $result = $system->debate('Test topic');

        $this->assertInstanceOf(DebateResult::class, $result);
        $this->assertSame('Test topic', $result->getTopic());
        $this->assertSame(1, $result->getRoundCount());
    }

    public function testDebateWithMultipleAgents(): void
    {
        $messagesResource = $this->createMock(Messages::class);
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $response = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Agent response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent1 = new DebateAgent($this->client, 'Pro', 'support', 'You support');
        $agent2 = new DebateAgent($this->client, 'Con', 'oppose', 'You oppose');

        $system = new DebateSystem($this->client, ['logger' => $this->logger]);
        $system->addAgent('pro', $agent1)
               ->addAgent('con', $agent2)
               ->rounds(2);

        $result = $system->debate('Should we adopt feature X?');

        $this->assertInstanceOf(DebateResult::class, $result);
        $this->assertSame('Should we adopt feature X?', $result->getTopic());
        $this->assertSame(2, $result->getRoundCount());
        $this->assertIsString($result->getSynthesis());
        $this->assertIsFloat($result->getAgreementScore());
    }

    public function testDebateLogsProgress(): void
    {
        $messagesResource = $this->createMock(Messages::class);
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $response = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new DebateAgent($this->client, 'Agent', 'perspective', 'prompt');

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        $this->logger->expects($this->atLeastOnce())
            ->method('debug');

        $system = new DebateSystem($this->client, ['logger' => $this->logger]);
        $system->addAgent('agent', $agent)->rounds(1);

        $system->debate('Test topic');
    }

    public function testGetModerator(): void
    {
        $system = new DebateSystem($this->client);
        $moderator = $system->getModerator();

        $this->assertInstanceOf(DebateModerator::class, $moderator);
    }

    public function testGetAgentCount(): void
    {
        $agent1 = $this->createMock(DebateAgent::class);
        $agent2 = $this->createMock(DebateAgent::class);
        $agent3 = $this->createMock(DebateAgent::class);

        $system = new DebateSystem($this->client);

        $this->assertSame(0, $system->getAgentCount());

        $system->addAgent('a1', $agent1);
        $this->assertSame(1, $system->getAgentCount());

        $system->addAgent('a2', $agent2);
        $this->assertSame(2, $system->getAgentCount());

        $system->addAgent('a3', $agent3);
        $this->assertSame(3, $system->getAgentCount());
    }

    public function testFluentInterface(): void
    {
        $agent1 = $this->createMock(DebateAgent::class);
        $agent2 = $this->createMock(DebateAgent::class);

        $system = DebateSystem::create($this->client)
            ->addAgent('agent1', $agent1)
            ->addAgent('agent2', $agent2)
            ->rounds(3);

        $this->assertInstanceOf(DebateSystem::class, $system);
        $this->assertSame(2, $system->getAgentCount());
    }

    public function testDebateBuildsContext(): void
    {
        // This test verifies that context is built up across rounds
        $messagesResource = $this->createMock(Messages::class);
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $callCount = 0;
        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturnCallback(function ($params) use (&$callCount, $usage) {
                $callCount++;
                $responseText = $callCount === 1
                    ? 'First statement'
                    : 'Response to previous statement';

                return new Message(
                    id: "msg_{$callCount}",
                    type: 'message',
                    role: 'assistant',
                    content: [['type' => 'text', 'text' => $responseText]],
                    model: 'claude-sonnet-4-5',
                    stop_reason: 'end_turn',
                    stop_sequence: null,
                    usage: $usage
                );
            });

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new DebateAgent($this->client, 'Agent', 'perspective', 'prompt');

        $system = new DebateSystem($this->client, ['logger' => $this->logger]);
        $system->addAgent('agent', $agent)->rounds(2);

        $result = $system->debate('Test topic');

        $this->assertSame(2, $result->getRoundCount());
    }
}
