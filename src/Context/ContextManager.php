<?php

declare(strict_types=1);

namespace ClaudeAgents\Context;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Manages context window to prevent exceeding token limits.
 */
class ContextManager
{
    private int $maxContextTokens;
    private float $compactThreshold; // Compact when threshold exceeded
    private bool $autoClear;
    private bool $clearToolResults;
    private LoggerInterface $logger;

    /**
     * @param int $maxContextTokens Maximum context window size
     * @param array<string, mixed> $options Configuration
     */
    public function __construct(
        int $maxContextTokens = 100000,
        array $options = [],
    ) {
        $this->maxContextTokens = $maxContextTokens;
        $this->compactThreshold = $options['compact_threshold'] ?? 0.8;
        $this->autoClear = $options['auto_compact'] ?? true;
        $this->clearToolResults = $options['clear_tool_results'] ?? true;
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Check if messages fit within context window.
     *
     * @param array<array<string, mixed>> $messages Messages to check
     * @param array<array<string, mixed>> $tools Tools to include in count
     * @return bool True if fits within window
     */
    public function fitsInContext(array $messages, array $tools = []): bool
    {
        $estimatedTokens = TokenCounter::estimateTotal($messages, $tools);

        return $estimatedTokens <= $this->maxContextTokens;
    }

    /**
     * Get estimated context usage percentage.
     *
     * @param array<array<string, mixed>> $messages
     * @param array<array<string, mixed>> $tools
     * @return float Percentage from 0.0 to 1.0
     */
    public function getUsagePercentage(array $messages, array $tools = []): float
    {
        $estimatedTokens = TokenCounter::estimateTotal($messages, $tools);

        return $estimatedTokens / $this->maxContextTokens;
    }

    /**
     * Compact messages to fit within context window.
     *
     * @param array<array<string, mixed>> $messages Messages to compact
     * @param array<array<string, mixed>> $tools Tools in use
     * @return array<array<string, mixed>> Compacted messages
     */
    public function compactMessages(array $messages, array $tools = []): array
    {
        if ($this->fitsInContext($messages, $tools)) {
            return $messages;
        }

        $this->logger->info('Compacting context messages');

        // Clear tool results first
        if ($this->clearToolResults) {
            $messages = $this->clearToolResults($messages);
        }

        // If still too large, remove oldest messages (keep first system message)
        $compacted = [];
        $systemMessage = null;

        // Preserve system message if present
        if (! empty($messages) && ($messages[0]['role'] ?? '') === 'system') {
            $systemMessage = $messages[0];
            $compacted[] = $systemMessage;
            $messages = array_slice($messages, 1);
        }

        // Add newer messages in reverse order until we fit
        $recentMessages = array_reverse($messages);
        foreach ($recentMessages as $message) {
            array_unshift($compacted, $message);

            if ($this->fitsInContext($compacted, $tools)) {
                $this->logger->debug('Compacted to ' . count($compacted) . ' messages');

                return $compacted;
            }
        }

        return $compacted;
    }

    /**
     * Clear tool result messages.
     *
     * @param array<array<string, mixed>> $messages
     * @return array<array<string, mixed>>
     */
    private function clearToolResults(array $messages): array
    {
        return array_map(function ($message) {
            if (($message['role'] ?? '') === 'user' && is_array($message['content'] ?? null)) {
                // Clear tool result blocks
                $message['content'] = array_filter(
                    $message['content'],
                    fn ($block) => ! is_array($block) || ($block['type'] ?? '') !== 'tool_result'
                );
            }

            return $message;
        }, $messages);
    }

    /**
     * Get the maximum context tokens.
     */
    public function getMaxContextTokens(): int
    {
        return $this->maxContextTokens;
    }

    /**
     * Set maximum context tokens.
     */
    public function setMaxContextTokens(int $maxTokens): void
    {
        $this->maxContextTokens = $maxTokens;
    }

    /**
     * Get compact threshold.
     */
    public function getCompactThreshold(): float
    {
        return $this->compactThreshold;
    }
}
