<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services;

use ClaudeAgents\Services\Settings\SettingsService;
use ClaudeAgents\Services\Storage\LocalStorageService;
use ClaudeAgents\Services\Session\SessionService;
use PHPUnit\Framework\TestCase;

class SessionServiceTest extends TestCase
{
    private SessionService $service;
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/claude-agent-test-' . uniqid();

        $settings = new SettingsService(null, [
            'storage.directory' => $this->testDir,
            'session.lifetime' => 3600,
        ]);
        $settings->initialize();

        $storage = new LocalStorageService($settings);
        $storage->initialize();

        $this->service = new SessionService($settings, $storage);
        $this->service->initialize();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    public function testGetName(): void
    {
        $this->assertSame('session', $this->service->getName());
    }

    public function testCreateSession(): void
    {
        $sessionId = $this->service->createSession('user-123', [
            'name' => 'John Doe',
        ]);

        $this->assertNotEmpty($sessionId);
    }

    public function testGetSession(): void
    {
        $data = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $sessionId = $this->service->createSession('user-123', $data);

        $retrieved = $this->service->getSession($sessionId);

        $this->assertSame($data, $retrieved);
    }

    public function testUpdateSession(): void
    {
        $sessionId = $this->service->createSession('user-123', [
            'name' => 'John Doe',
        ]);

        $this->service->updateSession($sessionId, [
            'email' => 'john@example.com',
        ]);

        $data = $this->service->getSession($sessionId);

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertSame('John Doe', $data['name']);
        $this->assertSame('john@example.com', $data['email']);
    }

    public function testDestroySession(): void
    {
        $sessionId = $this->service->createSession('user-123', []);

        $this->assertNotNull($this->service->getSession($sessionId));

        $this->service->destroySession($sessionId);

        $this->assertNull($this->service->getSession($sessionId));
    }

    public function testListSessions(): void
    {
        $session1 = $this->service->createSession('user-123', []);
        $session2 = $this->service->createSession('user-123', []);
        $session3 = $this->service->createSession('user-456', []);

        $user123Sessions = $this->service->listSessions('user-123');
        $user456Sessions = $this->service->listSessions('user-456');

        $this->assertCount(2, $user123Sessions);
        $this->assertCount(1, $user456Sessions);
        $this->assertContains($session1, $user123Sessions);
        $this->assertContains($session2, $user123Sessions);
        $this->assertContains($session3, $user456Sessions);
    }

    public function testExtendSession(): void
    {
        $sessionId = $this->service->createSession('user-123', []);

        $this->service->extendSession($sessionId, 1800);

        // Should still be valid
        $this->assertNotNull($this->service->getSession($sessionId));
    }

    public function testGetNonexistentSession(): void
    {
        $data = $this->service->getSession('nonexistent');

        $this->assertNull($data);
    }

    public function testUpdateNonexistentSession(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session not found');

        $this->service->updateSession('nonexistent', []);
    }

    private function removeDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
