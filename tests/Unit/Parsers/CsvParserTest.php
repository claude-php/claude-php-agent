<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Parsers;

use ClaudeAgents\Parsers\CsvParser;
use PHPUnit\Framework\TestCase;

class CsvParserTest extends TestCase
{
    private CsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CsvParser();
    }

    public function testParseWithHeaders(): void
    {
        $csv = "Name,Age,City\nJohn,30,NYC\nJane,25,LA";

        $result = $this->parser->parse($csv);

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result[0]['Name']);
        $this->assertEquals('30', $result[0]['Age']);
        $this->assertEquals('Jane', $result[1]['Name']);
    }

    public function testParseWithoutHeaders(): void
    {
        $csv = "John,30,NYC\nJane,25,LA";

        $parser = (new CsvParser())->withoutHeaders();
        $result = $parser->parse($csv);

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result[0][0]);
        $this->assertEquals('30', $result[0][1]);
    }

    public function testParseTabDelimited(): void
    {
        $tsv = "Name\tAge\nJohn\t30\nJane\t25";

        $parser = (new CsvParser())->asTab();
        $result = $parser->parse($tsv);

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result[0]['Name']);
    }

    public function testParseSemicolonDelimited(): void
    {
        $csv = "Name;Age\nJohn;30\nJane;25";

        $parser = (new CsvParser())->asSemicolon();
        $result = $parser->parse($csv);

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result[0]['Name']);
    }

    public function testParseWithTypeConversion(): void
    {
        $csv = "Name,Age,Active\nJohn,30,true\nJane,25,false";

        $parser = (new CsvParser())->withTypeConversion();
        $result = $parser->parse($csv);

        $this->assertIsInt($result[0]['Age']);
        $this->assertEquals(30, $result[0]['Age']);
        $this->assertIsBool($result[0]['Active']);
        $this->assertTrue($result[0]['Active']);
    }

    public function testParseQuotedFields(): void
    {
        $csv = "Name,Description\n\"John Doe\",\"A person, with comma\"\n\"Jane\",\"Another\"";

        $result = $this->parser->parse($csv);

        $this->assertEquals('John Doe', $result[0]['Name']);
        $this->assertStringContainsString('comma', $result[0]['Description']);
    }

    public function testParseFromCodeBlock(): void
    {
        $text = "```csv\nName,Age\nJohn,30\n```";

        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result[0]['Name']);
    }

    public function testToCsv(): void
    {
        $data = [
            ['Name' => 'John', 'Age' => 30],
            ['Name' => 'Jane', 'Age' => 25],
        ];

        $csv = $this->parser->toCsv($data);

        $this->assertStringContainsString('Name,Age', $csv);
        $this->assertStringContainsString('John,30', $csv);
    }

    public function testParseEmptyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->parser->parse('');
    }

    public function testGetType(): void
    {
        $this->assertEquals('csv', $this->parser->getType());
        $this->assertEquals('tsv', (new CsvParser())->asTab()->getType());
    }

    public function testGetFormatInstructions(): void
    {
        $instructions = $this->parser->getFormatInstructions();

        $this->assertStringContainsString('CSV', $instructions);
    }
}
