<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation\Storage;

use ClaudeAgents\Contracts\SessionSerializerInterface;
use ClaudeAgents\Contracts\SessionStorageInterface;
use ClaudeAgents\Conversation\Session;

/**
 * In-memory session storage implementation (useful for testing).
 */
class InMemorySessionStorage implements SessionStorageInterface
{
    /** @var array<string, Session> */
    private array $sessions = [];

    private SessionSerializerInterface $serializer;

    public function __construct(SessionSerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function save(Session $session): bool
    {
        // Deep clone via serialization to prevent reference issues
        $data = $this->serializer->serialize($session);
        $cloned = $this->serializer->deserialize($data);

        if (! $cloned) {
            return false;
        }

        $this->sessions[$session->getId()] = $cloned;

        return true;
    }

    public function load(string $sessionId): ?Session
    {
        if (! isset($this->sessions[$sessionId])) {
            return null;
        }

        // Return a deep clone
        $data = $this->serializer->serialize($this->sessions[$sessionId]);

        return $this->serializer->deserialize($data);
    }

    public function delete(string $sessionId): bool
    {
        if (! isset($this->sessions[$sessionId])) {
            return false;
        }

        unset($this->sessions[$sessionId]);

        return true;
    }

    public function exists(string $sessionId): bool
    {
        return isset($this->sessions[$sessionId]);
    }

    public function listSessions(): array
    {
        return array_keys($this->sessions);
    }

    public function findByUser(string $userId): array
    {
        $userSessions = [];

        foreach ($this->sessions as $session) {
            if (($session->getState()['user_id'] ?? null) === $userId) {
                $userSessions[] = $session;
            }
        }

        return $userSessions;
    }

    /**
     * Clear all sessions (useful for testing).
     */
    public function clear(): void
    {
        $this->sessions = [];
    }

    /**
     * Get count of stored sessions.
     */
    public function count(): int
    {
        return count($this->sessions);
    }
}
