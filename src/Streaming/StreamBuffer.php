<?php

declare(strict_types=1);

namespace ClaudeAgents\Streaming;

/**
 * Accumulates streamed content into coherent blocks.
 */
class StreamBuffer
{
    private string $textBuffer = '';
    private string $currentBlock = '';
    private int $blockCount = 0;

    /**
     * @var array<array<string, mixed>>
     */
    private array $blocks = [];

    private int $totalChunks = 0;
    private float $startTime = 0.0;
    private float $lastChunkTime = 0.0;
    private int $totalBytes = 0;

    public function addText(string $text): void
    {
        if ($this->startTime === 0.0) {
            $this->startTime = microtime(true);
        }

        $this->textBuffer .= $text;
        $this->currentBlock .= $text;
        $this->totalChunks++;
        $this->totalBytes += strlen($text);
        $this->lastChunkTime = microtime(true);
    }

    public function finishBlock(): void
    {
        if (! empty($this->currentBlock)) {
            $this->blocks[] = [
                'type' => 'text',
                'text' => $this->currentBlock,
            ];
            $this->currentBlock = '';
            $this->blockCount++;
        }
    }

    /**
     * Add a complete block (e.g., tool use).
     *
     * @param array<string, mixed> $block
     */
    public function addBlock(array $block): void
    {
        $this->finishBlock(); // Finish any pending text
        $this->blocks[] = $block;
        $this->blockCount++;
    }

    public function getText(): string
    {
        return $this->textBuffer;
    }

    /**
     * Get all accumulated blocks.
     *
     * @return array<array<string, mixed>>
     */
    public function getBlocks(): array
    {
        // Include current block if not empty
        $blocks = $this->blocks;
        if (! empty($this->currentBlock)) {
            $blocks[] = [
                'type' => 'text',
                'text' => $this->currentBlock,
            ];
        }

        return $blocks;
    }

    public function getBlockCount(): int
    {
        return $this->blockCount + (! empty($this->currentBlock) ? 1 : 0);
    }

    /**
     * Clear the buffer.
     */
    public function clear(): void
    {
        $this->textBuffer = '';
        $this->currentBlock = '';
        $this->blocks = [];
        $this->blockCount = 0;
        $this->totalChunks = 0;
        $this->startTime = 0.0;
        $this->lastChunkTime = 0.0;
        $this->totalBytes = 0;
    }

    /**
     * Check if buffer has content.
     */
    public function isEmpty(): bool
    {
        return empty($this->textBuffer) && empty($this->currentBlock);
    }

    /**
     * Get streaming statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $duration = $this->startTime > 0 ? (microtime(true) - $this->startTime) : 0.0;
        $bytesPerSecond = $duration > 0 ? ($this->totalBytes / $duration) : 0.0;
        $chunksPerSecond = $duration > 0 ? ($this->totalChunks / $duration) : 0.0;

        return [
            'total_chunks' => $this->totalChunks,
            'total_bytes' => $this->totalBytes,
            'duration_seconds' => round($duration, 3),
            'bytes_per_second' => round($bytesPerSecond, 2),
            'chunks_per_second' => round($chunksPerSecond, 2),
            'average_chunk_size' => $this->totalChunks > 0 ? round($this->totalBytes / $this->totalChunks, 2) : 0,
        ];
    }
}
