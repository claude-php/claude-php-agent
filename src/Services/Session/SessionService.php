<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Session;

use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\Settings\SettingsService;
use ClaudeAgents\Services\Storage\StorageService;

/**
 * Session service for managing user sessions.
 *
 * Provides session creation, storage, and expiration management.
 */
class SessionService implements ServiceInterface
{
    private bool $ready = false;

    /**
     * @var array<string, Session> In-memory session cache
     */
    private array $sessions = [];

    /**
     * @param SettingsService $settings Settings service
     * @param StorageService $storage Storage service for persistence
     */
    public function __construct(
        private SettingsService $settings,
        private StorageService $storage
    ) {
    }

    public function getName(): string
    {
        return 'session';
    }

    public function initialize(): void
    {
        if ($this->ready) {
            return;
        }

        $this->ready = true;
    }

    public function teardown(): void
    {
        // Save all active sessions
        foreach ($this->sessions as $sessionId => $session) {
            try {
                $this->saveSession($session);
            } catch (\Throwable $e) {
                // Ignore errors during teardown
            }
        }

        $this->sessions = [];
        $this->ready = false;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'ready' => $this->ready,
            'methods' => [
                'createSession' => [
                    'parameters' => ['userId' => 'string', 'data' => 'array'],
                    'return' => 'string',
                    'description' => 'Create a new session',
                ],
                'getSession' => [
                    'parameters' => ['sessionId' => 'string'],
                    'return' => 'array|null',
                    'description' => 'Get a session by ID',
                ],
                'updateSession' => [
                    'parameters' => ['sessionId' => 'string', 'data' => 'array'],
                    'return' => 'void',
                    'description' => 'Update a session',
                ],
                'destroySession' => [
                    'parameters' => ['sessionId' => 'string'],
                    'return' => 'void',
                    'description' => 'Destroy a session',
                ],
                'listSessions' => [
                    'parameters' => ['userId' => 'string'],
                    'return' => 'array',
                    'description' => 'List all sessions for a user',
                ],
            ],
        ];
    }

    /**
     * Create a new session.
     *
     * @param string $userId User identifier
     * @param array<string, mixed> $data Initial session data
     * @return string Session ID
     */
    public function createSession(string $userId, array $data = []): string
    {
        $sessionId = $this->generateSessionId();
        $lifetime = $this->settings->get('session.lifetime', 7200);

        $session = new Session(
            sessionId: $sessionId,
            userId: $userId,
            data: $data,
            createdAt: time(),
            expiresAt: time() + $lifetime
        );

        // Cache in memory
        $this->sessions[$sessionId] = $session;

        // Persist to storage
        $this->saveSession($session);

        return $sessionId;
    }

    /**
     * Get a session by ID.
     *
     * @param string $sessionId Session ID
     * @return array<string, mixed>|null Session data or null if not found/expired
     */
    public function getSession(string $sessionId): ?array
    {
        $session = $this->loadSession($sessionId);

        if ($session === null) {
            return null;
        }

        // Check if expired
        if ($session->isExpired()) {
            $this->destroySession($sessionId);

            return null;
        }

        return $session->data;
    }

    /**
     * Update a session.
     *
     * @param string $sessionId Session ID
     * @param array<string, mixed> $data Data to merge into session
     * @return void
     * @throws \RuntimeException If session not found
     */
    public function updateSession(string $sessionId, array $data): void
    {
        $session = $this->loadSession($sessionId);

        if ($session === null) {
            throw new \RuntimeException("Session not found: {$sessionId}");
        }

        // Check if expired
        if ($session->isExpired()) {
            $this->destroySession($sessionId);
            throw new \RuntimeException("Session expired: {$sessionId}");
        }

        // Merge data
        $session->data = array_merge($session->data, $data);

        // Update in cache
        $this->sessions[$sessionId] = $session;

        // Persist to storage
        $this->saveSession($session);
    }

    /**
     * Destroy a session.
     *
     * @param string $sessionId Session ID
     * @return void
     */
    public function destroySession(string $sessionId): void
    {
        // Remove from cache
        unset($this->sessions[$sessionId]);

        // Delete from storage
        try {
            $this->storage->deleteFile('sessions', "{$sessionId}.json");
        } catch (\RuntimeException $e) {
            // File might not exist, that's ok
        }
    }

    /**
     * List all sessions for a user.
     *
     * @param string $userId User identifier
     * @return array<string> Array of session IDs
     */
    public function listSessions(string $userId): array
    {
        $sessions = [];

        try {
            $files = $this->storage->listFiles('sessions');

            foreach ($files as $file) {
                if (! str_ends_with($file, '.json')) {
                    continue;
                }

                $sessionId = basename($file, '.json');
                $session = $this->loadSession($sessionId);

                if ($session !== null && $session->userId === $userId && ! $session->isExpired()) {
                    $sessions[] = $sessionId;
                }
            }
        } catch (\RuntimeException $e) {
            // Directory might not exist, that's ok
        }

        return $sessions;
    }

    /**
     * Clean up expired sessions.
     *
     * @return int Number of sessions cleaned up
     */
    public function cleanupExpiredSessions(): int
    {
        $count = 0;

        try {
            $files = $this->storage->listFiles('sessions');

            foreach ($files as $file) {
                if (! str_ends_with($file, '.json')) {
                    continue;
                }

                $sessionId = basename($file, '.json');
                $session = $this->loadSession($sessionId);

                if ($session !== null && $session->isExpired()) {
                    $this->destroySession($sessionId);
                    $count++;
                }
            }
        } catch (\RuntimeException $e) {
            // Directory might not exist, that's ok
        }

        return $count;
    }

    /**
     * Extend a session's expiration.
     *
     * @param string $sessionId Session ID
     * @param int $additionalSeconds Additional seconds to add
     * @return void
     * @throws \RuntimeException If session not found
     */
    public function extendSession(string $sessionId, int $additionalSeconds): void
    {
        $session = $this->loadSession($sessionId);

        if ($session === null) {
            throw new \RuntimeException("Session not found: {$sessionId}");
        }

        $session->expiresAt += $additionalSeconds;

        // Update in cache
        $this->sessions[$sessionId] = $session;

        // Persist to storage
        $this->saveSession($session);
    }

    /**
     * Load a session from storage or cache.
     *
     * @param string $sessionId Session ID
     * @return Session|null
     */
    private function loadSession(string $sessionId): ?Session
    {
        // Check cache first
        if (isset($this->sessions[$sessionId])) {
            return $this->sessions[$sessionId];
        }

        // Load from storage
        try {
            $data = $this->storage->getFile('sessions', "{$sessionId}.json");
            $decoded = json_decode($data, true);

            if (! is_array($decoded)) {
                return null;
            }

            $session = Session::fromArray($decoded);

            // Cache in memory
            $this->sessions[$sessionId] = $session;

            return $session;
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * Save a session to storage.
     *
     * @param Session $session Session to save
     * @return void
     */
    private function saveSession(Session $session): void
    {
        $data = json_encode($session->toArray(), JSON_PRETTY_PRINT);
        $this->storage->saveFile('sessions', "{$session->sessionId}.json", $data);
    }

    /**
     * Generate a unique session ID.
     *
     * @return string
     */
    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }
}
