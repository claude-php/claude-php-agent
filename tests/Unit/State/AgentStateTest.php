<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\State;

use ClaudeAgents\State\AgentState;
use ClaudeAgents\State\Goal;
use ClaudeAgents\State\StateConfig;
use PHPUnit\Framework\TestCase;

class AgentStateTest extends TestCase
{
    public function testConstruction(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(
            sessionNumber: 1,
            goal: $goal
        );

        $this->assertEquals(1, $state->getSessionNumber());
        $this->assertSame($goal, $state->getGoal());
        $this->assertEmpty($state->getConversationHistory());
        $this->assertEmpty($state->getActionHistory());
    }

    public function testIncrementSession(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $state->incrementSession();

        $this->assertEquals(2, $state->getSessionNumber());
    }

    public function testAddMessage(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $state->addMessage(['role' => 'user', 'content' => 'Hello']);

        $messages = $state->getConversationHistory();

        $this->assertCount(1, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertArrayHasKey('timestamp', $messages[0]);
    }

    public function testRecordAction(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $state->recordAction(['action' => 'test', 'result' => 'success']);

        $actions = $state->getActionHistory();

        $this->assertCount(1, $actions);
        $this->assertEquals('test', $actions[0]['action']);
        $this->assertArrayHasKey('timestamp', $actions[0]);
    }

    public function testMetadata(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $state->setMetadataValue('key', 'value');

        $this->assertEquals('value', $state->getMetadataValue('key'));
        $this->assertEquals(['key' => 'value'], $state->getMetadata());
    }

    public function testTimestamps(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $this->assertGreaterThan(0, $state->getCreatedAt());
        $this->assertGreaterThan(0, $state->getUpdatedAt());
    }

    public function testGetSessionDuration(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $duration = $state->getSessionDuration();

        $this->assertGreaterThanOrEqual(0, $duration);
    }

    public function testToArray(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);
        $state->addMessage(['role' => 'user', 'content' => 'Hello']);
        $state->recordAction(['action' => 'test']);

        $array = $state->toArray();

        $this->assertArrayHasKey('session_number', $array);
        $this->assertArrayHasKey('goal', $array);
        $this->assertArrayHasKey('conversation_history', $array);
        $this->assertArrayHasKey('action_history', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function testCreateFromArray(): void
    {
        $data = [
            'session_number' => 3,
            'goal' => [
                'description' => 'Test goal',
                'status' => 'in_progress',
                'progress_percentage' => 50,
                'completed_subgoals' => ['Sub 1'],
                'metadata' => ['key' => 'value'],
            ],
            'conversation_history' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'action_history' => [
                ['action' => 'test', 'result' => 'success'],
            ],
            'metadata' => ['state_key' => 'state_value'],
            'created_at' => 1234567890,
            'updated_at' => 1234567900,
        ];

        $state = AgentState::createFromArray($data);

        $this->assertEquals(3, $state->getSessionNumber());
        $this->assertEquals('Test goal', $state->getGoal()->getDescription());
        $this->assertEquals('in_progress', $state->getGoal()->getStatus());
        $this->assertCount(1, $state->getConversationHistory());
        $this->assertCount(1, $state->getActionHistory());
        $this->assertEquals('state_value', $state->getMetadataValue('state_key'));
    }

    public function testCreateFromArrayWithDefaults(): void
    {
        $data = [];

        $state = AgentState::createFromArray($data);

        $this->assertEquals(1, $state->getSessionNumber());
        $this->assertEquals('Unknown goal', $state->getGoal()->getDescription());
        $this->assertEmpty($state->getConversationHistory());
        $this->assertEmpty($state->getActionHistory());
    }

    public function testInvalidSessionNumberThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Session number must be at least 1');

        new AgentState(sessionNumber: 0, goal: new Goal('Test'));
    }

    public function testGetId(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $this->assertNotEmpty($state->getId());
        $this->assertStringStartsWith('state_', $state->getId());
    }

    public function testGetConfig(): void
    {
        $goal = new Goal('Test goal');
        $config = StateConfig::production();
        $state = new AgentState(sessionNumber: 1, goal: $goal, config: $config);

        $this->assertSame($config, $state->getConfig());
    }

    public function testGetRecentMessages(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $state->addMessage(['role' => 'user', 'content' => 'Message 1']);
        $state->addMessage(['role' => 'user', 'content' => 'Message 2']);
        $state->addMessage(['role' => 'user', 'content' => 'Message 3']);

        $recent = $state->getRecentMessages(2);

        $this->assertCount(2, $recent);
        $this->assertEquals('Message 2', $recent[0]['content']);
        $this->assertEquals('Message 3', $recent[1]['content']);
    }

    public function testClearConversationHistory(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $state->addMessage(['role' => 'user', 'content' => 'Message 1']);
        $state->clearConversationHistory();

        $this->assertEmpty($state->getConversationHistory());
    }

    public function testGetRecentActions(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $state->recordAction(['action' => 'action1']);
        $state->recordAction(['action' => 'action2']);
        $state->recordAction(['action' => 'action3']);

        $recent = $state->getRecentActions(2);

        $this->assertCount(2, $recent);
        $this->assertEquals('action2', $recent[0]['action']);
        $this->assertEquals('action3', $recent[1]['action']);
    }

    public function testClearActionHistory(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $state->recordAction(['action' => 'test']);
        $state->clearActionHistory();

        $this->assertEmpty($state->getActionHistory());
    }

    public function testClearAllHistory(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $state->addMessage(['role' => 'user', 'content' => 'Message']);
        $state->recordAction(['action' => 'test']);
        $state->clearAllHistory();

        $this->assertEmpty($state->getConversationHistory());
        $this->assertEmpty($state->getActionHistory());
    }

    public function testGetLifetimeDuration(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        sleep(1);

        $duration = $state->getLifetimeDuration();
        $this->assertGreaterThanOrEqual(1, $duration);
    }

    public function testGetIdleTime(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        sleep(1);

        $idle = $state->getIdleTime();
        $this->assertGreaterThanOrEqual(1, $idle);
    }

    public function testGetStateSize(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $size = $state->getStateSize();
        $this->assertGreaterThan(0, $size);
    }

    public function testGetStatistics(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);
        $state->addMessage(['role' => 'user', 'content' => 'Test']);

        $stats = $state->getStatistics();

        $this->assertArrayHasKey('session_number', $stats);
        $this->assertArrayHasKey('conversation_count', $stats);
        $this->assertArrayHasKey('action_count', $stats);
        $this->assertArrayHasKey('goal_progress', $stats);
        $this->assertArrayHasKey('lifetime_duration', $stats);
        $this->assertEquals(1, $stats['conversation_count']);
    }

    public function testHistoryLimits(): void
    {
        $goal = new Goal('Test goal');
        $config = new StateConfig(maxConversationHistory: 2, maxActionHistory: 2);
        $state = new AgentState(sessionNumber: 1, goal: $goal, config: $config);

        // Add 3 messages
        $state->addMessage(['role' => 'user', 'content' => 'Message 1']);
        $state->addMessage(['role' => 'user', 'content' => 'Message 2']);
        $state->addMessage(['role' => 'user', 'content' => 'Message 3']);

        // Should only keep last 2
        $this->assertCount(2, $state->getConversationHistory());

        // Add 3 actions
        $state->recordAction(['action' => 'action1']);
        $state->recordAction(['action' => 'action2']);
        $state->recordAction(['action' => 'action3']);

        // Should only keep last 2
        $this->assertCount(2, $state->getActionHistory());
    }

    public function testToJson(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $json = $state->toJson();

        $this->assertJson($json);
        $data = json_decode($json, true);
        $this->assertEquals(1, $data['session_number']);
    }

    public function testCreateFromJson(): void
    {
        $json = json_encode([
            'session_number' => 5,
            'goal' => [
                'description' => 'Test goal',
                'status' => 'in_progress',
                'progress_percentage' => 50,
            ],
            'conversation_history' => [],
            'action_history' => [],
            'metadata' => [],
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $state = AgentState::createFromJson($json);

        $this->assertEquals(5, $state->getSessionNumber());
        $this->assertEquals('Test goal', $state->getGoal()->getDescription());
    }

    public function testGetStateId(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $this->assertEquals($state->getId(), $state->getStateId());
    }

    public function testGetVersion(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $this->assertEquals('1.0', $state->getVersion());
    }

    public function testCreateFromArrayPreservesConfig(): void
    {
        $data = [
            'session_number' => 1,
            'goal' => ['description' => 'Test'],
            'config' => [
                'max_conversation_history' => 500,
                'max_action_history' => 300,
                'compress_history' => true,
                'atomic_writes' => false,
                'backup_retention' => 10,
                'version' => 2,
            ],
        ];

        $state = AgentState::createFromArray($data);
        $config = $state->getConfig();

        $this->assertEquals(500, $config->maxConversationHistory);
        $this->assertEquals(300, $config->maxActionHistory);
        $this->assertTrue($config->compressHistory);
        $this->assertFalse($config->atomicWrites);
        $this->assertEquals(10, $config->backupRetention);
        $this->assertEquals(2, $config->version);
    }
}
