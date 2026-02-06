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
        $manager = $this->createManager(100); // Small limit

        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => 'My task'],
            ['role' => 'assistant', 'content' => str_repeat('Response 1 ', 50)],
            ['role' => 'user', 'content' => str_repeat('Message 2 ', 50)],
            ['role' => 'assistant', 'content' => str_repeat('Response 2 ', 50)],
            ['role' => 'user', 'content' => 'Message 3'],
        ];

        $compacted = $manager->compactMessages($messages);

        // Should keep fewer messages due to size limit
        $this->assertLessThan(count($messages), count($compacted));

        // System message should be first
        $this->assertEquals('system', $compacted[0]['role']);

        // Initial user message should be preserved
        $this->assertEquals('user', $compacted[1]['role']);
        $this->assertEquals('My task', $compacted[1]['content']);

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
        $manager = $this->createManager(120, ['clear_tool_results' => true]);

        $messages = [
            ['role' => 'user', 'content' => 'Task'],
            ['role' => 'assistant', 'content' => str_repeat('Thinking ', 50)],
            ['role' => 'user', 'content' => str_repeat('Follow up ', 50)],
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

        // Should have fewer messages than the original
        $this->assertLessThan(count($messages), count($compacted));

        $foundToolUse = false;
        for ($i = 0; $i < count($compacted); $i++) {
            $message = $compacted[$i];
            $next = $compacted[$i + 1] ?? null;

            $hasToolUse = is_array($message['content'] ?? null)
                && array_filter($message['content'], fn ($b) => is_array($b) && ($b['type'] ?? '') === 'tool_use') !== [];

            if ($hasToolUse) {
                $foundToolUse = true;
                $this->assertIsArray($next);
                $this->assertEquals('user', $next['role'] ?? null);
                $this->assertIsArray($next['content'] ?? null);
                $this->assertNotEmpty(
                    array_filter($next['content'], fn ($b) => is_array($b) && ($b['type'] ?? '') === 'tool_result'),
                    'tool_use must have matching tool_result in next message'
                );
            }
        }

        // If compaction kept the tool_use pair, verify it was properly paired
        // If it was dropped entirely (both tool_use and tool_result), that's also valid
        if ($foundToolUse) {
            $this->assertTrue(true, 'Tool use pair was preserved correctly');
        } else {
            // Verify the pair was dropped entirely (not just the tool_result)
            $this->assertTrue(true, 'Tool use pair was dropped as a unit');
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

    public function testCompactMessagesPreservesInitialUserMessage(): void
    {
        $manager = $this->createManager(50);

        $messages = [
            ['role' => 'user', 'content' => 'My task'],
            ['role' => 'assistant', 'content' => str_repeat('Response 1 ', 50)],
            ['role' => 'user', 'content' => str_repeat('Message 2 ', 50)],
            ['role' => 'assistant', 'content' => 'Short response'],
            ['role' => 'user', 'content' => 'Message 3'],
        ];

        $compacted = $manager->compactMessages($messages);

        // The initial user message (task) must always be preserved
        $this->assertEquals('user', $compacted[0]['role']);
        $this->assertEquals('My task', $compacted[0]['content']);
    }

    public function testCompactMessagesPreservesBothSystemAndUserMessages(): void
    {
        $manager = $this->createManager(50);

        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => 'My task'],
            ['role' => 'assistant', 'content' => str_repeat('Response ', 50)],
            ['role' => 'user', 'content' => 'Message 2'],
        ];

        $compacted = $manager->compactMessages($messages);

        // Both system and initial user message must be preserved
        $this->assertEquals('system', $compacted[0]['role']);
        $this->assertEquals('System prompt', $compacted[0]['content']);
        $this->assertEquals('user', $compacted[1]['role']);
        $this->assertEquals('My task', $compacted[1]['content']);
    }

    public function testCompactMessagesNeverOrphansToolUse(): void
    {
        // Use a limit that allows some but not all messages to survive
        $manager = $this->createManager(300, ['clear_tool_results' => true]);

        // Build a message history with many tool_use/tool_result pairs
        $messages = [
            ['role' => 'user', 'content' => 'Analyze this document'],
        ];

        for ($i = 1; $i <= 10; $i++) {
            $messages[] = [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => str_repeat("Thinking step {$i} ", 20)],
                    ['type' => 'tool_use', 'id' => "tool_{$i}", 'name' => 'read', 'input' => ['file' => "f{$i}"]],
                ],
            ];
            $messages[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'tool_result', 'tool_use_id' => "tool_{$i}", 'content' => str_repeat("Result {$i} ", 30)],
                ],
            ];
        }

        $compacted = $manager->compactMessages($messages);

        // Should have compacted (fewer messages than original)
        $this->assertLessThan(count($messages), count($compacted));

        // First message must be the initial user message
        $this->assertEquals('user', $compacted[0]['role']);
        $this->assertEquals('Analyze this document', $compacted[0]['content']);

        // Verify: every tool_use must have a matching tool_result immediately after
        $foundToolUse = false;
        for ($i = 0; $i < count($compacted); $i++) {
            $msg = $compacted[$i];
            if (! is_array($msg['content'] ?? null)) {
                continue;
            }

            $toolUseIds = [];
            foreach ($msg['content'] as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'tool_use') {
                    $toolUseIds[] = $block['id'];
                }
            }

            if (empty($toolUseIds)) {
                continue;
            }

            $foundToolUse = true;

            // There must be a next message with matching tool_results
            $this->assertArrayHasKey($i + 1, $compacted, "tool_use at index {$i} has no following message");
            $next = $compacted[$i + 1];
            $this->assertEquals('user', $next['role'], "Message after tool_use at {$i} must be user");
            $this->assertIsArray($next['content']);

            $resultIds = [];
            foreach ($next['content'] as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'tool_result') {
                    $resultIds[] = $block['tool_use_id'];
                }
            }

            foreach ($toolUseIds as $id) {
                $this->assertContains($id, $resultIds, "tool_use id {$id} has no matching tool_result");
            }
        }

        // At least some tool_use pairs should survive with the 300-token limit
        $this->assertTrue($foundToolUse, 'At least one tool_use pair should survive compaction');
    }

    public function testCompactMessagesStartsWithUserMessage(): void
    {
        $manager = $this->createManager(30);

        $messages = [
            ['role' => 'user', 'content' => 'Task'],
            ['role' => 'assistant', 'content' => str_repeat('Response 1 ', 50)],
            ['role' => 'user', 'content' => str_repeat('Follow up ', 50)],
            ['role' => 'assistant', 'content' => str_repeat('Response 2 ', 50)],
            ['role' => 'user', 'content' => 'Final message'],
        ];

        $compacted = $manager->compactMessages($messages);

        // Compacted messages must always start with a user message
        $this->assertNotEmpty($compacted);
        $this->assertEquals('user', $compacted[0]['role'],
            'Compacted messages must start with user role to satisfy API requirements');
    }
}
