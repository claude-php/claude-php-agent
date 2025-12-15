<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory;

use ClaudeAgents\Memory\Summarizers\SummarizerInterface;

/**
 * Conversation memory that summarizes old messages.
 *
 * Keeps a running summary of the conversation, only preserving
 * recent messages in full. Reduces token usage for long conversations.
 */
class ConversationSummaryMemory
{
    /**
     * @var array<array<string, mixed>>
     */
    private array $recentMessages = [];

    private string $summary = '';
    private int $summaryThreshold;
    private int $keepRecentCount;

    /**
     * @param SummarizerInterface $summarizer Summarizer to use
     * @param int $summaryThreshold Number of messages before summarizing
     * @param int $keepRecentCount Number of recent messages to keep unsummarized
     */
    public function __construct(
        private readonly SummarizerInterface $summarizer,
        int $summaryThreshold = 10,
        int $keepRecentCount = 3
    ) {
        if ($summaryThreshold < 1) {
            throw new \InvalidArgumentException('Summary threshold must be at least 1');
        }

        if ($keepRecentCount < 0) {
            throw new \InvalidArgumentException('Keep recent count cannot be negative');
        }

        $this->summaryThreshold = $summaryThreshold;
        $this->keepRecentCount = $keepRecentCount;
    }

    /**
     * Add a message to memory.
     *
     * @param array<string, mixed> $message
     */
    public function add(array $message): void
    {
        $this->recentMessages[] = $message;

        // Check if we should summarize
        if (count($this->recentMessages) > $this->summaryThreshold) {
            $this->summarizeOldMessages();
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
    }

    /**
     * Get message count (recent only).
     */
    public function count(): int
    {
        return count($this->recentMessages);
    }

    /**
     * Check if there is a summary.
     */
    public function hasSummary(): bool
    {
        return ! empty($this->summary);
    }

    /**
     * Force summarization of current messages.
     */
    public function forceSummarize(): void
    {
        if (count($this->recentMessages) > 0) {
            $this->summarizeOldMessages();
        }
    }

    /**
     * Summarize old messages and keep only recent ones.
     */
    private function summarizeOldMessages(): void
    {
        $messagesToSummarize = array_slice(
            $this->recentMessages,
            0,
            -$this->keepRecentCount
        );

        if (empty($messagesToSummarize)) {
            return;
        }

        // Generate new summary
        $newSummary = $this->summarizer->summarize($messagesToSummarize, $this->summary);

        if ($newSummary) {
            $this->summary = $newSummary;

            // Keep only recent messages
            $this->recentMessages = array_slice(
                $this->recentMessages,
                -$this->keepRecentCount
            );
        }
    }
}
