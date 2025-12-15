<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Parsers;

use ClaudeAgents\Parsers\ListParser;
use PHPUnit\Framework\TestCase;

class ListParserTest extends TestCase
{
    private ListParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ListParser();
    }

    public function testParseBulletList(): void
    {
        $text = "- Item 1\n- Item 2\n- Item 3";

        $result = $this->parser->parse($text);

        $this->assertCount(3, $result);
        $this->assertEquals('Item 1', $result[0]);
        $this->assertEquals('Item 2', $result[1]);
        $this->assertEquals('Item 3', $result[2]);
    }

    public function testParseAsteriskList(): void
    {
        $text = "* First\n* Second\n* Third";

        $result = $this->parser->parse($text);

        $this->assertCount(3, $result);
        $this->assertEquals('First', $result[0]);
    }

    public function testParseNumberedList(): void
    {
        $text = "1. Apple\n2. Banana\n3. Cherry";

        $result = $this->parser->parseNumbered($text);

        $this->assertCount(3, $result);
        $this->assertEquals('Apple', $result[0]);
        $this->assertEquals('Banana', $result[1]);
        $this->assertEquals('Cherry', $result[2]);
    }

    public function testParseNumberedListWithParenthesis(): void
    {
        $text = "1) First item\n2) Second item";

        $result = $this->parser->parseNumbered($text);

        $this->assertCount(2, $result);
        $this->assertEquals('First item', $result[0]);
    }

    public function testParseBulletsWithExtraSpacing(): void
    {
        $text = "- Item 1\n\n- Item 2\n\n- Item 3";

        $result = $this->parser->parse($text);

        $this->assertCount(3, $result);
    }

    public function testParseMixedContent(): void
    {
        $text = "Here are the items:\n- Item 1\n- Item 2\nEnd of list";

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Item 1', $result[0]);
        $this->assertEquals('Item 2', $result[1]);
    }

    public function testParseBulletsMethod(): void
    {
        $text = "- First bullet\n- Second bullet\n- Third bullet";

        $result = $this->parser->parseBullets($text);

        $this->assertCount(3, $result);
        $this->assertEquals('First bullet', $result[0]);
    }

    public function testParseFallbackToLines(): void
    {
        $text = "Line 1\nLine 2\nLine 3";

        $result = $this->parser->parse($text);

        $this->assertGreaterThan(0, count($result));
    }

    public function testParseEmptyTextReturnsEmptyArray(): void
    {
        $result = $this->parser->parse('');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testParseTrimWhitespace(): void
    {
        $text = "-   Item with spaces   \n-  Another item  ";

        $result = $this->parser->parse($text);

        $this->assertEquals('Item with spaces', $result[0]);
        $this->assertEquals('Another item', $result[1]);
    }
}
