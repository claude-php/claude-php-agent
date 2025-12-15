<?php

declare(strict_types=1);

namespace ClaudeAgents\Context;

/**
 * Edits and manipulates message context.
 */
class ContextEditor
{
    /**
     * Clear all tool result blocks from messages.
     *
     * @param array<array<string, mixed>> $messages
     * @return array<array<string, mixed>>
     */
    public static function clearToolResults(array $messages): array
    {
        return array_map(function ($message) {
            if (($message['role'] ?? '') === 'user' && is_array($message['content'] ?? null)) {
                $message['content'] = array_filter(
                    $message['content'],
                    fn ($block) => ! is_array($block) || ($block['type'] ?? '') !== 'tool_result'
                );
            }

            return $message;
        }, $messages);
    }

    /**
     * Remove messages of a specific role.
     *
     * @param array<array<string, mixed>> $messages
     * @param string $role The role to remove (user, assistant, system)
     * @return array<array<string, mixed>>
     */
    public static function removeByRole(array $messages, string $role): array
    {
        return array_filter(
            $messages,
            fn ($m) => ($m['role'] ?? '') !== $role
        );
    }

    /**
     * Keep only the last N messages plus the first (system) message.
     *
     * @param array<array<string, mixed>> $messages
     * @param int $keepCount Number of recent messages to keep
     * @return array<array<string, mixed>>
     */
    public static function keepRecent(array $messages, int $keepCount = 10): array
    {
        if (count($messages) <= $keepCount) {
            return $messages;
        }

        // Keep first message (often system prompt)
        $first = isset($messages[0]) ? [$messages[0]] : [];

        // Add recent messages
        $recent = array_slice($messages, -$keepCount);

        return array_merge($first, $recent);
    }

    /**
     * Summarize early messages to preserve context.
     *
     * @param array<array<string, mixed>> $messages
     * @param int $keepCount Number of messages to keep unsummarized
     * @return array<array<string, mixed>> Messages with summary note
     */
    public static function summarizeEarly(array $messages, int $keepCount = 5): array
    {
        if (count($messages) <= $keepCount + 2) {
            return $messages;
        }

        // Keep system message and recent messages
        $systemMessages = array_filter($messages, fn ($m) => ($m['role'] ?? '') === 'system');
        $recentMessages = array_slice($messages, -$keepCount);

        // Create summary note
        $summary = [
            'role' => 'system',
            'content' => '[Previous conversation context summarized. ' . (count($messages) - $keepCount) . ' messages compacted.]',
        ];

        return array_merge($systemMessages, [$summary], $recentMessages);
    }

    /**
     * Extract only text content from messages.
     *
     * @param array<array<string, mixed>> $messages
     * @return array<array<string, mixed>>
     */
    public static function extractTextOnly(array $messages): array
    {
        return array_map(function ($message) {
            if (is_array($message['content'] ?? null)) {
                // Extract text blocks
                $texts = [];
                foreach ($message['content'] as $block) {
                    if (is_array($block) && ($block['type'] ?? '') === 'text') {
                        $texts[] = $block['text'] ?? '';
                    }
                }
                if (! empty($texts)) {
                    $message['content'] = implode("\n", $texts);
                }
            }

            return $message;
        }, $messages);
    }

    /**
     * Get conversation statistics.
     *
     * @param array<array<string, mixed>> $messages
     * @return array<string, int>
     */
    public static function getStats(array $messages): array
    {
        $stats = [
            'total_messages' => count($messages),
            'user_messages' => 0,
            'assistant_messages' => 0,
            'system_messages' => 0,
            'total_estimated_tokens' => 0,
        ];

        foreach ($messages as $message) {
            $role = $message['role'] ?? '';
            match ($role) {
                'user' => $stats['user_messages']++,
                'assistant' => $stats['assistant_messages']++,
                'system' => $stats['system_messages']++,
                default => null,
            };

            $stats['total_estimated_tokens'] += TokenCounter::estimateMessageTokens($message);
        }

        return $stats;
    }
}
