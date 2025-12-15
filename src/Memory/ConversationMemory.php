<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory;

/**
 * Manages conversation history with token-aware truncation.
 *
 * Helps manage context window limits by keeping the most relevant
 * messages while staying within token budgets.
 */
class ConversationMemory
{
    /**
     * @var array<array<string, mixed>>
     */
    private array $messages = [];

    private int $maxMessages;
    private int $estimatedTokens = 0;

    /**
     * @param int $maxMessages Maximum number of message pairs to keep
     */
    public function __construct(int $maxMessages = 20)
    {
        $this->maxMessages = $maxMessages;
    }

    /**
     * Add a message to the conversation.
     *
     * @param array<string, mixed> $message
     */
    public function add(array $message): void
    {
        $this->messages[] = $message;
        $this->estimatedTokens += $this->estimateTokens($message);
        $this->trim();
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
     * Get all messages.
     *
     * @return array<array<string, mixed>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the last N messages.
     *
     * @return array<array<string, mixed>>
     */
    public function getLastMessages(int $count): array
    {
        return array_slice($this->messages, -$count);
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
     * Trim messages to stay within limits.
     */
    private function trim(): void
    {
        if (count($this->messages) <= $this->maxMessages * 2) {
            return;
        }

        // Keep message pairs (user + assistant)
        $pairs = [];
        $currentPair = [];

        foreach ($this->messages as $message) {
            $role = $message['role'] ?? '';

            if ($role === 'user') {
                if (! empty($currentPair)) {
                    $pairs[] = $currentPair;
                }
                $currentPair = [$message];
            } elseif ($role === 'assistant' && ! empty($currentPair)) {
                $currentPair[] = $message;
                $pairs[] = $currentPair;
                $currentPair = [];
            }
        }

        // Keep the last N pairs
        $keepPairs = array_slice($pairs, -$this->maxMessages);

        // Flatten back to messages
        $this->messages = [];
        $this->estimatedTokens = 0;

        foreach ($keepPairs as $pair) {
            foreach ($pair as $msg) {
                $this->messages[] = $msg;
                $this->estimatedTokens += $this->estimateTokens($msg);
            }
        }
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

    /**
     * Create a summary of the conversation for context.
     */
    public function summarize(): string
    {
        $summary = [];

        foreach ($this->messages as $message) {
            $role = $message['role'] ?? 'unknown';
            $content = $message['content'] ?? '';

            if (is_string($content)) {
                $preview = substr($content, 0, 100);
                if (strlen($content) > 100) {
                    $preview .= '...';
                }
                $summary[] = "[{$role}]: {$preview}";
            } elseif (is_array($content)) {
                $types = array_map(fn ($b) => $b['type'] ?? 'unknown', $content);
                $summary[] = "[{$role}]: " . implode(', ', $types);
            }
        }

        return implode("\n", $summary);
    }
}
