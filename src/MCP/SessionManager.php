<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP;

use ClaudeAgents\Memory\ConversationMemory;

/**
 * Manages isolated sessions for MCP clients.
 *
 * Provides per-client session isolation, memory persistence, and cleanup.
 */
class SessionManager
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $sessions = [];

    /**
     * @var array<string, ConversationMemory>
     */
    private array $memories = [];

    public function __construct(
        private readonly int $sessionTimeout = 3600
    ) {
    }

    /**
     * Create or get a session for a client.
     *
     * @return array<string, mixed>
     */
    public function getSession(string $clientId): array
    {
        if (!isset($this->sessions[$clientId])) {
            $this->sessions[$clientId] = [
                'id' => $clientId,
                'created_at' => time(),
                'last_accessed' => time(),
                'data' => [],
            ];
        } else {
            $this->sessions[$clientId]['last_accessed'] = time();
        }

        $this->cleanupExpiredSessions();

        return $this->sessions[$clientId];
    }

    /**
     * Get memory for a session.
     */
    public function getMemory(string $clientId): ConversationMemory
    {
        if (!isset($this->memories[$clientId])) {
            $this->memories[$clientId] = new ConversationMemory();
        }

        return $this->memories[$clientId];
    }

    /**
     * Set session data.
     *
     * @param mixed $value
     */
    public function setSessionData(string $clientId, string $key, $value): void
    {
        $session = $this->getSession($clientId);
        $session['data'][$key] = $value;
        $this->sessions[$clientId] = $session;
    }

    /**
     * Get session data.
     *
     * @return mixed
     */
    public function getSessionData(string $clientId, string $key, $default = null)
    {
        $session = $this->getSession($clientId);
        return $session['data'][$key] ?? $default;
    }

    /**
     * Check if session exists.
     */
    public function hasSession(string $clientId): bool
    {
        return isset($this->sessions[$clientId]);
    }

    /**
     * Destroy a session.
     */
    public function destroySession(string $clientId): void
    {
        unset($this->sessions[$clientId]);
        unset($this->memories[$clientId]);
    }

    /**
     * Cleanup expired sessions.
     */
    public function cleanupExpiredSessions(): void
    {
        $now = time();
        
        foreach ($this->sessions as $clientId => $session) {
            if ($now - $session['last_accessed'] > $this->sessionTimeout) {
                $this->destroySession($clientId);
            }
        }
    }

    /**
     * Get all active sessions.
     *
     * @return array<string>
     */
    public function getActiveSessions(): array
    {
        $this->cleanupExpiredSessions();
        return array_keys($this->sessions);
    }

    /**
     * Get session count.
     */
    public function count(): int
    {
        $this->cleanupExpiredSessions();
        return count($this->sessions);
    }

    /**
     * Clear all sessions.
     */
    public function clearAll(): void
    {
        $this->sessions = [];
        $this->memories = [];
    }
}
