<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\RAG;

use ClaudeAgents\RAG\Document;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    public function testConstruction(): void
    {
        $doc = new Document('doc-1', 'Test Title', 'Test content', ['author' => 'John']);

        $this->assertEquals('doc-1', $doc->getId());
        $this->assertEquals('Test Title', $doc->getTitle());
        $this->assertEquals('Test content', $doc->getContent());
        $this->assertEquals(['author' => 'John'], $doc->getMetadata());
    }

    public function testGetMetadataValue(): void
    {
        $doc = new Document('doc-1', 'Title', 'Content', ['key' => 'value']);

        $this->assertEquals('value', $doc->getMetadataValue('key'));
        $this->assertEquals('default', $doc->getMetadataValue('nonexistent', 'default'));
    }

    public function testGetLength(): void
    {
        $doc = new Document('doc-1', 'Title', 'Hello World');

        $this->assertEquals(11, $doc->getLength());
    }

    public function testGetWordCount(): void
    {
        $doc = new Document('doc-1', 'Title', 'This is a test document with seven words');

        $this->assertEquals(8, $doc->getWordCount());
    }

    public function testToArray(): void
    {
        $doc = new Document('doc-1', 'Title', 'Content', ['author' => 'Jane']);

        $array = $doc->toArray();

        $this->assertEquals('doc-1', $array['id']);
        $this->assertEquals('Title', $array['title']);
        $this->assertEquals('Content', $array['content']);
        $this->assertEquals(['author' => 'Jane'], $array['metadata']);
        $this->assertArrayHasKey('length', $array);
        $this->assertArrayHasKey('word_count', $array);
    }
}
