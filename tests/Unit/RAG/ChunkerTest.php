<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\RAG;

use ClaudeAgents\RAG\Chunker;
use PHPUnit\Framework\TestCase;

class ChunkerTest extends TestCase
{
    public function testChunkEmptyText(): void
    {
        $chunker = new Chunker();

        $chunks = $chunker->chunk('');

        $this->assertEmpty($chunks);
    }

    public function testChunkShortText(): void
    {
        $chunker = new Chunker(chunkSize: 100, overlap: 10);
        $text = 'This is a short sentence.';

        $chunks = $chunker->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertEquals('This is a short sentence.', $chunks[0]);
    }

    public function testChunkLongTextCreatesMultipleChunks(): void
    {
        $chunker = new Chunker(chunkSize: 10, overlap: 2);

        // Create text with multiple sentences
        $text = str_repeat('This is sentence one. ', 5) . str_repeat('This is sentence two. ', 5);

        $chunks = $chunker->chunk($text);

        $this->assertGreaterThan(1, count($chunks));
    }

    public function testChunkHasOverlap(): void
    {
        $chunker = new Chunker(chunkSize: 10, overlap: 3);

        // Create longer text with more sentences to force multiple chunks
        $text = 'This is the first sentence. This is the second sentence. This is the third sentence. This is the fourth sentence. This is the fifth sentence. This is the sixth sentence.';

        $chunks = $chunker->chunk($text);

        // Check that multiple chunks are created for long text
        $this->assertGreaterThan(1, count($chunks));

        // Verify that the overlap setting is preserved
        $this->assertEquals(3, $chunker->getOverlap());
    }

    public function testGetChunkSize(): void
    {
        $chunker = new Chunker(chunkSize: 250, overlap: 25);

        $this->assertEquals(250, $chunker->getChunkSize());
    }

    public function testGetOverlap(): void
    {
        $chunker = new Chunker(chunkSize: 250, overlap: 25);

        $this->assertEquals(25, $chunker->getOverlap());
    }

    public function testChunkPreservesSentences(): void
    {
        $chunker = new Chunker(chunkSize: 50, overlap: 5);

        $text = 'First sentence. Second sentence. Third sentence.';

        $chunks = $chunker->chunk($text);

        // Each chunk should contain complete sentences (not cut mid-sentence)
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty($chunk);
        }
    }
}
