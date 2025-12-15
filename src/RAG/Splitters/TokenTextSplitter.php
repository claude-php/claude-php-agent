<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Splitters;

use ClaudeAgents\Contracts\ChunkerInterface;

/**
 * Splits text by token count (approximate).
 *
 * Uses a simple approximation: 1 token ≈ 4 characters.
 * For production use with Claude, integrate with actual tokenizer.
 */
class TokenTextSplitter implements ChunkerInterface
{
    private const CHARS_PER_TOKEN = 4;

    /**
     * @param int $chunkSize Target size in tokens per chunk
     * @param int $overlap Number of overlapping tokens between chunks
     */
    public function __construct(
        private readonly int $chunkSize = 500,
        private readonly int $overlap = 50,
    ) {
    }

    public function chunk(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // Approximate tokens to characters
        $chunkChars = $this->chunkSize * self::CHARS_PER_TOKEN;
        $overlapChars = $this->overlap * self::CHARS_PER_TOKEN;

        // Split by sentences first to maintain boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($sentences === false) {
            return [$text];
        }

        $chunks = [];
        $currentChunk = '';
        $currentLength = 0;

        foreach ($sentences as $sentence) {
            $sentenceLength = strlen($sentence);

            if ($currentLength + $sentenceLength > $chunkChars && ! empty($currentChunk)) {
                // Save current chunk
                $chunks[] = trim($currentChunk);

                // Create overlap
                $words = explode(' ', $currentChunk);
                $overlapWordCount = max(1, (int) ($overlapChars / (strlen($currentChunk) / count($words))));
                $overlapWords = array_slice($words, -$overlapWordCount);

                $currentChunk = implode(' ', $overlapWords) . ' ' . $sentence;
                $currentLength = strlen($currentChunk);
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : ' ') . $sentence;
                $currentLength += $sentenceLength;
            }
        }

        // Add final chunk
        if (! empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getOverlap(): int
    {
        return $this->overlap;
    }

    /**
     * Get approximate token count for text.
     */
    public function countTokens(string $text): int
    {
        return (int) ceil(strlen($text) / self::CHARS_PER_TOKEN);
    }
}
