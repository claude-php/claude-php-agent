<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\Conversation\Session;

/**
 * Interface for persisting conversation sessions.
 */
interface SessionStorageInterface
{
    /**
     * Save a session to persistent storage.
     *
     * @param Session $session The session to save
     * @return bool True if successful
     */
    public function save(Session $session): bool;

    /**
     * Load a session from persistent storage.
     *
     * @param string $sessionId The session ID to load
     * @return Session|null The loaded session or null if not found
     */
    public function load(string $sessionId): ?Session;

    /**
     * Delete a session from persistent storage.
     *
     * @param string $sessionId The session ID to delete
     * @return bool True if successful
     */
    public function delete(string $sessionId): bool;

    /**
     * Check if a session exists in storage.
     *
     * @param string $sessionId The session ID to check
     * @return bool True if exists
     */
    public function exists(string $sessionId): bool;

    /**
     * List all session IDs in storage.
     *
     * @return array<string> Array of session IDs
     */
    public function listSessions(): array;

    /**
     * Find sessions by user ID.
     *
     * @param string $userId The user ID to search for
     * @return array<Session> Array of sessions
     */
    public function findByUser(string $userId): array;
}
