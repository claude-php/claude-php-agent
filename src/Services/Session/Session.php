<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Session;

/**
 * Represents a user session.
 */
class Session
{
    /**
     * @param string $sessionId Unique session identifier
     * @param string $userId User identifier
     * @param array<string, mixed> $data Session data
     * @param int $createdAt Unix timestamp of creation
     * @param int $expiresAt Unix timestamp of expiration
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $userId,
        public array $data,
        public readonly int $createdAt,
        public int $expiresAt
    ) {
    }

    /**
     * Create a session from an array.
     *
     * @param array<string, mixed> $data Session data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sessionId: $data['session_id'] ?? '',
            userId: $data['user_id'] ?? '',
            data: $data['data'] ?? [],
            createdAt: $data['created_at'] ?? time(),
            expiresAt: $data['expires_at'] ?? time()
        );
    }

    /**
     * Convert the session to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'data' => $this->data,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    /**
     * Check if the session has expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return time() >= $this->expiresAt;
    }

    /**
     * Get the remaining lifetime in seconds.
     *
     * @return int
     */
    public function getRemainingLifetime(): int
    {
        return max(0, $this->expiresAt - time());
    }
}
