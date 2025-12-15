<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Context;

use ClaudeAgents\Context\TokenCounter;
use PHPUnit\Framework\TestCase;

class TokenCounterTest extends TestCase
{
    public function testEstimateTokensForSimpleText(): void
    {
        $text = 'Hello, world!';
        $tokens = TokenCounter::estimateTokens($text);

        $this->assertGreaterThan(0, $tokens);
        $this->assertIsInt($tokens);
    }

    public function testEstimateTokensReturnsMinimumOne(): void
    {
        $tokens = TokenCounter::estimateTokens('');

        $this->assertGreaterThanOrEqual(1, $tokens);
    }

    public function testEstimateTokensForLongText(): void
    {
        $text = str_repeat('test ', 1000); // 5000 characters
        $tokens = TokenCounter::estimateTokens($text);

        // Should be roughly 1250 tokens (5000 / 4 chars per token)
        $this->assertGreaterThan(1000, $tokens);
        $this->assertLessThan(2000, $tokens);
    }

    public function testEstimateMessageTokensForStringContent(): void
    {
        $message = [
            'role' => 'user',
            'content' => 'What is the weather today?',
        ];

        $tokens = TokenCounter::estimateMessageTokens($message);

        $this->assertGreaterThan(5, $tokens); // Should include base tokens + content
    }

    public function testEstimateMessageTokensForArrayContent(): void
    {
        $message = [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'Hello'],
                ['type' => 'text', 'text' => 'World'],
            ],
        ];

        $tokens = TokenCounter::estimateMessageTokens($message);

        $this->assertGreaterThan(5, $tokens);
    }

    public function testEstimateMessageTokensWithoutContent(): void
    {
        $message = [
            'role' => 'system',
        ];

        $tokens = TokenCounter::estimateMessageTokens($message);

        $this->assertGreaterThanOrEqual(4, $tokens); // Base tokens
    }

    public function testEstimateConversationTokens(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'What is 2+2?'],
            ['role' => 'assistant', 'content' => 'The answer is 4.'],
        ];

        $tokens = TokenCounter::estimateConversationTokens($messages);

        $this->assertGreaterThan(10, $tokens);

        // Should be sum of individual message tokens
        $expectedSum = 0;
        foreach ($messages as $message) {
            $expectedSum += TokenCounter::estimateMessageTokens($message);
        }

        $this->assertEquals($expectedSum, $tokens);
    }

    public function testEstimateConversationTokensEmptyArray(): void
    {
        $tokens = TokenCounter::estimateConversationTokens([]);

        $this->assertEquals(0, $tokens);
    }

    public function testEstimateToolTokens(): void
    {
        $tool = [
            'name' => 'calculator',
            'description' => 'Performs basic arithmetic operations',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'operation' => ['type' => 'string'],
                    'a' => ['type' => 'number'],
                    'b' => ['type' => 'number'],
                ],
            ],
        ];

        $tokens = TokenCounter::estimateToolTokens($tool);

        $this->assertGreaterThan(10, $tokens); // Should include base + description + schema
    }

    public function testEstimateToolTokensWithoutDescription(): void
    {
        $tool = [
            'name' => 'calculator',
            'input_schema' => ['type' => 'object'],
        ];

        $tokens = TokenCounter::estimateToolTokens($tool);

        $this->assertGreaterThanOrEqual(10, $tokens); // Base tokens
    }

    public function testEstimateTotal(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];

        $tools = [
            [
                'name' => 'tool1',
                'description' => 'First tool',
                'input_schema' => ['type' => 'object'],
            ],
            [
                'name' => 'tool2',
                'description' => 'Second tool',
                'input_schema' => ['type' => 'object'],
            ],
        ];

        $total = TokenCounter::estimateTotal($messages, $tools);

        // Should equal messages + tools
        $expectedMessages = TokenCounter::estimateConversationTokens($messages);
        $expectedTools = 0;
        foreach ($tools as $tool) {
            $expectedTools += TokenCounter::estimateToolTokens($tool);
        }

        $this->assertEquals($expectedMessages + $expectedTools, $total);
    }

    public function testEstimateTotalWithoutTools(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Test message'],
        ];

        $total = TokenCounter::estimateTotal($messages);

        $this->assertEquals(TokenCounter::estimateConversationTokens($messages), $total);
    }

    public function testTokenEstimationConsistency(): void
    {
        $text = 'This is a test message';

        $tokens1 = TokenCounter::estimateTokens($text);
        $tokens2 = TokenCounter::estimateTokens($text);

        $this->assertEquals($tokens1, $tokens2, 'Token estimation should be consistent');
    }
}
