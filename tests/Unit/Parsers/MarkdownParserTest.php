<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Parsers;

use ClaudeAgents\Parsers\MarkdownParser;
use PHPUnit\Framework\TestCase;

class MarkdownParserTest extends TestCase
{
    private MarkdownParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MarkdownParser();
    }

    public function testExtractHeadings(): void
    {
        $text = "# Title\n## Subtitle\n### Section";

        $headings = $this->parser->extractHeadings($text);

        $this->assertCount(3, $headings);
        $this->assertEquals(1, $headings[0]['level']);
        $this->assertEquals('Title', $headings[0]['text']);
        $this->assertEquals(2, $headings[1]['level']);
    }

    public function testExtractCodeBlocks(): void
    {
        $text = "```php\necho 'hello';\n```\n\n```javascript\nconsole.log('hi');\n```";

        $blocks = $this->parser->extractCodeBlocks($text);

        $this->assertCount(2, $blocks);
        $this->assertEquals('php', $blocks[0]['language']);
        $this->assertStringContainsString('echo', $blocks[0]['code']);
        $this->assertEquals('javascript', $blocks[1]['language']);
    }

    public function testExtractInlineCode(): void
    {
        $text = 'Use `echo` to print or `var_dump` to debug.';

        $code = $this->parser->extractInlineCode($text);

        $this->assertCount(2, $code);
        $this->assertEquals('echo', $code[0]);
        $this->assertEquals('var_dump', $code[1]);
    }

    public function testExtractLists(): void
    {
        $text = "- Item 1\n- Item 2\n\n1. First\n2. Second";

        $lists = $this->parser->extractLists($text);

        $this->assertCount(2, $lists);
        $this->assertEquals('bullet', $lists[0]['type']);
        $this->assertCount(2, $lists[0]['items']);
        $this->assertEquals('numbered', $lists[1]['type']);
    }

    public function testExtractLinks(): void
    {
        $text = '[Google](https://google.com) and [GitHub](https://github.com)';

        $links = $this->parser->extractLinks($text);

        $this->assertCount(2, $links);
        $this->assertEquals('Google', $links[0]['text']);
        $this->assertEquals('https://google.com', $links[0]['url']);
    }

    public function testExtractImages(): void
    {
        $text = '![Logo](logo.png) and ![Icon](icon.svg)';

        $images = $this->parser->extractImages($text);

        $this->assertCount(2, $images);
        $this->assertEquals('Logo', $images[0]['alt']);
        $this->assertEquals('logo.png', $images[0]['url']);
    }

    public function testExtractTables(): void
    {
        $text = "| Name | Age |\n|------|-----|\n| John | 30 |\n| Jane | 25 |";

        $tables = $this->parser->extractTables($text);

        $this->assertCount(1, $tables);
        $this->assertEquals(['Name', 'Age'], $tables[0]['headers']);
        $this->assertCount(2, $tables[0]['rows']);
    }

    public function testExtractSection(): void
    {
        $text = "# Intro\nIntro text\n## Details\nDetails text\n## More\nMore text";

        $section = $this->parser->extractSection($text, 'Details', 2);

        $this->assertStringContainsString('Details text', $section);
        $this->assertStringNotContainsString('More text', $section);
    }

    public function testToPlainText(): void
    {
        $text = "# Title\n\n**Bold** and *italic* text with [link](url).";

        $plain = $this->parser->toPlainText($text);

        $this->assertStringNotContainsString('#', $plain);
        $this->assertStringNotContainsString('**', $plain);
        $this->assertStringContainsString('Bold', $plain);
    }

    public function testParse(): void
    {
        $text = "# Title\n\n```php\ncode\n```\n\n- List item";

        $result = $this->parser->parse($text);

        $this->assertArrayHasKey('headings', $result);
        $this->assertArrayHasKey('code_blocks', $result);
        $this->assertArrayHasKey('lists', $result);
    }

    public function testGetType(): void
    {
        $this->assertEquals('markdown', $this->parser->getType());
    }

    public function testGetFormatInstructions(): void
    {
        $instructions = $this->parser->getFormatInstructions();

        $this->assertStringContainsString('Markdown', $instructions);
    }
}
