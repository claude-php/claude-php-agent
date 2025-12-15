<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory\Summarizers;

/**
 * Interface for conversation summarizers.
 *
 * Summarizers condense conversation history while preserving key information.
 */
interface SummarizerInterface
{
    /**
     * Summarize messages, optionally incorporating an existing summary.
     *
     * @param array<array<string, mixed>> $messages Messages to summarize
     * @param string $existingSummary Optional existing summary to build upon
     * @return string The generated summary
     */
    public function summarize(array $messages, string $existingSummary = ''): string;

    /**
     * Summarize messages without context.
     *
     * @param array<array<string, mixed>> $messages Messages to summarize
     * @return string The generated summary
     */
    public function summarizeMessages(array $messages): string;

    /**
     * Get the maximum length of summaries produced.
     *
     * @return int Maximum summary length in tokens (estimate)
     */
    public function getMaxLength(): int;
}
