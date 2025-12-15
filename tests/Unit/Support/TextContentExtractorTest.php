<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Support;

use ClaudeAgents\Support\TextContentExtractor;
use PHPUnit\Framework\TestCase;

class TextContentExtractorTest extends TestCase
{
    public function test_extract_with_text_blocks(): void
    {
        $content = [
            ['type' => 'text', 'text' => 'Hello'],
            ['type' => 'text', 'text' => 'World'],
        ];

        $result = TextContentExtractor::extract($content);

        $this->assertSame("Hello\nWorld", $result);
    }

    public function test_extract_with_mixed_blocks(): void
    {
        $content = [
            ['type' => 'text', 'text' => 'First'],
            ['type' => 'tool_use', 'id' => '123'],
            ['type' => 'text', 'text' => 'Second'],
        ];

        $result = TextContentExtractor::extract($content);

        $this->assertSame("First\nSecond", $result);
    }

    public function test_extract_with_empty_content(): void
    {
        $result = TextContentExtractor::extract([]);

        $this->assertSame('', $result);
    }

    public function test_extract_ignores_non_text_blocks(): void
    {
        $content = [
            ['type' => 'tool_use', 'id' => '123'],
            ['type' => 'tool_result', 'content' => 'result'],
        ];

        $result = TextContentExtractor::extract($content);

        $this->assertSame('', $result);
    }

    public function test_extract_handles_missing_text_field(): void
    {
        $content = [
            ['type' => 'text'],  // No 'text' field
            ['type' => 'text', 'text' => 'Valid'],
        ];

        $result = TextContentExtractor::extract($content);

        $this->assertSame("\nValid", $result);
    }

    public function test_extract_from_response_with_array(): void
    {
        $response = [
            'content' => [
                ['type' => 'text', 'text' => 'Test'],
            ],
        ];

        $result = TextContentExtractor::extractFromResponse($response);

        $this->assertSame('Test', $result);
    }

    public function test_extract_from_response_with_object(): void
    {
        $response = (object) [
            'content' => [
                ['type' => 'text', 'text' => 'Test'],
            ],
        ];

        $result = TextContentExtractor::extractFromResponse($response);

        $this->assertSame('Test', $result);
    }

    public function test_extract_from_response_with_missing_content(): void
    {
        $response = (object) [];

        $result = TextContentExtractor::extractFromResponse($response);

        $this->assertSame('', $result);
    }

    public function test_extract_preserves_whitespace_in_text(): void
    {
        $content = [
            ['type' => 'text', 'text' => "Line 1\n  Indented\nLine 3"],
        ];

        $result = TextContentExtractor::extract($content);

        $this->assertSame("Line 1\n  Indented\nLine 3", $result);
    }

    public function test_extract_handles_unicode_content(): void
    {
        $content = [
            ['type' => 'text', 'text' => '你好 世界 🌍'],
        ];

        $result = TextContentExtractor::extract($content);

        $this->assertSame('你好 世界 🌍', $result);
    }

    public function test_extract_handles_special_characters(): void
    {
        $content = [
            ['type' => 'text', 'text' => '<html>&amp;</html>'],
        ];

        $result = TextContentExtractor::extract($content);

        $this->assertSame('<html>&amp;</html>', $result);
    }
}
