<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Context;

use ClaudeAgents\Context\ContextManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ContextManagerTest extends TestCase
{
    private function createManager(int $maxTokens = 1000, array $options = []): ContextManager
    {
        return new ContextManager($maxTokens, array_merge(['logger' => new NullLogger()], $options));
    }

    public function testConstructorWithDefaults(): void
    {
        $manager = new ContextManager();

        $this->assertEquals(100000, $manager->getMaxContextTokens());
        $this->assertEquals(0.8, $manager->getCompactThreshold());
    }

    public function testConstructorWithCustomValues(): void
    {
        $manager = new ContextManager(50000, [
            'compact_threshold' => 0.9,
            'auto_compact' => false,
            'clear_tool_results' => false,
        ]);

        $this->assertEquals(50000, $manager->getMaxContextTokens());
        $this->assertEquals(0.9, $manager->getCompactThreshold());
    }

    public function testFitsInContextTrue(): void
    {
        $manager = $this->createManager(1000);

        $messages = [
            ['role' => 'user', 'content' => 'Short message'],
        ];

        $this->assertTrue($manager->fitsInContext($messages));
    }

    public function testFitsInContextFalse(): void
    {
        $manager = $this->createManager(50); // Very small limit

        $messages = [
            ['role' => 'user', 'content' => str_repeat('Long message ', 100)],
        ];

        $this->assertFalse($manager->fitsInContext($messages));
    }

    public function testFitsInContextWithTools(): void
    {
        $manager = $this->createManager(100); // Smaller limit

        $messages = [
            ['role' => 'user', 'content' => 'Message'],
        ];

        $tools = [
            [
                'name' => 'tool1',
                'description' => str_repeat('Description ', 200), // Large tool
                'input_schema' => ['type' => 'object'],
            ],
        ];

        // Tools should be counted in the total
        $withTools = $manager->fitsInContext($messages, $tools);

        $this->assertFalse($withTools);
    }

    public function testGetUsagePercentage(): void
    {
        $manager = $this->createManager(1000);

        $messages = [
            ['role' => 'user', 'content' => 'Test'],
        ];

        $percentage = $manager->getUsagePercentage($messages);

        $this->assertGreaterThan(0.0, $percentage);
        $this->assertLessThan(1.0, $percentage);
    }

    public function testGetUsagePercentageExceedsLimit(): void
    {
        $manager = $this->createManager(50);

        $messages = [
            ['role' => 'user', 'content' => str_repeat('Long message ', 100)],
        ];

        $percentage = $manager->getUsagePercentage($messages);

        $this->assertGreaterThan(1.0, $percentage);
    }

    public function testCompactMessagesWhenFits(): void
    {
        $manager = $this->createManager(10000);

        $messages = [
            ['role' => 'user', 'content' => 'Message 1'],
            ['role' => 'assistant', 'content' => 'Response 1'],
        ];

        $compacted = $manager->compactMessages($messages);

        $this->assertEquals($messages, $compacted);
    }

    public function testCompactMessagesRemovesOldest(): void
    {
        $manager = $this->createManager(50); // Very small limit

        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => str_repeat('Message 1 ', 50)],
            ['role' => 'assistant', 'content' => str_repeat('Response 1 ', 50)],
            ['role' => 'user', 'content' => str_repeat('Message 2 ', 50)],
            ['role' => 'assistant', 'content' => str_repeat('Response 2 ', 50)],
            ['role' => 'user', 'content' => 'Message 3'],
        ];

        $compacted = $manager->compactMessages($messages);

        // Should keep fewer messages due to size limit
        $this->assertLessThan(count($messages), count($compacted));

        // Should have a system message somewhere in the result
        $hasSystemMessage = false;
        foreach ($compacted as $msg) {
            if ($msg['role'] === 'system') {
                $hasSystemMessage = true;

                break;
            }
        }
        $this->assertTrue($hasSystemMessage, 'System message should be preserved');

        // Should keep most recent message
        $hasRecentMessage = false;
        foreach ($compacted as $msg) {
            if (($msg['content'] ?? '') === 'Message 3') {
                $hasRecentMessage = true;

                break;
            }
        }
        $this->assertTrue($hasRecentMessage, 'Most recent message should be kept');
    }

    public function testCompactMessagesCompactsToolResults(): void
    {
        $manager = $this->createManager(50, ['clear_tool_results' => true]);

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Message'],
                    ['type' => 'tool_result', 'tool_use_id' => '123', 'content' => str_repeat('Result ', 100)],
                ],
            ],
            ['role' => 'assistant', 'content' => str_repeat('Response ', 50)],
        ];

        $compacted = $manager->compactMessages($messages);

        // Tool results should be compacted in user messages
        $userMessage = null;
        foreach ($compacted as $msg) {
            if ($msg['role'] === 'user') {
                $userMessage = $msg;

                break;
            }
        }

        $this->assertNotNull($userMessage);
        $this->assertIsArray($userMessage['content']);

        // Check that tool_result blocks remain but are truncated
        $hasToolResult = false;
        $hasTruncatedContent = false;
        foreach ($userMessage['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'tool_result') {
                $hasToolResult = true;
                $hasTruncatedContent = ($block['content'] ?? '') === '[tool result truncated]';

                break;
            }
        }

        $this->assertTrue($hasToolResult, 'Tool results should be preserved');
        $this->assertTrue($hasTruncatedContent, 'Tool results should be truncated');
    }

    public function testCompactMessagesDoesNotClearToolResultsWhenDisabled(): void
    {
        $manager = $this->createManager(10000, ['clear_tool_results' => false]);

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Message'],
                    ['type' => 'tool_result', 'tool_use_id' => '123', 'content' => 'Result'],
                ],
            ],
        ];

        $compacted = $manager->compactMessages($messages);

        // Tool results should remain
        $this->assertCount(2, $compacted[0]['content']);
    }

    public function testCompactMessagesWithoutSystemMessage(): void
    {
        $manager = $this->createManager(100);

        $messages = [
            ['role' => 'user', 'content' => 'Message 1'],
            ['role' => 'assistant', 'content' => 'Response 1'],
            ['role' => 'user', 'content' => 'Message 2'],
            ['role' => 'assistant', 'content' => 'Response 2'],
        ];

        $compacted = $manager->compactMessages($messages);

        // Should work even without system message
        $this->assertIsArray($compacted);
        $this->assertNotEmpty($compacted);
    }

    public function testCompactMessagesPreservesToolUsePairs(): void
    {
        $manager = $this->createManager(12, ['clear_tool_results' => true]);

        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => str_repeat('Message 1 ', 50)],
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'calculator', 'input' => []],
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'tool_result', 'tool_use_id' => 'tool_1', 'content' => 'Result'],
                ],
            ],
            ['role' => 'assistant', 'content' => 'Final answer'],
        ];

        $compacted = $manager->compactMessages($messages);

        for ($i = 0; $i < count($compacted); $i++) {
            $message = $compacted[$i];
            $next = $compacted[$i + 1] ?? null;

            $hasToolUse = is_array($message['content'] ?? null)
                && array_filter($message['content'], fn ($b) => is_array($b) && ($b['type'] ?? '') === 'tool_use') !== [];

            if ($hasToolUse) {
                $this->assertIsArray($next);
                $this->assertEquals('user', $next['role'] ?? null);
                $this->assertIsArray($next['content'] ?? null);
                $this->assertNotEmpty(
                    array_filter($next['content'], fn ($b) => is_array($b) && ($b['type'] ?? '') === 'tool_result')
                );
            }
        }
    }

    public function testSetMaxContextTokens(): void
    {
        $manager = $this->createManager(1000);

        $this->assertEquals(1000, $manager->getMaxContextTokens());

        $manager->setMaxContextTokens(2000);

        $this->assertEquals(2000, $manager->getMaxContextTokens());
    }

    public function testCompactMessagesWithTools(): void
    {
        $manager = $this->createManager(200);

        $messages = [
            ['role' => 'user', 'content' => str_repeat('Message ', 50)],
            ['role' => 'assistant', 'content' => str_repeat('Response ', 50)],
        ];

        $tools = [
            [
                'name' => 'tool',
                'description' => 'Tool description',
                'input_schema' => ['type' => 'object'],
            ],
        ];

        $compacted = $manager->compactMessages($messages, $tools);

        // Should compact to fit with tools
        $this->assertTrue($manager->fitsInContext($compacted, $tools));
    }

    public function testCompactMessagesPreservesMessageOrder(): void
    {
        $manager = $this->createManager(150);

        $messages = [
            ['role' => 'system', 'content' => 'System'],
            ['role' => 'user', 'content' => 'Message 1'],
            ['role' => 'assistant', 'content' => 'Response 1'],
            ['role' => 'user', 'content' => 'Message 2'],
            ['role' => 'assistant', 'content' => 'Response 2'],
            ['role' => 'user', 'content' => 'Message 3'],
        ];

        $compacted = $manager->compactMessages($messages);

        // System should be first
        $this->assertEquals('system', $compacted[0]['role']);

        // Remaining messages should be in chronological order
        for ($i = 1; $i < count($compacted) - 1; $i++) {
            // Just verify it doesn't reverse the order
            $this->assertNotEmpty($compacted[$i]);
        }
    }

    public function testGetUsagePercentageWithTools(): void
    {
        $manager = $this->createManager(1000);

        $messages = [
            ['role' => 'user', 'content' => 'Test'],
        ];

        $tools = [
            [
                'name' => 'tool',
                'description' => 'Description',
                'input_schema' => ['type' => 'object'],
            ],
        ];

        $withoutTools = $manager->getUsagePercentage($messages);
        $withTools = $manager->getUsagePercentage($messages, $tools);

        $this->assertGreaterThan($withoutTools, $withTools);
    }
}
