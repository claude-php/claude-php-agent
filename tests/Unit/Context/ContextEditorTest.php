<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Context;

use ClaudeAgents\Context\ContextEditor;
use PHPUnit\Framework\TestCase;

class ContextEditorTest extends TestCase
{
    public function testClearToolResults(): void
    {
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello'],
                    ['type' => 'tool_result', 'tool_use_id' => '123', 'content' => 'Result'],
                ],
            ],
            [
                'role' => 'assistant',
                'content' => 'Response',
            ],
        ];

        $cleaned = ContextEditor::clearToolResults($messages);

        $this->assertCount(2, $cleaned);
        $this->assertCount(1, $cleaned[0]['content']); // Tool result removed
        $this->assertEquals('text', $cleaned[0]['content'][0]['type']);
    }

    public function testClearToolResultsPreservesStringContent(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Simple string message'],
            ['role' => 'assistant', 'content' => 'Response'],
        ];

        $cleaned = ContextEditor::clearToolResults($messages);

        $this->assertEquals($messages, $cleaned);
    }

    public function testClearToolResultsOnlyAffectsUserMessages(): void
    {
        $messages = [
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello'],
                    ['type' => 'tool_result', 'tool_use_id' => '123', 'content' => 'Result'],
                ],
            ],
        ];

        $cleaned = ContextEditor::clearToolResults($messages);

        // Assistant messages should not be affected
        $this->assertCount(2, $cleaned[0]['content']);
    }

    public function testRemoveByRole(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => 'User message'],
            ['role' => 'assistant', 'content' => 'Assistant response'],
            ['role' => 'user', 'content' => 'Another user message'],
        ];

        $filtered = ContextEditor::removeByRole($messages, 'user');

        $this->assertCount(2, $filtered);
        $this->assertEquals('system', $filtered[0]['role']);
        $this->assertEquals('assistant', $filtered[2]['role']);
    }

    public function testRemoveByRoleNonExistent(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Message'],
        ];

        $filtered = ContextEditor::removeByRole($messages, 'system');

        $this->assertCount(1, $filtered);
    }

    public function testKeepRecentWithinLimit(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Message 1'],
            ['role' => 'assistant', 'content' => 'Response 1'],
            ['role' => 'user', 'content' => 'Message 2'],
        ];

        $recent = ContextEditor::keepRecent($messages, 10);

        $this->assertEquals($messages, $recent);
    }

    public function testKeepRecentExceedsLimit(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => 'Message 1'],
            ['role' => 'assistant', 'content' => 'Response 1'],
            ['role' => 'user', 'content' => 'Message 2'],
            ['role' => 'assistant', 'content' => 'Response 2'],
            ['role' => 'user', 'content' => 'Message 3'],
        ];

        $recent = ContextEditor::keepRecent($messages, 3);

        $this->assertCount(4, $recent); // First + 3 recent
        $this->assertEquals('system', $recent[0]['role']);
        $this->assertEquals('Message 2', $recent[1]['content']);
        $this->assertEquals('Message 3', $recent[3]['content']);
    }

    public function testSummarizeEarlyWithinLimit(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Message 1'],
            ['role' => 'assistant', 'content' => 'Response 1'],
        ];

        $summarized = ContextEditor::summarizeEarly($messages, 5);

        $this->assertEquals($messages, $summarized);
    }

    public function testSummarizeEarlyExceedsLimit(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => 'Message 1'],
            ['role' => 'assistant', 'content' => 'Response 1'],
            ['role' => 'user', 'content' => 'Message 2'],
            ['role' => 'assistant', 'content' => 'Response 2'],
            ['role' => 'user', 'content' => 'Message 3'],
            ['role' => 'assistant', 'content' => 'Response 3'],
            ['role' => 'user', 'content' => 'Message 4'],
        ];

        $summarized = ContextEditor::summarizeEarly($messages, 3);

        // Should have: system messages + summary + 3 recent
        $this->assertGreaterThan(3, count($summarized));

        // Check for summary message
        $hasSummary = false;
        foreach ($summarized as $msg) {
            if (str_contains($msg['content'] ?? '', 'summarized')) {
                $hasSummary = true;

                break;
            }
        }
        $this->assertTrue($hasSummary);
    }

    public function testExtractTextOnly(): void
    {
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'First part'],
                    ['type' => 'text', 'text' => 'Second part'],
                    ['type' => 'image', 'source' => 'image_data'],
                ],
            ],
            [
                'role' => 'assistant',
                'content' => 'Simple string',
            ],
        ];

        $textOnly = ContextEditor::extractTextOnly($messages);

        $this->assertIsString($textOnly[0]['content']);
        $this->assertEquals("First part\nSecond part", $textOnly[0]['content']);
        $this->assertEquals('Simple string', $textOnly[1]['content']);
    }

    public function testExtractTextOnlyPreservesStringContent(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Already a string'],
        ];

        $textOnly = ContextEditor::extractTextOnly($messages);

        $this->assertEquals($messages, $textOnly);
    }

    public function testGetStats(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => 'User message 1'],
            ['role' => 'assistant', 'content' => 'Assistant response 1'],
            ['role' => 'user', 'content' => 'User message 2'],
            ['role' => 'assistant', 'content' => 'Assistant response 2'],
        ];

        $stats = ContextEditor::getStats($messages);

        $this->assertEquals(5, $stats['total_messages']);
        $this->assertEquals(2, $stats['user_messages']);
        $this->assertEquals(2, $stats['assistant_messages']);
        $this->assertEquals(1, $stats['system_messages']);
        $this->assertGreaterThan(0, $stats['total_estimated_tokens']);
    }

    public function testGetStatsEmptyMessages(): void
    {
        $stats = ContextEditor::getStats([]);

        $this->assertEquals(0, $stats['total_messages']);
        $this->assertEquals(0, $stats['user_messages']);
        $this->assertEquals(0, $stats['assistant_messages']);
        $this->assertEquals(0, $stats['system_messages']);
        $this->assertEquals(0, $stats['total_estimated_tokens']);
    }

    public function testGetStatsWithUnknownRole(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Message'],
            ['role' => 'unknown', 'content' => 'Should not count'],
        ];

        $stats = ContextEditor::getStats($messages);

        $this->assertEquals(2, $stats['total_messages']);
        $this->assertEquals(1, $stats['user_messages']);
    }
}
