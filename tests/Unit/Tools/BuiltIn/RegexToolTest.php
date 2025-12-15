<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Tools\BuiltIn;

use ClaudeAgents\Tools\BuiltIn\RegexTool;
use PHPUnit\Framework\TestCase;

class RegexToolTest extends TestCase
{
    public function testCreateWithDefaults(): void
    {
        $tool = RegexTool::create();
        $this->assertEquals('regex', $tool->getName());
    }

    public function testMatchOperation(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'match',
            'pattern' => '/\d+/',
            'text' => 'The year is 2024',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['matched']);
        $this->assertEquals('2024', $data['matches'][0]);
    }

    public function testMatchAllOperation(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'match_all',
            'pattern' => '/\d+/',
            'text' => 'Years: 2023, 2024, 2025',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['matched']);
        $this->assertEquals(3, $data['count']);
    }

    public function testReplaceOperation(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'replace',
            'pattern' => '/\d+/',
            'text' => 'Year 2023',
            'replacement' => '2024',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals('Year 2024', $data['result']);
        $this->assertEquals(1, $data['replacements']);
    }

    public function testSplitOperation(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'split',
            'pattern' => '/,\s*/',
            'text' => 'apple, banana, cherry',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(3, $data['count']);
        $this->assertEquals(['apple', 'banana', 'cherry'], $data['parts']);
    }

    public function testTestOperation(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'test',
            'pattern' => '/\d+/',
            'text' => '',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['valid']);
    }

    public function testExtractOperation(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'extract',
            'pattern' => '/(\w+)@(\w+\.\w+)/',
            'text' => 'Emails: alice@example.com and bob@test.org',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(2, $data['count']);
        $this->assertEquals('alice', $data['extracted'][0][0]);
        $this->assertEquals('example.com', $data['extracted'][0][1]);
    }

    public function testInvalidPattern(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'match',
            'pattern' => '/[invalid/',
            'text' => 'test',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('invalid', strtolower($result->getContent()));
    }

    public function testEmptyPattern(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'match',
            'pattern' => '',
            'text' => 'test',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('required', strtolower($result->getContent()));
    }

    public function testCaseInsensitiveMatch(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'match',
            'pattern' => '/hello/i',
            'text' => 'HELLO World',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['matched']);
    }

    public function testMatchLimit(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'match_all',
            'pattern' => '/\d+/',
            'text' => '1 2 3 4 5',
            'limit' => 3,
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(3, count($data['matches']));
        $this->assertTrue($data['limited']);
    }

    public function testReplaceWithoutReplacement(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'replace',
            'pattern' => '/\d+/',
            'text' => 'Year 2023',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('required', strtolower($result->getContent()));
    }

    public function testTextLengthLimit(): void
    {
        $tool = RegexTool::create(['max_text_length' => 100]);

        $longText = str_repeat('x', 200);

        $result = $tool->execute([
            'operation' => 'match',
            'pattern' => '/x/',
            'text' => $longText,
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('too long', strtolower($result->getContent()));
    }

    public function testComplexPattern(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'match_all',
            'pattern' => '/\b\w+@\w+\.\w+\b/',
            'text' => 'Contact us at info@example.com or support@test.org',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(2, $data['count']);
    }

    public function testNoMatchFound(): void
    {
        $tool = RegexTool::create();

        $result = $tool->execute([
            'operation' => 'match',
            'pattern' => '/\d+/',
            'text' => 'No numbers here',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertFalse($data['matched']);
    }

    public function testInputSchema(): void
    {
        $tool = RegexTool::create();
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('operation', $schema['properties']);
        $this->assertArrayHasKey('pattern', $schema['properties']);
        $this->assertContains('operation', $schema['required']);
        $this->assertContains('pattern', $schema['required']);
    }
}
