<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\RAG;

use ClaudeAgents\RAG\Chunk;
use PHPUnit\Framework\TestCase;

class ChunkTest extends TestCase
{
    public function testConstruction(): void
    {
        $chunk = new Chunk('chunk-1', 'doc-1', 'Chunk text', 0, 10, ['source' => 'Test Doc']);

        $this->assertEquals('chunk-1', $chunk->getId());
        $this->assertEquals('doc-1', $chunk->getDocumentId());
        $this->assertEquals('Chunk text', $chunk->getText());
        $this->assertEquals(0, $chunk->getStartIndex());
        $this->assertEquals(10, $chunk->getEndIndex());
        $this->assertEquals(['source' => 'Test Doc'], $chunk->getMetadata());
    }

    public function testGetMetadataValue(): void
    {
        $chunk = new Chunk('chunk-1', 'doc-1', 'Text', 0, 4, ['category' => 'important']);

        $this->assertEquals('important', $chunk->getMetadataValue('category'));
        $this->assertNull($chunk->getMetadataValue('nonexistent'));
    }

    public function testGetLength(): void
    {
        $chunk = new Chunk('chunk-1', 'doc-1', 'Hello', 0, 5);

        $this->assertEquals(5, $chunk->getLength());
    }

    public function testGetWordCount(): void
    {
        $chunk = new Chunk('chunk-1', 'doc-1', 'One two three four', 0, 18);

        $this->assertEquals(4, $chunk->getWordCount());
    }

    public function testGetSource(): void
    {
        $chunk = new Chunk('chunk-1', 'doc-1', 'Text', 0, 4, ['source' => 'My Source']);

        $this->assertEquals('My Source', $chunk->getSource());
    }

    public function testGetSourceNull(): void
    {
        $chunk = new Chunk('chunk-1', 'doc-1', 'Text', 0, 4);

        $this->assertNull($chunk->getSource());
    }

    public function testToArray(): void
    {
        $chunk = new Chunk('chunk-1', 'doc-1', 'Text', 5, 9, ['tag' => 'test']);

        $array = $chunk->toArray();

        $this->assertEquals('chunk-1', $array['id']);
        $this->assertEquals('doc-1', $array['document_id']);
        $this->assertEquals('Text', $array['text']);
        $this->assertEquals(5, $array['start_index']);
        $this->assertEquals(9, $array['end_index']);
        $this->assertEquals(['tag' => 'test'], $array['metadata']);
        $this->assertArrayHasKey('length', $array);
        $this->assertArrayHasKey('word_count', $array);
    }
}
