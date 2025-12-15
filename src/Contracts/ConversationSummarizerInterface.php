<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\Conversation\Session;

/**
 * Interface for summarizing conversations.
 */
interface ConversationSummarizerInterface
{
    /**
     * Summarize a conversation session.
     *
     * @param Session $session The session to summarize
     * @param array $options Summarization options
     * @return string The summary text
     */
    public function summarize(Session $session, array $options = []): string;

    /**
     * Get key topics discussed in the conversation.
     *
     * @param Session $session The session to analyze
     * @param int $maxTopics Maximum number of topics to extract
     * @return array<string> Array of topics
     */
    public function extractTopics(Session $session, int $maxTopics = 5): array;

    /**
     * Get a brief summary of each turn.
     *
     * @param Session $session The session to analyze
     * @return array<array{turn_id: string, summary: string}> Array of turn summaries
     */
    public function summarizeTurns(Session $session): array;
}
