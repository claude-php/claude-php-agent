<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Streaming;

use ClaudeAgents\Streaming\StreamBuffer;
use PHPUnit\Framework\TestCase;

class StreamBufferTest extends TestCase
{
    private StreamBuffer $buffer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buffer = new StreamBuffer();
    }

    public function testAddText(): void
    {
        $this->buffer->addText('Hello');
        $this->buffer->addText(' World');

        $this->assertEquals('Hello World', $this->buffer->getText());
    }

    public function testFinishBlock(): void
    {
        $this->buffer->addText('Block 1');
        $this->buffer->finishBlock();
        $this->buffer->addText('Block 2');
        $this->buffer->finishBlock();

        $blocks = $this->buffer->getBlocks();

        $this->assertCount(2, $blocks);
        $this->assertEquals('text', $blocks[0]['type']);
        $this->assertEquals('Block 1', $blocks[0]['text']);
        $this->assertEquals('Block 2', $blocks[1]['text']);
    }

    public function testFinishBlockIgnoresEmpty(): void
    {
        $this->buffer->finishBlock();

        $this->assertEmpty($this->buffer->getBlocks());
    }

    public function testAddBlock(): void
    {
        $this->buffer->addText('Text block');
        $this->buffer->addBlock([
            'type' => 'tool_use',
            'name' => 'calculator',
        ]);

        $blocks = $this->buffer->getBlocks();

        $this->assertCount(2, $blocks);
        $this->assertEquals('text', $blocks[0]['type']);
        $this->assertEquals('tool_use', $blocks[1]['type']);
    }

    public function testAddBlockFinishesPendingText(): void
    {
        $this->buffer->addText('Pending text');
        $this->buffer->addBlock(['type' => 'custom']);

        $blocks = $this->buffer->getBlocks();

        $this->assertCount(2, $blocks);
        $this->assertEquals('Pending text', $blocks[0]['text']);
    }

    public function testGetBlocksIncludesCurrentBlock(): void
    {
        $this->buffer->addText('Current');

        $blocks = $this->buffer->getBlocks();

        $this->assertCount(1, $blocks);
        $this->assertEquals('Current', $blocks[0]['text']);
    }

    public function testGetBlockCount(): void
    {
        $this->assertEquals(0, $this->buffer->getBlockCount());

        $this->buffer->addText('Block 1');
        $this->buffer->finishBlock();

        $this->assertEquals(1, $this->buffer->getBlockCount());

        $this->buffer->addText('Block 2');

        $this->assertEquals(2, $this->buffer->getBlockCount());
    }

    public function testClear(): void
    {
        $this->buffer->addText('Some text');
        $this->buffer->finishBlock();
        $this->buffer->addBlock(['type' => 'custom']);

        $this->buffer->clear();

        $this->assertEquals('', $this->buffer->getText());
        $this->assertEmpty($this->buffer->getBlocks());
        $this->assertEquals(0, $this->buffer->getBlockCount());
        $this->assertTrue($this->buffer->isEmpty());
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->buffer->isEmpty());

        $this->buffer->addText('Not empty');

        $this->assertFalse($this->buffer->isEmpty());

        $this->buffer->clear();

        $this->assertTrue($this->buffer->isEmpty());
    }

    public function testMultipleBlockAccumulation(): void
    {
        $this->buffer->addText('Text 1');
        $this->buffer->finishBlock();

        $this->buffer->addBlock(['type' => 'tool', 'name' => 'tool1']);

        $this->buffer->addText('Text 2');
        $this->buffer->finishBlock();

        $this->buffer->addBlock(['type' => 'tool', 'name' => 'tool2']);

        $blocks = $this->buffer->getBlocks();

        $this->assertCount(4, $blocks);
        $this->assertEquals('text', $blocks[0]['type']);
        $this->assertEquals('tool', $blocks[1]['type']);
        $this->assertEquals('text', $blocks[2]['type']);
        $this->assertEquals('tool', $blocks[3]['type']);
    }

    public function testGetStatistics(): void
    {
        $this->buffer->addText('Hello');
        usleep(1000); // Sleep 1ms to ensure measurable time passes
        $this->buffer->addText(' World');

        $stats = $this->buffer->getStatistics();

        $this->assertEquals(2, $stats['total_chunks']);
        $this->assertEquals(11, $stats['total_bytes']); // "Hello" + " World"
        $this->assertGreaterThanOrEqual(0, $stats['duration_seconds']);
        $this->assertGreaterThanOrEqual(0, $stats['bytes_per_second']);
        $this->assertGreaterThanOrEqual(0, $stats['chunks_per_second']);
        $this->assertEquals(5.5, $stats['average_chunk_size']);
    }

    public function testStatisticsEmptyBuffer(): void
    {
        $stats = $this->buffer->getStatistics();

        $this->assertEquals(0, $stats['total_chunks']);
        $this->assertEquals(0, $stats['total_bytes']);
        $this->assertEquals(0, $stats['duration_seconds']);
        $this->assertEquals(0, $stats['bytes_per_second']);
        $this->assertEquals(0, $stats['chunks_per_second']);
        $this->assertEquals(0, $stats['average_chunk_size']);
    }

    public function testStatisticsAfterClear(): void
    {
        $this->buffer->addText('Test');
        $this->buffer->clear();

        $stats = $this->buffer->getStatistics();

        $this->assertEquals(0, $stats['total_chunks']);
        $this->assertEquals(0, $stats['total_bytes']);
    }
}
