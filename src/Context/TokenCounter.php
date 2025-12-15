<?php

declare(strict_types=1);

namespace ClaudeAgents\Context;

/**
 * Estimates token counts for messages and text.
 */
class TokenCounter
{
    // Approximate tokens per character (Claude uses roughly 4 chars per token on average)
    private const CHARS_PER_TOKEN = 4;

    /**
     * Estimate tokens for a string of text.
     *
     * @param string $text The text to estimate
     * @return int Estimated token count
     */
    public static function estimateTokens(string $text): int
    {
        // Rough estimation: ~4 characters per token
        $estimatedTokens = ceil(strlen($text) / self::CHARS_PER_TOKEN);

        // Add overhead for special tokens and formatting
        return max(1, (int) $estimatedTokens + 2);
    }

    /**
     * Estimate tokens for a message.
     *
     * @param array<string, mixed> $message A message with role and content
     * @return int Estimated token count
     */
    public static function estimateMessageTokens(array $message): int
    {
        $tokens = 4; // Base tokens for message structure

        // Role token
        if (isset($message['role'])) {
            $tokens += 1;
        }

        // Content tokens
        if (isset($message['content'])) {
            $content = $message['content'];
            if (is_string($content)) {
                $tokens += self::estimateTokens($content);
            } elseif (is_array($content)) {
                foreach ($content as $block) {
                    if (is_array($block) && isset($block['text'])) {
                        $tokens += self::estimateTokens($block['text']);
                    }
                }
            }
        }

        return $tokens;
    }

    /**
     * Estimate total tokens for a conversation.
     *
     * @param array<array<string, mixed>> $messages Array of messages
     * @return int Total estimated tokens
     */
    public static function estimateConversationTokens(array $messages): int
    {
        $totalTokens = 0;

        foreach ($messages as $message) {
            $totalTokens += self::estimateMessageTokens($message);
        }

        return $totalTokens;
    }

    /**
     * Estimate tokens for a tool definition.
     *
     * @param array<string, mixed> $tool Tool definition
     * @return int Estimated token count
     */
    public static function estimateToolTokens(array $tool): int
    {
        $tokens = 10; // Base tokens for tool wrapper

        if (isset($tool['description'])) {
            $tokens += self::estimateTokens($tool['description']);
        }

        if (isset($tool['input_schema'])) {
            $tokens += self::estimateTokens(json_encode($tool['input_schema']));
        }

        return $tokens;
    }

    /**
     * Estimate total tokens including tools.
     *
     * @param array<array<string, mixed>> $messages
     * @param array<array<string, mixed>> $tools
     * @return int Total estimated tokens
     */
    public static function estimateTotal(array $messages, array $tools = []): int
    {
        $totalTokens = self::estimateConversationTokens($messages);

        foreach ($tools as $tool) {
            $totalTokens += self::estimateToolTokens($tool);
        }

        return $totalTokens;
    }
}
