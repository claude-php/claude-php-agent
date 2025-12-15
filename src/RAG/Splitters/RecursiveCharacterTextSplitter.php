<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Splitters;

use ClaudeAgents\Contracts\ChunkerInterface;

/**
 * Recursively splits text using multiple separators.
 *
 * Tries to split on paragraphs first, then sentences, then words,
 * maintaining natural language boundaries.
 */
class RecursiveCharacterTextSplitter implements ChunkerInterface
{
    /**
     * @param int $chunkSize Target size in characters per chunk
     * @param int $overlap Number of overlapping characters between chunks
     * @param array<string> $separators Separators in order of preference
     */
    public function __construct(
        private readonly int $chunkSize = 2000,
        private readonly int $overlap = 200,
        private readonly array $separators = ["\n\n", "\n", '. ', ' ', ''],
    ) {
    }

    public function chunk(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        return $this->splitText($text, $this->separators);
    }

    /**
     * Recursively split text using separators.
     *
     * @param array<string> $separators
     * @return array<string>
     */
    private function splitText(string $text, array $separators): array
    {
        if (strlen($text) <= $this->chunkSize) {
            return [trim($text)];
        }

        $separator = $separators[0] ?? '';
        $nextSeparators = array_slice($separators, 1);

        // Split by current separator
        $splits = $separator !== '' ? explode($separator, $text) : str_split($text, $this->chunkSize);

        $chunks = [];
        $currentChunk = '';

        foreach ($splits as $split) {
            $testChunk = $currentChunk === ''
                ? $split
                : $currentChunk . $separator . $split;

            if (strlen($testChunk) <= $this->chunkSize) {
                $currentChunk = $testChunk;
            } else {
                // Save current chunk if not empty
                if (! empty(trim($currentChunk))) {
                    $chunks[] = trim($currentChunk);
                }

                // If split itself is too large, recursively split it
                if (strlen($split) > $this->chunkSize && ! empty($nextSeparators)) {
                    $subChunks = $this->splitText($split, $nextSeparators);
                    foreach ($subChunks as $subChunk) {
                        $chunks[] = $subChunk;
                    }
                    $currentChunk = '';
                } else {
                    // Start new chunk with overlap from previous
                    if (! empty($chunks)) {
                        $lastChunk = $chunks[count($chunks) - 1];
                        $overlapText = $this->getOverlapText($lastChunk);
                        $currentChunk = $overlapText . ($overlapText !== '' ? $separator : '') . $split;
                    } else {
                        $currentChunk = $split;
                    }
                }
            }
        }

        // Add final chunk
        if (! empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Get overlap text from the end of a chunk.
     */
    private function getOverlapText(string $text): string
    {
        if ($this->overlap === 0 || strlen($text) <= $this->overlap) {
            return '';
        }

        return substr($text, -$this->overlap);
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
