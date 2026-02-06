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

        // Compact tool results first (preserve tool_use/tool_result pairing)
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

        // Add newer message units (tool_use + tool_result pairs) in reverse order until we fit
        $units = $this->buildMessageUnits($messages);
        $recentUnits = array_reverse($units);
        foreach ($recentUnits as $unit) {
            foreach (array_reverse($unit) as $message) {
                array_unshift($compacted, $message);
            }

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
                // Compact tool result blocks but keep structure to preserve pairing
                $message['content'] = array_map(function ($block) {
                    if (! is_array($block) || ($block['type'] ?? '') !== 'tool_result') {
                        return $block;
                    }

                    $toolUseId = $block['tool_use_id'] ?? null;
                    $isError = $block['is_error'] ?? false;
                    $replacement = [
                        'type' => 'tool_result',
                        'tool_use_id' => $toolUseId,
                        'content' => '[tool result truncated]',
                    ];

                    if ($isError) {
                        $replacement['is_error'] = true;
                    }

                    return $replacement;
                }, $message['content']);
            }

            return $message;
        }, $messages);
    }

    /**
     * Build message units that preserve tool_use/tool_result pairs.
     *
     * @param array<array<string, mixed>> $messages
     * @return array<array<int, array<string, mixed>>>
     */
    private function buildMessageUnits(array $messages): array
    {
        $units = [];
        $count = count($messages);
        $i = 0;

        while ($i < $count) {
            $message = $messages[$i];
            $next = $messages[$i + 1] ?? null;

            if ($this->isToolUseMessage($message) && is_array($next) && $this->isToolResultMessage($next)) {
                $units[] = [$message, $next];
                $i += 2;
                continue;
            }

            $units[] = [$message];
            $i++;
        }

        return $units;
    }

    /**
     * Check if a message contains any tool_use blocks.
     *
     * @param array<string, mixed> $message
     */
    private function isToolUseMessage(array $message): bool
    {
        if (($message['role'] ?? '') !== 'assistant' || ! is_array($message['content'] ?? null)) {
            return false;
        }

        foreach ($message['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'tool_use') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a message contains any tool_result blocks.
     *
     * @param array<string, mixed> $message
     */
    private function isToolResultMessage(array $message): bool
    {
        if (($message['role'] ?? '') !== 'user' || ! is_array($message['content'] ?? null)) {
            return false;
        }

        foreach ($message['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'tool_result') {
                return true;
            }
        }

        return false;
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
