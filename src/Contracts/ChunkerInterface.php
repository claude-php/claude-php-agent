<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for text chunking strategies.
 */
interface ChunkerInterface
{
    /**
     * Chunk text into smaller pieces.
     *
     * @param string $text The text to chunk
     * @return array<string> Array of text chunks
     */
    public function chunk(string $text): array;

    /**
     * Get the chunk size.
     */
    public function getChunkSize(): int;

    /**
     * Get the overlap size.
     */
    public function getOverlap(): int;
}
