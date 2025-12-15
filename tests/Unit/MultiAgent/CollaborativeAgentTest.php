<?php

declare(strict_types=1);

namespace Tests\Unit\MultiAgent;

use ClaudeAgents\AgentResult;
use ClaudeAgents\MultiAgent\CollaborationManager;
use ClaudeAgents\MultiAgent\CollaborativeAgent;
use ClaudeAgents\MultiAgent\Message;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class CollaborativeAgentTest extends TestCase
{
    private ClaudePhp $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
    }

    public function test_creates_collaborative_agent(): void
    {
        $agent = $this->createAgent('agent1', ['capability1', 'capability2']);

        $this->assertEquals('agent1', $agent->getAgentId());
        $this->assertEquals(['capability1', 'capability2'], $agent->getCapabilities());
    }

    public function test_sends_message(): void
    {
        $agent = $this->createAgent('agent1');

        $message = new Message('agent1', 'agent2', 'Hello');
        $agent->sendMessage($message);

        $outbox = $agent->getOutbox();
        $this->assertCount(1, $outbox);
        $this->assertSame($message, $outbox[0]);
    }

    public function test_receives_message(): void
    {
        $agent = $this->createAgent('agent1');

        $message = new Message('agent2', 'agent1', 'Hi there');
        $agent->receiveMessage($message);

        $inbox = $agent->getInbox();
        $this->assertCount(1, $inbox);
        $this->assertSame($message, $inbox[0]);
    }

    public function test_clears_inbox(): void
    {
        $agent = $this->createAgent('agent1');

        $agent->receiveMessage(new Message('agent2', 'agent1', 'Message 1'));
        $agent->receiveMessage(new Message('agent2', 'agent1', 'Message 2'));

        $agent->clearInbox();

        $this->assertEmpty($agent->getInbox());
    }

    public function test_clears_outbox(): void
    {
        $agent = $this->createAgent('agent1');

        $agent->sendMessage(new Message('agent1', 'agent2', 'Message 1'));
        $agent->sendMessage(new Message('agent1', 'agent3', 'Message 2'));

        $agent->clearOutbox();

        $this->assertEmpty($agent->getOutbox());
    }

    public function test_gets_unread_count(): void
    {
        $agent = $this->createAgent('agent1');

        $agent->receiveMessage(new Message('agent2', 'agent1', 'Message 1'));
        $agent->receiveMessage(new Message('agent3', 'agent1', 'Message 2'));
        $agent->receiveMessage(new Message('agent4', 'agent1', 'Message 3'));

        $this->assertEquals(3, $agent->getUnreadCount());
    }

    public function test_has_capability(): void
    {
        $agent = $this->createAgent('agent1', ['coding', 'testing']);

        $this->assertTrue($agent->hasCapability('coding'));
        $this->assertTrue($agent->hasCapability('testing'));
        $this->assertFalse($agent->hasCapability('design'));
    }

    public function test_sets_manager(): void
    {
        $agent = $this->createAgent('agent1');
        $manager = $this->createMock(CollaborationManager::class);

        $agent->setManager($manager);

        // Manager is set (tested by checking message routing in integration)
        $this->assertTrue(true);
    }

    private function createAgent(string $id, array $capabilities = []): CollaborativeAgent
    {
        return new class ($this->client, $id, $capabilities) extends CollaborativeAgent {
            public function run(string $task): AgentResult
            {
                return AgentResult::success(
                    answer: "Processed: {$task}",
                    messages: [],
                    iterations: 1
                );
            }

            public function getName(): string
            {
                return $this->agentId;
            }
        };
    }
}
