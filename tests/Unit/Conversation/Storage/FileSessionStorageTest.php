<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation\Storage;

use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Storage\FileSessionStorage;
use ClaudeAgents\Conversation\Storage\JsonSessionSerializer;
use ClaudeAgents\Conversation\Turn;
use PHPUnit\Framework\TestCase;

class FileSessionStorageTest extends TestCase
{
    private FileSessionStorage $storage;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_sessions_' . uniqid();
        $serializer = new JsonSessionSerializer();
        $this->storage = new FileSessionStorage($this->tempDir, $serializer);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function test_saves_session_to_file(): void
    {
        $session = new Session('test_session');
        $session->updateState('user_id', 'user_123');

        $result = $this->storage->save($session);

        $this->assertTrue($result);
        $this->assertTrue($this->storage->exists('test_session'));
    }

    public function test_loads_session_from_file(): void
    {
        $session = new Session('test_session');
        $session->updateState('user_id', 'user_123');
        $session->addTurn(new Turn('Hello', 'Hi there'));

        $this->storage->save($session);
        $loaded = $this->storage->load('test_session');

        $this->assertNotNull($loaded);
        $this->assertSame('test_session', $loaded->getId());
        $this->assertSame('user_123', $loaded->getState()['user_id']);
        $this->assertCount(1, $loaded->getTurns());
    }

    public function test_returns_null_for_nonexistent_session(): void
    {
        $loaded = $this->storage->load('nonexistent');

        $this->assertNull($loaded);
    }

    public function test_deletes_session_file(): void
    {
        $session = new Session('test_session');
        $this->storage->save($session);

        $result = $this->storage->delete('test_session');

        $this->assertTrue($result);
        $this->assertFalse($this->storage->exists('test_session'));
    }

    public function test_delete_returns_false_for_nonexistent_session(): void
    {
        $result = $this->storage->delete('nonexistent');

        $this->assertFalse($result);
    }

    public function test_lists_all_sessions(): void
    {
        $session1 = new Session('session_1');
        $session2 = new Session('session_2');
        $session3 = new Session('session_3');

        $this->storage->save($session1);
        $this->storage->save($session2);
        $this->storage->save($session3);

        $sessions = $this->storage->listSessions();

        $this->assertCount(3, $sessions);
        $this->assertContains('session_1', $sessions);
        $this->assertContains('session_2', $sessions);
        $this->assertContains('session_3', $sessions);
    }

    public function test_finds_sessions_by_user(): void
    {
        $session1 = new Session('session_1');
        $session1->updateState('user_id', 'user_123');

        $session2 = new Session('session_2');
        $session2->updateState('user_id', 'user_123');

        $session3 = new Session('session_3');
        $session3->updateState('user_id', 'user_456');

        $this->storage->save($session1);
        $this->storage->save($session2);
        $this->storage->save($session3);

        $userSessions = $this->storage->findByUser('user_123');

        $this->assertCount(2, $userSessions);
    }

    public function test_creates_storage_directory_if_not_exists(): void
    {
        $newDir = sys_get_temp_dir() . '/new_test_dir_' . uniqid();
        $serializer = new JsonSessionSerializer();

        new FileSessionStorage($newDir, $serializer);

        $this->assertTrue(is_dir($newDir));

        // Cleanup
        rmdir($newDir);
    }
}
