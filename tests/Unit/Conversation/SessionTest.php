<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Turn;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    public function test_creates_session_with_generated_id(): void
    {
        $session = new Session();

        $this->assertNotEmpty($session->getId());
        $this->assertStringStartsWith('session_', $session->getId());
    }

    public function test_creates_session_with_custom_id(): void
    {
        $customId = 'my_custom_session_id';
        $session = new Session($customId);

        $this->assertSame($customId, $session->getId());
    }

    public function test_get_id(): void
    {
        $session = new Session('test_id');

        $this->assertSame('test_id', $session->getId());
    }

    public function test_add_turn(): void
    {
        $session = new Session();
        $turn = new Turn('User input', 'Agent response');

        $session->addTurn($turn);

        $this->assertCount(1, $session->getTurns());
    }

    public function test_add_multiple_turns(): void
    {
        $session = new Session();

        $turn1 = new Turn('First input', 'First response');
        $turn2 = new Turn('Second input', 'Second response');
        $turn3 = new Turn('Third input', 'Third response');

        $session->addTurn($turn1);
        $session->addTurn($turn2);
        $session->addTurn($turn3);

        $this->assertCount(3, $session->getTurns());
    }

    public function test_get_turns_returns_array(): void
    {
        $session = new Session();

        $turns = $session->getTurns();

        $this->assertIsArray($turns);
        $this->assertEmpty($turns);
    }

    public function test_get_turns_returns_turns_in_order(): void
    {
        $session = new Session();

        $turn1 = new Turn('Input 1', 'Response 1');
        $turn2 = new Turn('Input 2', 'Response 2');

        $session->addTurn($turn1);
        $session->addTurn($turn2);

        $turns = $session->getTurns();

        $this->assertSame($turn1, $turns[0]);
        $this->assertSame($turn2, $turns[1]);
    }

    public function test_set_state(): void
    {
        $session = new Session();
        $state = ['user' => 'John', 'language' => 'en'];

        $session->setState($state);

        $this->assertSame($state, $session->getState());
    }

    public function test_get_state_returns_empty_array_by_default(): void
    {
        $session = new Session();

        $this->assertSame([], $session->getState());
    }

    public function test_update_state_adds_new_key(): void
    {
        $session = new Session();

        $session->updateState('key1', 'value1');

        $state = $session->getState();
        $this->assertArrayHasKey('key1', $state);
        $this->assertSame('value1', $state['key1']);
    }

    public function test_update_state_updates_existing_key(): void
    {
        $session = new Session();

        $session->updateState('key1', 'initial_value');
        $session->updateState('key1', 'updated_value');

        $state = $session->getState();
        $this->assertSame('updated_value', $state['key1']);
    }

    public function test_update_state_preserves_other_keys(): void
    {
        $session = new Session();

        $session->updateState('key1', 'value1');
        $session->updateState('key2', 'value2');

        $state = $session->getState();
        $this->assertCount(2, $state);
        $this->assertSame('value1', $state['key1']);
        $this->assertSame('value2', $state['key2']);
    }

    public function test_get_created_at(): void
    {
        $beforeCreation = microtime(true);
        $session = new Session();
        $afterCreation = microtime(true);

        $createdAt = $session->getCreatedAt();

        $this->assertGreaterThanOrEqual($beforeCreation, $createdAt);
        $this->assertLessThanOrEqual($afterCreation, $createdAt);
    }

    public function test_get_last_activity_is_null_initially(): void
    {
        $session = new Session();

        $this->assertNull($session->getLastActivity());
    }

    public function test_add_turn_updates_last_activity(): void
    {
        $session = new Session();
        $turn = new Turn('Input', 'Response');

        $beforeAdd = microtime(true);
        $session->addTurn($turn);
        $afterAdd = microtime(true);

        $lastActivity = $session->getLastActivity();
        $this->assertNotNull($lastActivity);
        $this->assertGreaterThanOrEqual($beforeAdd, $lastActivity);
        $this->assertLessThanOrEqual($afterAdd, $lastActivity);
    }

    public function test_get_turn_count(): void
    {
        $session = new Session();

        $this->assertSame(0, $session->getTurnCount());

        $session->addTurn(new Turn('Input 1', 'Response 1'));
        $this->assertSame(1, $session->getTurnCount());

        $session->addTurn(new Turn('Input 2', 'Response 2'));
        $this->assertSame(2, $session->getTurnCount());
    }

    public function test_session_tracks_multiple_turn_activities(): void
    {
        $session = new Session();

        $session->addTurn(new Turn('First', 'Response 1'));
        $firstActivity = $session->getLastActivity();

        usleep(1000); // Small delay to ensure different timestamp

        $session->addTurn(new Turn('Second', 'Response 2'));
        $secondActivity = $session->getLastActivity();

        $this->assertNotNull($firstActivity);
        $this->assertNotNull($secondActivity);
        $this->assertGreaterThan($firstActivity, $secondActivity);
    }

    public function test_state_can_store_complex_data(): void
    {
        $session = new Session();

        $complexState = [
            'user' => ['id' => 123, 'name' => 'John'],
            'preferences' => ['theme' => 'dark', 'language' => 'en'],
            'history' => [1, 2, 3, 4, 5],
        ];

        $session->setState($complexState);

        $this->assertSame($complexState, $session->getState());
    }

    public function test_set_state_replaces_entire_state(): void
    {
        $session = new Session();

        $session->setState(['key1' => 'value1', 'key2' => 'value2']);
        $session->setState(['key3' => 'value3']);

        $state = $session->getState();
        $this->assertCount(1, $state);
        $this->assertArrayHasKey('key3', $state);
        $this->assertArrayNotHasKey('key1', $state);
    }
}
