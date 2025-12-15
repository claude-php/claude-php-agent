<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory;

use ClaudeAgents\Memory\Summarizers\SummarizerInterface;

/**
 * Hybrid conversation memory combining summary and buffer window.
 *
 * Summarizes old messages while keeping a buffer window of recent ones.
 * Provides the best balance between context preservation and token efficiency.
 */
class ConversationSummaryBufferMemory
{
    /**
     * @var array<array<string, mixed>>
     */
    private array $recentMessages = [];

    private string $summary = '';
    private int $maxTokens;
    private int $summaryTokens = 0;
    private int $bufferTokens = 0;

    /**
     * @param SummarizerInterface $summarizer Summarizer to use
     * @param int $maxTokens Maximum total tokens (summary + buffer)
     */
    public function __construct(
        private readonly SummarizerInterface $summarizer,
        int $maxTokens = 2000
    ) {
        if ($maxTokens < 100) {
            throw new \InvalidArgumentException('Max tokens must be at least 100');
        }

        $this->maxTokens = $maxTokens;
    }

    /**
     * Add a message to memory.
     *
     * @param array<string, mixed> $message
     */
    public function add(array $message): void
    {
        $this->recentMessages[] = $message;
        $this->bufferTokens += $this->estimateTokens($message);

        // Check if we exceed token limit
        while ($this->getTotalTokens() > $this->maxTokens && count($this->recentMessages) > 1) {
            $this->summarizeOldestMessage();
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
     * Get context for the LLM (summary + recent messages).
     *
     * @return array<array<string, mixed>>
     */
    public function getContext(): array
    {
        $context = [];

        // Add summary as a system-like message if it exists
        if ($this->summary) {
            $context[] = [
                'role' => 'user',
                'content' => "Previous conversation summary:\n{$this->summary}",
            ];
        }

        // Add recent messages
        foreach ($this->recentMessages as $message) {
            $context[] = $message;
        }

        return $context;
    }

    /**
     * Get all recent messages (not summarized yet).
     *
     * @return array<array<string, mixed>>
     */
    public function getRecentMessages(): array
    {
        return $this->recentMessages;
    }

    /**
     * Get the current summary.
     */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * Clear all memory.
     */
    public function clear(): void
    {
        $this->recentMessages = [];
        $this->summary = '';
        $this->summaryTokens = 0;
        $this->bufferTokens = 0;
    }

    /**
     * Get message count (recent only).
     */
    public function count(): int
    {
        return count($this->recentMessages);
    }

    /**
     * Get total estimated tokens (summary + buffer).
     */
    public function getTotalTokens(): int
    {
        return $this->summaryTokens + $this->bufferTokens;
    }

    /**
     * Get estimated buffer tokens.
     */
    public function getBufferTokens(): int
    {
        return $this->bufferTokens;
    }

    /**
     * Get estimated summary tokens.
     */
    public function getSummaryTokens(): int
    {
        return $this->summaryTokens;
    }

    /**
     * Check if there is a summary.
     */
    public function hasSummary(): bool
    {
        return ! empty($this->summary);
    }

    /**
     * Get the maximum token limit.
     */
    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    /**
     * Check if near token limit.
     */
    public function isNearLimit(float $threshold = 0.9): bool
    {
        return $this->getTotalTokens() >= ($this->maxTokens * $threshold);
    }

    /**
     * Summarize the oldest message and remove it from buffer.
     */
    private function summarizeOldestMessage(): void
    {
        if (empty($this->recentMessages)) {
            return;
        }

        // Take the oldest message
        $oldest = array_shift($this->recentMessages);
        $this->bufferTokens -= $this->estimateTokens($oldest);

        // Summarize it
        $newSummary = $this->summarizer->summarize([$oldest], $this->summary);

        if ($newSummary) {
            $this->summary = $newSummary;
            $this->summaryTokens = $this->estimateTokens(['content' => $this->summary]);
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
}
