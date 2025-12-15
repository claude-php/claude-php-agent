<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation;

use ClaudeAgents\Contracts\SessionStorageInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Conversation Manager - Manages multiple conversation sessions.
 */
class ConversationManager
{
    private array $sessions = [];
    private LoggerInterface $logger;
    private int $maxSessions;
    private int $sessionTimeout;
    private ?SessionStorageInterface $storage;

    public function __construct(array $options = [])
    {
        $this->maxSessions = $options['max_sessions'] ?? 1000;
        $this->sessionTimeout = $options['session_timeout'] ?? 3600; // 1 hour
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->storage = $options['storage'] ?? null;
    }

    public function createSession(?string $userId = null): Session
    {
        $this->cleanupExpiredSessions();

        $session = new Session();

        if ($userId) {
            $session->updateState('user_id', $userId);
        }

        $this->sessions[$session->getId()] = $session;
        $this->logger->info("Created session: {$session->getId()}");

        // Persist if storage is configured
        if ($this->storage) {
            $this->storage->save($session);
        }

        return $session;
    }

    public function getSession(string $sessionId): ?Session
    {
        // Check in-memory cache first
        if (isset($this->sessions[$sessionId])) {
            return $this->sessions[$sessionId];
        }

        // Try to load from storage
        if ($this->storage) {
            $session = $this->storage->load($sessionId);
            if ($session) {
                $this->sessions[$sessionId] = $session;

                return $session;
            }
        }

        return null;
    }

    public function deleteSession(string $sessionId): bool
    {
        $deleted = false;

        if (isset($this->sessions[$sessionId])) {
            unset($this->sessions[$sessionId]);
            $deleted = true;
        }

        // Also delete from storage
        if ($this->storage && $this->storage->exists($sessionId)) {
            $this->storage->delete($sessionId);
            $deleted = true;
        }

        if ($deleted) {
            $this->logger->info("Deleted session: {$sessionId}");
        }

        return $deleted;
    }

    public function getSessionsByUser(string $userId): array
    {
        // Get from in-memory sessions
        $sessions = array_filter($this->sessions, function ($session) use ($userId) {
            return ($session->getState()['user_id'] ?? null) === $userId;
        });

        // Also check storage if available
        if ($this->storage) {
            $storageSessions = $this->storage->findByUser($userId);
            foreach ($storageSessions as $session) {
                if (! isset($sessions[$session->getId()])) {
                    $sessions[$session->getId()] = $session;
                }
            }
        }

        return array_values($sessions);
    }

    /**
     * Save a session to storage (if configured).
     */
    public function saveSession(Session $session): bool
    {
        if (! $this->storage) {
            return false;
        }

        $this->sessions[$session->getId()] = $session;

        return $this->storage->save($session);
    }

    /**
     * Load all sessions from storage into memory.
     */
    public function loadAllFromStorage(): int
    {
        if (! $this->storage) {
            return 0;
        }

        $sessionIds = $this->storage->listSessions();
        $loaded = 0;

        foreach ($sessionIds as $sessionId) {
            if (! isset($this->sessions[$sessionId])) {
                $session = $this->storage->load($sessionId);
                if ($session) {
                    $this->sessions[$sessionId] = $session;
                    $loaded++;
                }
            }
        }

        $this->logger->info("Loaded {$loaded} sessions from storage");

        return $loaded;
    }

    private function cleanupExpiredSessions(): void
    {
        $now = microtime(true);
        $expired = [];

        foreach ($this->sessions as $id => $session) {
            $lastActivity = $session->getLastActivity() ?? $session->getCreatedAt();
            if (($now - $lastActivity) > $this->sessionTimeout) {
                $expired[] = $id;
            }
        }

        foreach ($expired as $id) {
            unset($this->sessions[$id]);
        }

        if (! empty($expired)) {
            $this->logger->info('Cleaned up ' . count($expired) . ' expired sessions');
        }
    }
}
