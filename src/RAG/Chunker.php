<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG;

use ClaudeAgents\Contracts\ChunkerInterface;

/**
 * Chunks text into overlapping pieces.
 *
 * Uses sentence-based chunking with configurable size and overlap.
 */
class Chunker implements ChunkerInterface
{
    /**
     * @param int $chunkSize Target size in words per chunk
     * @param int $overlap Number of overlapping words between chunks
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

        // Split by sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($sentences === false) {
            return [$text];
        }

        $chunks = [];
        $currentChunk = '';
        $wordCount = 0;

        foreach ($sentences as $sentence) {
            $sentenceWords = str_word_count($sentence);

            // Check if adding this sentence would exceed chunk size
            if ($wordCount + $sentenceWords > $this->chunkSize && ! empty($currentChunk)) {
                // Save the current chunk
                $chunks[] = trim($currentChunk);

                // Create overlap: take last N words of current chunk
                $words = explode(' ', $currentChunk);
                $overlapWords = array_slice($words, -$this->overlap);
                $currentChunk = implode(' ', $overlapWords) . ' ' . $sentence;
                $wordCount = count($overlapWords) + $sentenceWords;
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : ' ') . $sentence;
                $wordCount += $sentenceWords;
            }
        }

        // Add the last chunk if not empty
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
}
