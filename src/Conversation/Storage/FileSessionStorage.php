<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation\Storage;

use ClaudeAgents\Contracts\SessionSerializerInterface;
use ClaudeAgents\Contracts\SessionStorageInterface;
use ClaudeAgents\Conversation\Session;

/**
 * File-based session storage implementation.
 */
class FileSessionStorage implements SessionStorageInterface
{
    private string $storageDir;
    private SessionSerializerInterface $serializer;

    public function __construct(string $storageDir, SessionSerializerInterface $serializer)
    {
        $this->storageDir = rtrim($storageDir, '/');
        $this->serializer = $serializer;

        if (! is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0o755, true);
        }
    }

    public function save(Session $session): bool
    {
        $filePath = $this->getFilePath($session->getId());
        $data = $this->serializer->serialize($session);

        return file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    public function load(string $sessionId): ?Session
    {
        $filePath = $this->getFilePath($sessionId);

        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null) {
            return null;
        }

        return $this->serializer->deserialize($data);
    }

    public function delete(string $sessionId): bool
    {
        $filePath = $this->getFilePath($sessionId);

        if (! file_exists($filePath)) {
            return false;
        }

        return unlink($filePath);
    }

    public function exists(string $sessionId): bool
    {
        return file_exists($this->getFilePath($sessionId));
    }

    public function listSessions(): array
    {
        $sessions = [];
        $files = glob($this->storageDir . '/session_*.json');

        foreach ($files as $file) {
            $filename = basename($file, '.json');
            $sessionId = substr($filename, 8); // Remove "session_" prefix
            $sessions[] = $sessionId;
        }

        return $sessions;
    }

    public function findByUser(string $userId): array
    {
        $userSessions = [];
        $sessionIds = $this->listSessions();

        foreach ($sessionIds as $sessionId) {
            $session = $this->load($sessionId);
            if ($session && ($session->getState()['user_id'] ?? null) === $userId) {
                $userSessions[] = $session;
            }
        }

        return $userSessions;
    }

    private function getFilePath(string $sessionId): string
    {
        // Sanitize session ID for filename
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId);

        return $this->storageDir . '/session_' . $sanitized . '.json';
    }
}
