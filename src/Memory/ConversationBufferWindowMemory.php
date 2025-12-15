<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory;

/**
 * Conversation memory that keeps a sliding window of recent messages.
 *
 * Maintains only the last N messages, automatically discarding older ones.
 * Provides predictable memory usage and token counts.
 */
class ConversationBufferWindowMemory
{
    /**
     * @var array<array<string, mixed>>
     */
    private array $messages = [];

    private int $windowSize;
    private int $estimatedTokens = 0;

    /**
     * @param int $windowSize Number of messages to keep in the window
     */
    public function __construct(int $windowSize = 10)
    {
        if ($windowSize < 1) {
            throw new \InvalidArgumentException('Window size must be at least 1');
        }

        $this->windowSize = $windowSize;
    }

    /**
     * Add a message to the buffer.
     *
     * @param array<string, mixed> $message
     */
    public function add(array $message): void
    {
        $this->messages[] = $message;
        $this->estimatedTokens += $this->estimateTokens($message);

        // Trim to window size
        if (count($this->messages) > $this->windowSize) {
            $removed = array_shift($this->messages);
            $this->estimatedTokens -= $this->estimateTokens($removed);
        }
    }

    /**
     * Add multiple messages.
     *
     * @param array<array<string, mixed>> $messages
     */
    public function addMany(array $messages): void
    {
        foreach ($messages as $message) {
            $this->add($message);
        }
    }

    /**
     * Get all messages in the current window.
     *
     * @return array<array<string, mixed>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get context for the LLM (all messages in window).
     *
     * @return array<array<string, mixed>>
     */
    public function getContext(): array
    {
        return $this->messages;
    }

    /**
     * Clear all messages.
     */
    public function clear(): void
    {
        $this->messages = [];
        $this->estimatedTokens = 0;
    }

    /**
     * Get estimated token count.
     */
    public function getEstimatedTokens(): int
    {
        return $this->estimatedTokens;
    }

    /**
     * Get message count.
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Get the configured window size.
     */
    public function getWindowSize(): int
    {
        return $this->windowSize;
    }

    /**
     * Check if the window is full.
     */
    public function isFull(): bool
    {
        return count($this->messages) >= $this->windowSize;
    }

    /**
     * Get the oldest message in the window.
     *
     * @return array<string, mixed>|null
     */
    public function getOldest(): ?array
    {
        return $this->messages[0] ?? null;
    }

    /**
     * Get the newest message in the window.
     *
     * @return array<string, mixed>|null
     */
    public function getNewest(): ?array
    {
        $count = count($this->messages);

        return $count > 0 ? $this->messages[$count - 1] : null;
    }

    /**
     * Estimate token count for a message.
     * Rough estimation: ~4 characters = 1 token
     *
     * @param array<string, mixed> $message
     */
    private function estimateTokens(array $message): int
    {
        $content = $message['content'] ?? '';

        if (is_string($content)) {
            return (int) ceil(strlen($content) / 4);
        }

        if (is_array($content)) {
            $text = json_encode($content);

            return (int) ceil(strlen($text) / 4);
        }

        return 0;
    }
}
