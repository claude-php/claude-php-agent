<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation\Storage;

use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Storage\JsonSessionSerializer;
use ClaudeAgents\Conversation\Turn;
use PHPUnit\Framework\TestCase;

class JsonSessionSerializerTest extends TestCase
{
    private JsonSessionSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonSessionSerializer();
    }

    public function test_serializes_empty_session(): void
    {
        $session = new Session('test_session');

        $data = $this->serializer->serialize($session);

        $this->assertIsArray($data);
        $this->assertSame('test_session', $data['id']);
        $this->assertSame(0, $data['turn_count']);
        $this->assertEmpty($data['turns']);
        $this->assertSame('1.0', $data['version']);
    }

    public function test_serializes_session_with_turns(): void
    {
        $session = new Session('test_session');
        $session->addTurn(new Turn('Hello', 'Hi'));
        $session->addTurn(new Turn('How are you?', 'I am fine'));

        $data = $this->serializer->serialize($session);

        $this->assertCount(2, $data['turns']);
        $this->assertSame('Hello', $data['turns'][0]['user_input']);
        $this->assertSame('Hi', $data['turns'][0]['agent_response']);
    }

    public function test_serializes_session_with_state(): void
    {
        $session = new Session('test_session');
        $session->setState(['user_id' => '123', 'language' => 'en']);

        $data = $this->serializer->serialize($session);

        $this->assertArrayHasKey('state', $data);
        $this->assertSame('123', $data['state']['user_id']);
        $this->assertSame('en', $data['state']['language']);
    }

    public function test_deserializes_session(): void
    {
        $data = [
            'id' => 'test_session',
            'state' => ['user_id' => '123'],
            'turns' => [
                [
                    'user_input' => 'Hello',
                    'agent_response' => 'Hi',
                    'metadata' => ['key' => 'value'],
                ],
            ],
            'created_at' => microtime(true),
            'last_activity' => microtime(true),
            'turn_count' => 1,
            'version' => '1.0',
        ];

        $session = $this->serializer->deserialize($data);

        $this->assertNotNull($session);
        $this->assertSame('test_session', $session->getId());
        $this->assertCount(1, $session->getTurns());
        $this->assertSame('123', $session->getState()['user_id']);
    }

    public function test_returns_null_for_invalid_data(): void
    {
        $session = $this->serializer->deserialize('invalid');

        $this->assertNull($session);
    }

    public function test_returns_null_for_data_without_id(): void
    {
        $data = ['turns' => []];

        $session = $this->serializer->deserialize($data);

        $this->assertNull($session);
    }

    public function test_round_trip_serialization(): void
    {
        $original = new Session('test_session');
        $original->setState(['user_id' => '123', 'language' => 'en']);
        $original->addTurn(new Turn('Hello', 'Hi', ['timestamp' => time()]));
        $original->addTurn(new Turn('Bye', 'Goodbye'));

        $data = $this->serializer->serialize($original);
        $restored = $this->serializer->deserialize($data);

        $this->assertNotNull($restored);
        $this->assertSame($original->getId(), $restored->getId());
        $this->assertSame($original->getTurnCount(), $restored->getTurnCount());
        $this->assertSame($original->getState(), $restored->getState());
    }
}
