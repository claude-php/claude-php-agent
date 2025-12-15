<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Parsers;

use ClaudeAgents\Parsers\JsonParser;
use PHPUnit\Framework\TestCase;

class JsonParserTest extends TestCase
{
    private JsonParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new JsonParser();
    }

    public function testParseSimpleJson(): void
    {
        $text = '{"name": "John", "age": 30}';

        $result = $this->parser->parse($text);

        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function testParseJsonWithCodeBlock(): void
    {
        $text = "Here's the result:\n```json\n{\"status\": \"success\", \"count\": 5}\n```";

        $result = $this->parser->parse($text);

        $this->assertEquals(['status' => 'success', 'count' => 5], $result);
    }

    public function testParseJsonInText(): void
    {
        $text = "The data is: {\"value\": 42, \"valid\": true} and that's it.";

        $result = $this->parser->parse($text);

        $this->assertEquals(['value' => 42, 'valid' => true], $result);
    }

    public function testParseNestedJson(): void
    {
        $text = '{"user": {"name": "Alice", "roles": ["admin", "user"]}}';

        $result = $this->parser->parse($text);

        $this->assertIsArray($result['user']);
        $this->assertEquals('Alice', $result['user']['name']);
        $this->assertContains('admin', $result['user']['roles']);
    }

    public function testParseJsonArray(): void
    {
        $text = '[{"id": 1}, {"id": 2}, {"id": 3}]';

        $result = $this->parser->parse($text);

        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]['id']);
    }

    public function testParseJsonArrayInCodeBlock(): void
    {
        $text = "```json\n[1, 2, 3, 4, 5]\n```";

        $result = $this->parser->parse($text);

        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    public function testParseInvalidJsonThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No JSON found');

        $this->parser->parse('This is just plain text');
    }

    public function testParseMalformedJsonThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->parser->parse('{invalid json}');
    }

    public function testParseEmptyStringThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->parser->parse('');
    }
}
