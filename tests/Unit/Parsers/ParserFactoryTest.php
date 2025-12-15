<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Parsers;

use ClaudeAgents\Parsers\CsvParser;
use ClaudeAgents\Parsers\JsonParser;
use ClaudeAgents\Parsers\ListParser;
use ClaudeAgents\Parsers\MarkdownParser;
use ClaudeAgents\Parsers\ParserFactory;
use ClaudeAgents\Parsers\RegexParser;
use ClaudeAgents\Parsers\XmlParser;
use PHPUnit\Framework\TestCase;

class ParserFactoryTest extends TestCase
{
    private ParserFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = ParserFactory::create();
    }

    public function testGetJsonParser(): void
    {
        $parser = $this->factory->get('json');

        $this->assertInstanceOf(JsonParser::class, $parser);
    }

    public function testGetListParser(): void
    {
        $parser = $this->factory->get('list');

        $this->assertInstanceOf(ListParser::class, $parser);
    }

    public function testGetRegexParser(): void
    {
        $parser = $this->factory->get('regex');

        $this->assertInstanceOf(RegexParser::class, $parser);
    }

    public function testGetXmlParser(): void
    {
        $parser = $this->factory->get('xml');

        $this->assertInstanceOf(XmlParser::class, $parser);
        $this->assertEquals('xml', $parser->getType());
    }

    public function testGetHtmlParser(): void
    {
        $parser = $this->factory->get('html');

        $this->assertInstanceOf(XmlParser::class, $parser);
        $this->assertEquals('html', $parser->getType());
    }

    public function testGetMarkdownParser(): void
    {
        $parser = $this->factory->get('markdown');

        $this->assertInstanceOf(MarkdownParser::class, $parser);
    }

    public function testGetCsvParser(): void
    {
        $parser = $this->factory->get('csv');

        $this->assertInstanceOf(CsvParser::class, $parser);
    }

    public function testGetTsvParser(): void
    {
        $parser = $this->factory->get('tsv');

        $this->assertInstanceOf(CsvParser::class, $parser);
        $this->assertEquals('tsv', $parser->getType());
    }

    public function testConvenienceMethods(): void
    {
        $this->assertInstanceOf(JsonParser::class, $this->factory->json());
        $this->assertInstanceOf(ListParser::class, $this->factory->list());
        $this->assertInstanceOf(RegexParser::class, $this->factory->regex());
        $this->assertInstanceOf(XmlParser::class, $this->factory->xml());
        $this->assertInstanceOf(XmlParser::class, $this->factory->html());
        $this->assertInstanceOf(MarkdownParser::class, $this->factory->markdown());
        $this->assertInstanceOf(CsvParser::class, $this->factory->csv());
        $this->assertInstanceOf(CsvParser::class, $this->factory->tsv());
    }

    public function testJsonWithSchema(): void
    {
        $schema = ['type' => 'object'];
        $parser = $this->factory->json($schema);

        $this->assertInstanceOf(JsonParser::class, $parser);
    }

    public function testHasParser(): void
    {
        $this->assertTrue($this->factory->has('json'));
        $this->assertTrue($this->factory->has('xml'));
        $this->assertFalse($this->factory->has('unknown'));
    }

    public function testGetTypes(): void
    {
        $types = $this->factory->getTypes();

        $this->assertContains('json', $types);
        $this->assertContains('xml', $types);
        $this->assertContains('markdown', $types);
        $this->assertContains('csv', $types);
    }

    public function testDetectJson(): void
    {
        $type = $this->factory->detectType('{"key": "value"}');

        $this->assertEquals('json', $type);
    }

    public function testDetectXml(): void
    {
        $type = $this->factory->detectType('<?xml version="1.0"?><root></root>');

        $this->assertEquals('xml', $type);
    }

    public function testDetectMarkdown(): void
    {
        $type = $this->factory->detectType('# Heading\n\nSome text');

        $this->assertEquals('markdown', $type);
    }

    public function testDetectCsv(): void
    {
        $type = $this->factory->detectType("name,age\njohn,30\njane,25");

        $this->assertEquals('csv', $type);
    }

    public function testDetectList(): void
    {
        $type = $this->factory->detectType("- Item 1\n- Item 2\n- Item 3");

        $this->assertEquals('list', $type);
    }

    public function testAutoParse(): void
    {
        $result = $this->factory->autoParse('{"name": "test"}');

        $this->assertIsArray($result);
        $this->assertEquals('test', $result['name']);
    }

    public function testGetUnknownParserThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->get('unknown');
    }
}
