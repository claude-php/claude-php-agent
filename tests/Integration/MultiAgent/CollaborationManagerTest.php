<?php

declare(strict_types=1);

namespace Tests\Integration\MultiAgent;

use ClaudeAgents\MultiAgent\CollaborationManager;
use ClaudeAgents\MultiAgent\Message;
use ClaudeAgents\MultiAgent\Protocol;
use ClaudeAgents\MultiAgent\SimpleCollaborativeAgent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class CollaborationManagerTest extends TestCase
{
    private ClaudePhp $client;
    private CollaborationManager $manager;

    protected function setUp(): void
    {
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';

        if (empty($apiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $this->client = new ClaudePhp($apiKey);
        $this->manager = new CollaborationManager($this->client, [
            'max_rounds' => 3,
            'enable_message_passing' => true,
        ]);
    }

    public function test_registers_and_unregisters_agents(): void
    {
        $agent = new SimpleCollaborativeAgent(
            $this->client,
            'test_agent',
            ['testing']
        );

        $this->manager->registerAgent('test_agent', $agent, ['testing']);

        // Agent is registered
        $history = $this->manager->getConversationHistory();
        $this->assertIsArray($history);

        // Unregister
        $result = $this->manager->unregisterAgent('test_agent');
        $this->assertTrue($result);

        // Can't unregister twice
        $result = $this->manager->unregisterAgent('test_agent');
        $this->assertFalse($result);
    }

    public function test_sends_and_routes_messages(): void
    {
        $agent1 = new SimpleCollaborativeAgent(
            $this->client,
            'agent1',
            ['capability1']
        );

        $agent2 = new SimpleCollaborativeAgent(
            $this->client,
            'agent2',
            ['capability2']
        );

        $this->manager->registerAgent('agent1', $agent1);
        $this->manager->registerAgent('agent2', $agent2);

        // Send message from agent1 to agent2
        $message = new Message('agent1', 'agent2', 'Test message', 'request');
        $this->manager->sendMessage($message);

        // Check agent2 received it
        $inbox = $agent2->getInbox();
        $this->assertCount(1, $inbox);
        $this->assertEquals('Test message', $inbox[0]->getContent());
    }

    public function test_delivers_broadcast_messages(): void
    {
        $agent1 = new SimpleCollaborativeAgent($this->client, 'agent1', []);
        $agent2 = new SimpleCollaborativeAgent($this->client, 'agent2', []);
        $agent3 = new SimpleCollaborativeAgent($this->client, 'agent3', []);

        $this->manager->registerAgent('agent1', $agent1);
        $this->manager->registerAgent('agent2', $agent2);
        $this->manager->registerAgent('agent3', $agent3);

        // Broadcast from agent1
        $message = new Message('agent1', 'broadcast', 'Announcement to all');
        $this->manager->sendMessage($message);

        // agent2 and agent3 received it, agent1 didn't
        $this->assertEmpty($agent1->getInbox());
        $this->assertCount(1, $agent2->getInbox());
        $this->assertCount(1, $agent3->getInbox());
    }

    public function test_validates_messages_with_protocol(): void
    {
        $manager = new CollaborationManager($this->client, [
            'protocol' => Protocol::requestResponse(),
        ]);

        $agent = new SimpleCollaborativeAgent($this->client, 'agent1', []);
        $manager->registerAgent('agent1', $agent);

        // Valid request message
        $validMessage = new Message('user', 'agent1', 'Request', 'request');
        $manager->sendMessage($validMessage);
        $this->assertCount(1, $agent->getInbox());

        // Invalid message type (should be rejected)
        $invalidMessage = new Message('user', 'agent1', 'Notification', 'notification');
        $manager->sendMessage($invalidMessage);
        $this->assertCount(1, $agent->getInbox()); // Still only 1
    }

    public function test_tracks_metrics(): void
    {
        $agent = new SimpleCollaborativeAgent($this->client, 'agent1', []);
        $this->manager->registerAgent('agent1', $agent);

        $message = new Message('user', 'agent1', 'Test');
        $this->manager->sendMessage($message);

        $metrics = $this->manager->getMetrics();

        $this->assertArrayHasKey('agents_registered', $metrics);
        $this->assertArrayHasKey('messages_routed', $metrics);
        $this->assertArrayHasKey('messages_in_queue', $metrics);
        $this->assertArrayHasKey('shared_memory_stats', $metrics);

        $this->assertEquals(1, $metrics['agents_registered']);
        $this->assertGreaterThanOrEqual(1, $metrics['messages_routed']);
    }

    public function test_shared_memory_integration(): void
    {
        $memory = $this->manager->getSharedMemory();

        $agent = new SimpleCollaborativeAgent($this->client, 'agent1', []);
        $this->manager->registerAgent('agent1', $agent);

        // Agent writes to shared memory
        $memory->write('shared_data', 'value123', 'agent1');

        // Another agent reads it
        $value = $memory->read('shared_data', 'agent2');
        $this->assertEquals('value123', $value);

        // Check statistics
        $stats = $memory->getStatistics();
        $this->assertEquals(1, $stats['total_keys']);
        $this->assertEquals(2, $stats['unique_agents']);
    }

    public function test_processes_message_queue(): void
    {
        $agent = new SimpleCollaborativeAgent($this->client, 'agent1', []);

        // Create manager with message passing disabled to test queue
        $manager = new CollaborationManager($this->client, [
            'enable_message_passing' => false,
        ]);
        $manager->registerAgent('agent1', $agent);

        // Queue messages
        $manager->sendMessage(new Message('user', 'agent1', 'Message 1'));
        $manager->sendMessage(new Message('user', 'agent1', 'Message 2'));

        // Process queue
        $processed = $manager->processMessageQueue();

        $this->assertEquals(2, $processed);
        $this->assertCount(2, $agent->getInbox());
    }

    public function test_collaboration_workflow(): void
    {
        // This test requires API calls, so we'll keep it simple
        $researcher = new SimpleCollaborativeAgent(
            $this->client,
            'researcher',
            ['research'],
            ['system_prompt' => 'You are a researcher who provides factual information.']
        );

        $analyst = new SimpleCollaborativeAgent(
            $this->client,
            'analyst',
            ['analysis'],
            ['system_prompt' => 'You are an analyst who evaluates information.']
        );

        $this->manager->registerAgent('researcher', $researcher, ['research']);
        $this->manager->registerAgent('analyst', $analyst, ['analysis']);

        $result = $this->manager->collaborate('What are the benefits of PHP 8?');

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getAnswer());
        $this->assertGreaterThan(0, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('rounds', $metadata);
        $this->assertArrayHasKey('agents_involved', $metadata);
    }
}
