<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\Exceptions\SkillValidationException;
use ClaudeAgents\Skills\FrontmatterParser;
use PHPUnit\Framework\TestCase;

class FrontmatterParserTest extends TestCase
{
    public function test_parses_basic_frontmatter(): void
    {
        $content = <<<MD
---
name: test-skill
description: A test skill
---

# Test Skill

Instructions here.
MD;

        $result = FrontmatterParser::parse($content);

        $this->assertArrayHasKey('frontmatter', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertEquals('test-skill', $result['frontmatter']['name']);
        $this->assertEquals('A test skill', $result['frontmatter']['description']);
        $this->assertStringContainsString('# Test Skill', $result['body']);
        $this->assertStringContainsString('Instructions here.', $result['body']);
    }

    public function test_parses_quoted_strings(): void
    {
        $content = <<<MD
---
name: my-skill
description: "A skill with special: chars"
---

Body
MD;

        $result = FrontmatterParser::parse($content);

        $this->assertEquals('my-skill', $result['frontmatter']['name']);
        $this->assertEquals('A skill with special: chars', $result['frontmatter']['description']);
    }

    public function test_parses_boolean_values(): void
    {
        $content = <<<MD
---
name: mode-skill
description: A mode skill
disable-model-invocation: true
mode: false
---

Body
MD;

        $result = FrontmatterParser::parse($content);

        $this->assertTrue($result['frontmatter']['disable-model-invocation']);
        $this->assertFalse($result['frontmatter']['mode']);
    }

    public function test_parses_nested_metadata(): void
    {
        $content = <<<MD
---
name: nested-skill
description: Skill with metadata
metadata:
  author: test-author
  version: "1.0.0"
---

Body
MD;

        $result = FrontmatterParser::parse($content);

        $this->assertIsArray($result['frontmatter']['metadata']);
        $this->assertEquals('test-author', $result['frontmatter']['metadata']['author']);
        $this->assertEquals('1.0.0', $result['frontmatter']['metadata']['version']);
    }

    public function test_parses_inline_arrays(): void
    {
        $content = <<<MD
---
name: array-skill
description: Skill with arrays
metadata:
  tags: [php, testing, code]
---

Body
MD;

        $result = FrontmatterParser::parse($content);

        $this->assertIsArray($result['frontmatter']['metadata']);
    }

    public function test_parses_numeric_values(): void
    {
        $content = <<<MD
---
name: numeric-skill
description: Skill with numbers
metadata:
  priority: 5
  weight: 0.75
---

Body
MD;

        $result = FrontmatterParser::parse($content);

        $this->assertIsArray($result['frontmatter']['metadata']);
        $this->assertEquals(5, $result['frontmatter']['metadata']['priority']);
        $this->assertEquals(0.75, $result['frontmatter']['metadata']['weight']);
    }

    public function test_parses_null_values(): void
    {
        $content = <<<MD
---
name: null-skill
description: Skill with null
license: null
---

Body
MD;

        $result = FrontmatterParser::parse($content);

        $this->assertNull($result['frontmatter']['license']);
    }

    public function test_throws_on_missing_frontmatter(): void
    {
        $this->expectException(SkillValidationException::class);
        $this->expectExceptionMessage('must start with YAML frontmatter');

        FrontmatterParser::parse('# No frontmatter here');
    }

    public function test_throws_on_unclosed_frontmatter(): void
    {
        $this->expectException(SkillValidationException::class);
        $this->expectExceptionMessage('must be closed');

        FrontmatterParser::parse("---\nname: broken\n");
    }

    public function test_handles_empty_body(): void
    {
        $content = <<<MD
---
name: empty-body
description: No instructions
---
MD;

        $result = FrontmatterParser::parse($content);

        $this->assertEquals('empty-body', $result['frontmatter']['name']);
        $this->assertEquals('', $result['body']);
    }

    public function test_handles_comments_in_yaml(): void
    {
        $content = <<<MD
---
name: commented-skill
# This is a comment
description: A skill
---

Body
MD;

        $result = FrontmatterParser::parse($content);

        $this->assertEquals('commented-skill', $result['frontmatter']['name']);
        $this->assertEquals('A skill', $result['frontmatter']['description']);
        $this->assertArrayNotHasKey('#', $result['frontmatter']);
    }

    public function test_generates_frontmatter_string(): void
    {
        $data = [
            'name' => 'generated-skill',
            'description' => 'A generated skill',
        ];

        $result = FrontmatterParser::generate($data);

        $this->assertStringStartsWith('---', $result);
        $this->assertStringEndsWith("---\n", $result);
        $this->assertStringContainsString('name: generated-skill', $result);
        $this->assertStringContainsString('description: A generated skill', $result);
    }

    public function test_generates_frontmatter_with_nested_data(): void
    {
        $data = [
            'name' => 'nested-gen',
            'description' => 'Test',
            'metadata' => [
                'author' => 'test',
                'version' => '1.0.0',
            ],
        ];

        $result = FrontmatterParser::generate($data);

        $this->assertStringContainsString('metadata:', $result);
        $this->assertStringContainsString('author: test', $result);
    }

    public function test_roundtrip_parse_and_generate(): void
    {
        $original = <<<MD
---
name: roundtrip-skill
description: Test roundtrip
license: MIT
---

# Instructions

Do the thing.
MD;

        $parsed = FrontmatterParser::parse($original);
        $generated = FrontmatterParser::generate($parsed['frontmatter']);
        $reparsed = FrontmatterParser::parse($generated . "\n" . $parsed['body']);

        $this->assertEquals($parsed['frontmatter']['name'], $reparsed['frontmatter']['name']);
        $this->assertEquals($parsed['frontmatter']['description'], $reparsed['frontmatter']['description']);
    }

    public function test_handles_leading_whitespace(): void
    {
        $content = "  \n---\nname: spaced\ndescription: test\n---\nBody";

        $result = FrontmatterParser::parse($content);

        $this->assertEquals('spaced', $result['frontmatter']['name']);
    }

    public function test_parses_dependencies_as_array(): void
    {
        $content = <<<MD
---
name: dep-skill
description: Has deps
dependencies:
  - phpunit/phpunit
  - guzzlehttp/guzzle
---

Body
MD;

        $result = FrontmatterParser::parse($content);

        $this->assertIsArray($result['frontmatter']['dependencies']);
        $this->assertContains('phpunit/phpunit', $result['frontmatter']['dependencies']);
        $this->assertContains('guzzlehttp/guzzle', $result['frontmatter']['dependencies']);
    }
}
