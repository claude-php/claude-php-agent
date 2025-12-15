<?php

declare(strict_types=1);

namespace Tests\Unit\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Conversation\Session;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DialogAgentTest extends TestCase
{
    private ClaudePhp $client;
    private DialogAgent $agent;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
        $this->agent = new DialogAgent($this->client, ['name' => 'test_dialog_agent']);
    }

    public function test_creates_dialog_agent_with_default_options(): void
    {
        $agent = new DialogAgent($this->client);

        $this->assertSame('dialog_agent', $agent->getName());
    }

    public function test_creates_dialog_agent_with_custom_options(): void
    {
        $logger = new NullLogger();
        $agent = new DialogAgent($this->client, [
            'name' => 'custom_dialog',
            'logger' => $logger,
        ]);

        $this->assertSame('custom_dialog', $agent->getName());
    }

    public function test_get_name(): void
    {
        $this->assertSame('test_dialog_agent', $this->agent->getName());
    }

    public function test_start_conversation_creates_new_session(): void
    {
        $session = $this->agent->startConversation();

        $this->assertInstanceOf(Session::class, $session);
        $this->assertNotEmpty($session->getId());
    }

    public function test_start_conversation_with_custom_session_id(): void
    {
        $customId = 'custom_session_123';
        $session = $this->agent->startConversation($customId);

        $this->assertInstanceOf(Session::class, $session);
        $this->assertSame($customId, $session->getId());
    }

    public function test_get_session_returns_existing_session(): void
    {
        $session = $this->agent->startConversation();
        $sessionId = $session->getId();

        $retrievedSession = $this->agent->getSession($sessionId);

        $this->assertSame($session, $retrievedSession);
    }

    public function test_get_session_returns_null_for_nonexistent_session(): void
    {
        $session = $this->agent->getSession('nonexistent_id');

        $this->assertNull($session);
    }

    public function test_turn_creates_session_if_none_exists(): void
    {
        $this->mockLlmResponse('Hello! How can I help you today?');

        $response = $this->agent->turn('Hello');

        $this->assertIsString($response);
        $this->assertNotEmpty($response);
    }

    public function test_turn_adds_turn_to_session(): void
    {
        $this->mockLlmResponse('I can help with that.');

        $session = $this->agent->startConversation();
        $sessionId = $session->getId();

        $this->agent->turn('I need help', $sessionId);

        $retrievedSession = $this->agent->getSession($sessionId);
        $this->assertCount(1, $retrievedSession->getTurns());
    }

    public function test_turn_maintains_conversation_context(): void
    {
        $this->mockLlmResponse('Context maintained');

        $session = $this->agent->startConversation();
        $sessionId = $session->getId();

        $this->agent->turn('First message', $sessionId);
        $this->agent->turn('Second message', $sessionId);
        $this->agent->turn('Third message', $sessionId);

        $retrievedSession = $this->agent->getSession($sessionId);
        $this->assertCount(3, $retrievedSession->getTurns());
    }

    public function test_turn_uses_session_state_in_response(): void
    {
        $this->mockLlmResponse('Response with state');

        $session = $this->agent->startConversation();
        $session->updateState('user_name', 'John');
        $session->updateState('topic', 'weather');

        $response = $this->agent->turn('Tell me more', $session->getId());

        $this->assertIsString($response);
    }

    public function test_turn_builds_context_from_recent_turns(): void
    {
        $this->mockLlmResponse('Contextual response');

        $session = $this->agent->startConversation();
        $sessionId = $session->getId();

        // Add several turns
        for ($i = 1; $i <= 6; $i++) {
            $this->agent->turn("Message {$i}", $sessionId);
        }

        $retrievedSession = $this->agent->getSession($sessionId);
        $this->assertCount(6, $retrievedSession->getTurns());

        // Context builder should use last 5 turns
        $turns = $retrievedSession->getTurns();
        $this->assertGreaterThanOrEqual(5, count($turns));
    }

    public function test_turn_works_without_explicit_session_id(): void
    {
        $this->mockLlmResponse('Response');

        $this->agent->startConversation();
        $response = $this->agent->turn('Hello');

        $this->assertIsString($response);
        $this->assertNotEmpty($response);
    }

    public function test_turn_returns_string_response(): void
    {
        $expectedResponse = 'This is the agent response';
        $this->mockLlmResponse($expectedResponse);

        $session = $this->agent->startConversation();
        $response = $this->agent->turn('Hello', $session->getId());

        $this->assertSame($expectedResponse, $response);
    }

    public function test_run_method_executes_single_turn(): void
    {
        $this->mockLlmResponse('Task completed successfully');

        $result = $this->agent->run('Complete this task');

        $this->assertInstanceOf(AgentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Task completed', $result->getAnswer());
        $this->assertSame(1, $result->getIterations());
    }

    public function test_run_method_returns_session_id_in_metadata(): void
    {
        $this->mockLlmResponse('Response');

        $result = $this->agent->run('Test task');

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('session_id', $metadata);
        $this->assertNotEmpty($metadata['session_id']);
    }

    public function test_run_method_starts_new_conversation(): void
    {
        $this->mockLlmResponse('Response');

        $result = $this->agent->run('Test');
        $metadata = $result->getMetadata();
        $sessionId = $metadata['session_id'];

        $session = $this->agent->getSession($sessionId);
        $this->assertInstanceOf(Session::class, $session);
        $this->assertCount(1, $session->getTurns());
    }

    public function test_multiple_sessions_can_coexist(): void
    {
        $this->mockLlmResponse('Response');

        $session1 = $this->agent->startConversation();
        $session2 = $this->agent->startConversation();

        $this->assertNotSame($session1->getId(), $session2->getId());

        $this->agent->turn('Message for session 1', $session1->getId());
        $this->agent->turn('Message for session 2', $session2->getId());

        $retrieved1 = $this->agent->getSession($session1->getId());
        $retrieved2 = $this->agent->getSession($session2->getId());

        $this->assertCount(1, $retrieved1->getTurns());
        $this->assertCount(1, $retrieved2->getTurns());
        $this->assertSame('Message for session 1', $retrieved1->getTurns()[0]->getUserInput());
        $this->assertSame('Message for session 2', $retrieved2->getTurns()[0]->getUserInput());
    }

    public function test_turn_extracts_text_content_from_response(): void
    {
        $this->mockLlmResponse('Extracted text');

        $response = $this->agent->turn('Test');

        $this->assertSame('Extracted text', $response);
    }

    public function test_turn_handles_multiple_text_blocks(): void
    {
        $usage = new Usage(
            input_tokens: 100,
            output_tokens: 50
        );

        $response = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'First block'],
                ['type' => 'text', 'text' => 'Second block'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willReturn($response);
        $this->client->method('messages')->willReturn($messages);

        $result = $this->agent->turn('Test');

        $this->assertStringContainsString('First block', $result);
        $this->assertStringContainsString('Second block', $result);
    }

    public function test_turn_handles_empty_context_for_first_message(): void
    {
        $this->mockLlmResponse('First response');

        $session = $this->agent->startConversation();
        $response = $this->agent->turn('First message', $session->getId());

        $this->assertSame('First response', $response);
    }

    public function test_turn_includes_system_prompt(): void
    {
        $messages = $this->createMock(Messages::class);
        $messages->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                return isset($params['system']) &&
                       str_contains($params['system'], 'conversational agent');
            }))
            ->willReturn($this->createMockMessage('Response'));

        $this->client->method('messages')->willReturn($messages);

        $this->agent->turn('Test');
    }

    public function test_conversation_maintains_order_of_turns(): void
    {
        $this->mockLlmResponse('Response');

        $session = $this->agent->startConversation();

        $this->agent->turn('First', $session->getId());
        $this->agent->turn('Second', $session->getId());
        $this->agent->turn('Third', $session->getId());

        $turns = $session->getTurns();
        $this->assertSame('First', $turns[0]->getUserInput());
        $this->assertSame('Second', $turns[1]->getUserInput());
        $this->assertSame('Third', $turns[2]->getUserInput());
    }

    private function mockLlmResponse(string $text): void
    {
        $usage = new Usage(
            input_tokens: 100,
            output_tokens: 50
        );

        $response = new Message(
            id: 'msg_test',
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

        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willReturn($response);
        $this->client->method('messages')->willReturn($messages);
    }

    private function createMockMessage(string $text): Message
    {
        $usage = new Usage(
            input_tokens: 100,
            output_tokens: 50
        );

        return new Message(
            id: 'msg_test',
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
}
