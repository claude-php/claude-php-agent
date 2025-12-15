<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Parsers;

use ClaudeAgents\Parsers\RegexParser;
use PHPUnit\Framework\TestCase;

class RegexParserTest extends TestCase
{
    private RegexParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RegexParser();
    }

    public function testExtractAll(): void
    {
        $text = 'The numbers are 10, 20, and 30.';

        $result = $this->parser->extract($text, '/(\d+)/', 1);

        $this->assertCount(3, $result);
        $this->assertEquals('10', $result[0]);
        $this->assertEquals('20', $result[1]);
        $this->assertEquals('30', $result[2]);
    }

    public function testExtractOne(): void
    {
        $text = 'Error code: 404';

        $result = $this->parser->extractOne($text, '/(\d+)/', 1);

        $this->assertEquals('404', $result);
    }

    public function testExtractOneNoMatch(): void
    {
        $text = 'No numbers here';

        $result = $this->parser->extractOne($text, '/(\d+)/', 1);

        $this->assertNull($result);
    }

    public function testExtractKeyValue(): void
    {
        $text = "name: John\nage: 30\ncity: NYC";

        $result = $this->parser->extractKeyValue($text, '/(?<key>\w+):\s*(?<value>[^\n]+)/');

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('age', $result);
        $this->assertEquals('John', $result['name']);
        $this->assertEquals('30', $result['age']);
        $this->assertEquals('NYC', $result['city']);
    }

    public function testExtractKeyValueWithNumberedGroups(): void
    {
        $text = 'a=1, b=2, c=3';

        $result = $this->parser->extractKeyValue($text, '/(\w+)=(\d+)/');

        $this->assertEquals('1', $result['a']);
        $this->assertEquals('2', $result['b']);
        $this->assertEquals('3', $result['c']);
    }

    public function testExtractNumber(): void
    {
        $text = 'The price is $19.99';

        $result = $this->parser->extractNumber($text);

        $this->assertEquals(19.99, $result);
    }

    public function testExtractNumberNegative(): void
    {
        $text = 'Temperature: -5 degrees';

        $result = $this->parser->extractNumber($text);

        $this->assertEquals(-5.0, $result);
    }

    public function testExtractNumberNoMatch(): void
    {
        $text = 'No numbers here';

        $result = $this->parser->extractNumber($text);

        $this->assertNull($result);
    }

    public function testExtractEmails(): void
    {
        $text = 'Contact us at john@example.com or jane@test.org';

        $result = $this->parser->extractEmails($text);

        $this->assertCount(2, $result);
        $this->assertEquals('john@example.com', $result[0]);
        $this->assertEquals('jane@test.org', $result[1]);
    }

    public function testExtractUrls(): void
    {
        $text = 'Visit https://example.com or http://test.org for more info';

        $result = $this->parser->extractUrls($text);

        $this->assertCount(2, $result);
        $this->assertStringContainsString('example.com', $result[0]);
        $this->assertStringContainsString('test.org', $result[1]);
    }

    public function testExtractWithDifferentCaptureGroup(): void
    {
        $text = 'Error: File not found at /path/to/file.txt';

        $result = $this->parser->extractOne($text, '/(\w+): (.+) at (.+)/', 2);

        $this->assertEquals('File not found', $result);
    }

    public function testExtractEmptyResult(): void
    {
        $text = 'No matches here';

        $result = $this->parser->extract($text, '/xyz/', 1);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testExtractComplexPattern(): void
    {
        $text = 'Users: @john, @jane, @bob';

        $result = $this->parser->extract($text, '/@(\w+)/', 1);

        $this->assertCount(3, $result);
        $this->assertEquals('john', $result[0]);
        $this->assertEquals('jane', $result[1]);
        $this->assertEquals('bob', $result[2]);
    }
}
