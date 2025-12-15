<?php

declare(strict_types=1);

namespace Tests\Integration\Agents;

use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Conversation\Turn;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for DialogAgent
 *
 * These tests use a real ClaudePhp client (or mock if no API key)
 */
class DialogAgentIntegrationTest extends TestCase
{
    private ?ClaudePhp $client = null;
    private bool $hasApiKey = false;

    protected function setUp(): void
    {
        // Check for API key
        $apiKey = getenv('ANTHROPIC_API_KEY') ?: $_ENV['ANTHROPIC_API_KEY'] ?? null;

        if ($apiKey && ! empty($apiKey)) {
            $this->client = new ClaudePhp(apiKey: $apiKey);
            $this->hasApiKey = true;
        } else {
            $this->client = $this->createMock(ClaudePhp::class);
            $this->hasApiKey = false;
        }
    }

    public function test_dialog_agent_can_have_multi_turn_conversation(): void
    {
        if (! $this->hasApiKey) {
            $this->markTestSkipped('Skipping integration test: No API key available');
        }

        $agent = new DialogAgent($this->client, ['name' => 'integration_test']);
        $session = $agent->startConversation();

        // First turn
        $response1 = $agent->turn('Hello! My name is Alice.', $session->getId());
        $this->assertNotEmpty($response1);

        // Second turn - should remember the name
        $response2 = $agent->turn('What is my name?', $session->getId());
        $this->assertNotEmpty($response2);

        // Response should reference the name from context
        $this->assertStringContainsString('alice', strtolower($response2));

        // Verify turns were recorded
        $retrievedSession = $agent->getSession($session->getId());
        $this->assertCount(2, $retrievedSession->getTurns());
    }

    public function test_dialog_agent_maintains_context_across_multiple_turns(): void
    {
        if (! $this->hasApiKey) {
            $this->markTestSkipped('Skipping integration test: No API key available');
        }

        $agent = new DialogAgent($this->client);
        $session = $agent->startConversation();

        // Build up a context
        $agent->turn('I have a dog named Max.', $session->getId());
        $agent->turn('Max is 3 years old.', $session->getId());
        $agent->turn('He loves to play fetch.', $session->getId());

        // Ask a question that requires context
        $response = $agent->turn('Tell me about my dog.', $session->getId());

        $this->assertNotEmpty($response);
        $this->assertStringContainsString('max', strtolower($response));
    }

    public function test_dialog_agent_handles_session_state(): void
    {
        if (! $this->hasApiKey) {
            $this->markTestSkipped('Skipping integration test: No API key available');
        }

        $agent = new DialogAgent($this->client);
        $session = $agent->startConversation();

        // Set some state
        $session->updateState('user_preference', 'detailed_responses');
        $session->updateState('language', 'en');

        $response = $agent->turn('Give me a brief summary of AI.', $session->getId());

        $this->assertNotEmpty($response);

        // Verify state is preserved
        $retrievedSession = $agent->getSession($session->getId());
        $state = $retrievedSession->getState();
        $this->assertSame('detailed_responses', $state['user_preference']);
        $this->assertSame('en', $state['language']);
    }

    public function test_dialog_agent_can_handle_multiple_concurrent_sessions(): void
    {
        if (! $this->hasApiKey) {
            $this->markTestSkipped('Skipping integration test: No API key available');
        }

        $agent = new DialogAgent($this->client);

        // Create two separate sessions
        $session1 = $agent->startConversation();
        $session2 = $agent->startConversation();

        // Have different conversations
        $agent->turn('I like pizza.', $session1->getId());
        $agent->turn('I like sushi.', $session2->getId());

        $response1 = $agent->turn('What do I like?', $session1->getId());
        $response2 = $agent->turn('What do I like?', $session2->getId());

        $this->assertStringContainsString('pizza', strtolower($response1));
        $this->assertStringContainsString('sushi', strtolower($response2));
    }

    public function test_dialog_agent_run_method_creates_new_conversation(): void
    {
        if (! $this->hasApiKey) {
            $this->markTestSkipped('Skipping integration test: No API key available');
        }

        $agent = new DialogAgent($this->client);

        $result = $agent->run('What is 2 + 2?');

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getAnswer());
        $this->assertSame(1, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('session_id', $metadata);

        // Verify session exists
        $session = $agent->getSession($metadata['session_id']);
        $this->assertNotNull($session);
        $this->assertCount(1, $session->getTurns());
    }

    public function test_dialog_agent_handles_long_conversations(): void
    {
        if (! $this->hasApiKey) {
            $this->markTestSkipped('Skipping integration test: No API key available');
        }

        $agent = new DialogAgent($this->client);
        $session = $agent->startConversation();

        // Have a longer conversation (10 turns)
        for ($i = 1; $i <= 10; $i++) {
            $response = $agent->turn("This is message number {$i}.", $session->getId());
            $this->assertNotEmpty($response);
        }

        $retrievedSession = $agent->getSession($session->getId());
        $this->assertCount(10, $retrievedSession->getTurns());

        // Context builder should use last 5 turns
        $finalResponse = $agent->turn('How many messages have I sent?', $session->getId());
        $this->assertNotEmpty($finalResponse);
    }

    public function test_dialog_agent_preserves_turn_order(): void
    {
        if (! $this->hasApiKey) {
            $this->markTestSkipped('Skipping integration test: No API key available');
        }

        $agent = new DialogAgent($this->client);
        $session = $agent->startConversation();

        $inputs = [
            'First message',
            'Second message',
            'Third message',
        ];

        foreach ($inputs as $input) {
            $agent->turn($input, $session->getId());
        }

        $retrievedSession = $agent->getSession($session->getId());
        $turns = $retrievedSession->getTurns();

        foreach ($inputs as $i => $input) {
            $this->assertSame($input, $turns[$i]->getUserInput());
        }
    }

    public function test_dialog_agent_without_api_key_uses_mock(): void
    {
        // This test always runs
        $mockClient = $this->createMock(ClaudePhp::class);
        $agent = new DialogAgent($mockClient);

        $session = $agent->startConversation();

        $this->assertNotNull($session);
        $this->assertNotEmpty($session->getId());
    }
}
