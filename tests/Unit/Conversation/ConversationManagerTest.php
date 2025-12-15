<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use ClaudeAgents\Conversation\ConversationManager;
use ClaudeAgents\Conversation\Session;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ConversationManagerTest extends TestCase
{
    private ConversationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ConversationManager();
    }

    public function test_creates_manager_with_default_options(): void
    {
        $manager = new ConversationManager();

        $this->assertInstanceOf(ConversationManager::class, $manager);
    }

    public function test_creates_manager_with_custom_options(): void
    {
        $logger = new NullLogger();
        $manager = new ConversationManager([
            'max_sessions' => 500,
            'session_timeout' => 7200,
            'logger' => $logger,
        ]);

        $this->assertInstanceOf(ConversationManager::class, $manager);
    }

    public function test_create_session_returns_session(): void
    {
        $session = $this->manager->createSession();

        $this->assertInstanceOf(Session::class, $session);
        $this->assertNotEmpty($session->getId());
    }

    public function test_create_session_with_user_id(): void
    {
        $userId = 'user_123';
        $session = $this->manager->createSession($userId);

        $state = $session->getState();
        $this->assertArrayHasKey('user_id', $state);
        $this->assertSame($userId, $state['user_id']);
    }

    public function test_get_session_returns_created_session(): void
    {
        $session = $this->manager->createSession();
        $sessionId = $session->getId();

        $retrieved = $this->manager->getSession($sessionId);

        $this->assertSame($session, $retrieved);
    }

    public function test_get_session_returns_null_for_nonexistent_session(): void
    {
        $session = $this->manager->getSession('nonexistent_id');

        $this->assertNull($session);
    }

    public function test_delete_session_removes_session(): void
    {
        $session = $this->manager->createSession();
        $sessionId = $session->getId();

        $result = $this->manager->deleteSession($sessionId);

        $this->assertTrue($result);
        $this->assertNull($this->manager->getSession($sessionId));
    }

    public function test_delete_session_returns_false_for_nonexistent_session(): void
    {
        $result = $this->manager->deleteSession('nonexistent_id');

        $this->assertFalse($result);
    }

    public function test_create_multiple_sessions(): void
    {
        $session1 = $this->manager->createSession();
        $session2 = $this->manager->createSession();
        $session3 = $this->manager->createSession();

        $this->assertNotSame($session1->getId(), $session2->getId());
        $this->assertNotSame($session2->getId(), $session3->getId());
        $this->assertNotSame($session1->getId(), $session3->getId());
    }

    public function test_get_sessions_by_user(): void
    {
        $userId = 'user_456';

        $session1 = $this->manager->createSession($userId);
        $session2 = $this->manager->createSession($userId);
        $session3 = $this->manager->createSession('other_user');

        $userSessions = $this->manager->getSessionsByUser($userId);

        $this->assertCount(2, $userSessions);
        $this->assertContains($session1, $userSessions);
        $this->assertContains($session2, $userSessions);
        $this->assertNotContains($session3, $userSessions);
    }

    public function test_get_sessions_by_user_returns_empty_array_when_none_found(): void
    {
        $this->manager->createSession('user_1');
        $this->manager->createSession('user_2');

        $sessions = $this->manager->getSessionsByUser('user_3');

        $this->assertIsArray($sessions);
        $this->assertEmpty($sessions);
    }

    public function test_get_sessions_by_user_returns_empty_for_sessions_without_user_id(): void
    {
        $this->manager->createSession(); // No user ID
        $this->manager->createSession(); // No user ID

        $sessions = $this->manager->getSessionsByUser('user_123');

        $this->assertEmpty($sessions);
    }

    public function test_sessions_persist_in_manager(): void
    {
        $session1 = $this->manager->createSession('user_1');
        $session2 = $this->manager->createSession('user_2');

        // Retrieve them later
        $retrieved1 = $this->manager->getSession($session1->getId());
        $retrieved2 = $this->manager->getSession($session2->getId());

        $this->assertSame($session1, $retrieved1);
        $this->assertSame($session2, $retrieved2);
    }

    public function test_cleanup_expired_sessions_is_called_on_create(): void
    {
        // Create manager with very short timeout
        $manager = new ConversationManager([
            'session_timeout' => 0, // Immediate expiration
        ]);

        $session1 = $manager->createSession();
        $sessionId = $session1->getId();

        // Wait a moment
        usleep(100);

        // Creating a new session should trigger cleanup
        $session2 = $manager->createSession();

        // Original session should be cleaned up
        $retrieved = $manager->getSession($sessionId);
        $this->assertNull($retrieved);

        // New session should exist
        $this->assertNotNull($manager->getSession($session2->getId()));
    }

    public function test_sessions_with_recent_activity_are_not_cleaned_up(): void
    {
        $manager = new ConversationManager([
            'session_timeout' => 3600, // 1 hour
        ]);

        $session = $manager->createSession();
        $sessionId = $session->getId();

        // Create another session to trigger cleanup
        $manager->createSession();

        // Original session should still exist (not expired)
        $retrieved = $manager->getSession($sessionId);
        $this->assertNotNull($retrieved);
    }

    public function test_manager_handles_many_sessions(): void
    {
        $sessionIds = [];

        for ($i = 0; $i < 100; $i++) {
            $session = $this->manager->createSession("user_{$i}");
            $sessionIds[] = $session->getId();
        }

        // All sessions should be retrievable
        foreach ($sessionIds as $id) {
            $this->assertNotNull($this->manager->getSession($id));
        }
    }

    public function test_session_state_is_maintained(): void
    {
        $session = $this->manager->createSession();
        $session->updateState('custom_key', 'custom_value');

        $retrieved = $this->manager->getSession($session->getId());

        $state = $retrieved->getState();
        $this->assertArrayHasKey('custom_key', $state);
        $this->assertSame('custom_value', $state['custom_key']);
    }

    public function test_deleted_session_cannot_be_deleted_again(): void
    {
        $session = $this->manager->createSession();
        $sessionId = $session->getId();

        $firstDelete = $this->manager->deleteSession($sessionId);
        $secondDelete = $this->manager->deleteSession($sessionId);

        $this->assertTrue($firstDelete);
        $this->assertFalse($secondDelete);
    }
}
