<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\MCP;

use ClaudeAgents\MCP\SessionManager;
use PHPUnit\Framework\TestCase;

class SessionManagerTest extends TestCase
{
    private SessionManager $sessionManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionManager = new SessionManager(3600);
    }

    public function testGetSession(): void
    {
        $session = $this->sessionManager->getSession('test-session');
        
        $this->assertIsArray($session);
        $this->assertEquals('test-session', $session['id']);
        $this->assertArrayHasKey('created_at', $session);
        $this->assertArrayHasKey('last_accessed', $session);
    }

    public function testHasSession(): void
    {
        $this->assertFalse($this->sessionManager->hasSession('new-session'));
        
        $this->sessionManager->getSession('new-session');
        
        $this->assertTrue($this->sessionManager->hasSession('new-session'));
    }

    public function testSetAndGetSessionData(): void
    {
        $sessionId = 'test-session';
        
        $this->sessionManager->setSessionData($sessionId, 'key1', 'value1');
        
        $value = $this->sessionManager->getSessionData($sessionId, 'key1');
        $this->assertEquals('value1', $value);
    }

    public function testGetSessionDataDefault(): void
    {
        $value = $this->sessionManager->getSessionData('nonexistent', 'key', 'default');
        $this->assertEquals('default', $value);
    }

    public function testGetMemory(): void
    {
        $memory = $this->sessionManager->getMemory('test-session');
        $this->assertNotNull($memory);
    }

    public function testDestroySession(): void
    {
        $sessionId = 'destroy-test';
        
        $this->sessionManager->getSession($sessionId);
        $this->assertTrue($this->sessionManager->hasSession($sessionId));
        
        $this->sessionManager->destroySession($sessionId);
        $this->assertFalse($this->sessionManager->hasSession($sessionId));
    }

    public function testGetActiveSessions(): void
    {
        $this->sessionManager->getSession('session1');
        $this->sessionManager->getSession('session2');
        
        $sessions = $this->sessionManager->getActiveSessions();
        
        $this->assertIsArray($sessions);
        $this->assertContains('session1', $sessions);
        $this->assertContains('session2', $sessions);
    }

    public function testCount(): void
    {
        $this->sessionManager->clearAll();
        
        $this->assertEquals(0, $this->sessionManager->count());
        
        $this->sessionManager->getSession('session1');
        $this->sessionManager->getSession('session2');
        
        $this->assertEquals(2, $this->sessionManager->count());
    }

    public function testClearAll(): void
    {
        $this->sessionManager->getSession('session1');
        $this->sessionManager->getSession('session2');
        
        $this->sessionManager->clearAll();
        
        $this->assertEquals(0, $this->sessionManager->count());
    }
}
