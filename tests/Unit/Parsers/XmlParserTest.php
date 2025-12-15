<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Parsers;

use ClaudeAgents\Parsers\XmlParser;
use PHPUnit\Framework\TestCase;

class XmlParserTest extends TestCase
{
    private XmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new XmlParser();
    }

    public function testParseSimpleXml(): void
    {
        $xml = '<?xml version="1.0"?><root><item>Value</item></root>';

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('item', $result);
        $this->assertEquals('Value', $result['item']);
    }

    public function testParseXmlInCodeBlock(): void
    {
        $text = "Here's the XML:\n```xml\n<?xml version=\"1.0\"?>\n<data><name>John</name></data>\n```";

        $result = $this->parser->parse($text);

        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('John', $result['name']);
    }

    public function testParseHtml(): void
    {
        $html = '<div><p>Content here</p></div>';

        $parser = (new XmlParser())->asHtml();
        $result = $parser->parse($html);

        $this->assertIsArray($result);
    }

    public function testExtractTag(): void
    {
        $xml = '<root><item>First</item><item>Second</item></root>';

        $items = $this->parser->extractTag($xml, 'item');

        $this->assertCount(2, $items);
        $this->assertEquals('First', $items[0]);
        $this->assertEquals('Second', $items[1]);
    }

    public function testExtractText(): void
    {
        $html = '<div><p>Hello</p><p>World</p></div>';

        $parser = (new XmlParser())->asHtml();
        $text = $parser->extractText($html);

        $this->assertStringContainsString('Hello', $text);
        $this->assertStringContainsString('World', $text);
    }

    public function testInvalidXmlThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $parser = (new XmlParser())->showErrors();
        $parser->parse('<invalid><xml>');
    }

    public function testGetType(): void
    {
        $this->assertEquals('xml', $this->parser->getType());
        $this->assertEquals('html', (new XmlParser())->asHtml()->getType());
    }

    public function testGetFormatInstructions(): void
    {
        $instructions = $this->parser->getFormatInstructions();

        $this->assertStringContainsString('XML', $instructions);
    }
}
