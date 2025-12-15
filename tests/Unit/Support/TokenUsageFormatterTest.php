<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Support;

use ClaudeAgents\Support\TokenUsageFormatter;
use PHPUnit\Framework\TestCase;

class TokenUsageFormatterTest extends TestCase
{
    public function test_format_with_object_response(): void
    {
        $response = (object) [
            'usage' => (object) [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ];

        $result = TokenUsageFormatter::format($response);

        $this->assertSame([
            'input' => 100,
            'output' => 50,
            'total' => 150,
        ], $result);
    }

    public function test_format_with_array_response(): void
    {
        $response = [
            'usage' => [
                'input_tokens' => 200,
                'output_tokens' => 75,
            ],
        ];

        $result = TokenUsageFormatter::format($response);

        $this->assertSame([
            'input' => 200,
            'output' => 75,
            'total' => 275,
        ], $result);
    }

    public function test_format_with_missing_usage(): void
    {
        $response = (object) [];

        $result = TokenUsageFormatter::format($response);

        $this->assertSame([
            'input' => 0,
            'output' => 0,
            'total' => 0,
        ], $result);
    }

    public function test_format_with_partial_usage(): void
    {
        $response = (object) [
            'usage' => (object) [
                'input_tokens' => 50,
            ],
        ];

        $result = TokenUsageFormatter::format($response);

        $this->assertSame([
            'input' => 50,
            'output' => 0,
            'total' => 50,
        ], $result);
    }

    public function test_format_simple_with_object_response(): void
    {
        $response = (object) [
            'usage' => (object) [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ];

        $result = TokenUsageFormatter::formatSimple($response);

        $this->assertSame([
            'input' => 100,
            'output' => 50,
        ], $result);
    }

    public function test_format_simple_with_array_response(): void
    {
        $response = [
            'usage' => [
                'input_tokens' => 200,
                'output_tokens' => 75,
            ],
        ];

        $result = TokenUsageFormatter::formatSimple($response);

        $this->assertSame([
            'input' => 200,
            'output' => 75,
        ], $result);
    }

    public function test_format_simple_with_missing_usage(): void
    {
        $response = (object) [];

        $result = TokenUsageFormatter::formatSimple($response);

        $this->assertSame([
            'input' => 0,
            'output' => 0,
        ], $result);
    }

    public function test_format_handles_zero_tokens(): void
    {
        $response = (object) [
            'usage' => (object) [
                'input_tokens' => 0,
                'output_tokens' => 0,
            ],
        ];

        $result = TokenUsageFormatter::format($response);

        $this->assertSame([
            'input' => 0,
            'output' => 0,
            'total' => 0,
        ], $result);
    }

    public function test_format_handles_large_token_counts(): void
    {
        $response = (object) [
            'usage' => (object) [
                'input_tokens' => 100000,
                'output_tokens' => 50000,
            ],
        ];

        $result = TokenUsageFormatter::format($response);

        $this->assertSame([
            'input' => 100000,
            'output' => 50000,
            'total' => 150000,
        ], $result);
    }

    public function test_format_mixed_object_and_array_usage(): void
    {
        $response = (object) [
            'usage' => [
                'input_tokens' => 30,
                'output_tokens' => 20,
            ],
        ];

        $result = TokenUsageFormatter::format($response);

        $this->assertSame([
            'input' => 30,
            'output' => 20,
            'total' => 50,
        ], $result);
    }
}
