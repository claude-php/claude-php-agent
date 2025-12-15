<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory;

use ClaudeAgents\Memory\ConversationMemory;
use PHPUnit\Framework\TestCase;

class ConversationMemoryTest extends TestCase
{
    private ConversationMemory $memory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memory = new ConversationMemory(maxMessages: 3);
    }

    public function testAddMessage(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Hello']);

        $this->assertEquals(1, $this->memory->count());
    }

    public function testAddMany(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Hello'],
            ['role' => 'user', 'content' => 'How are you?'],
        ];

        $this->memory->addMany($messages);

        $this->assertEquals(3, $this->memory->count());
    }

    public function testGetMessages(): void
    {
        $message = ['role' => 'user', 'content' => 'Test'];
        $this->memory->add($message);

        $messages = $this->memory->getMessages();

        $this->assertCount(1, $messages);
        $this->assertEquals($message, $messages[0]);
    }

    public function testGetLastMessages(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->memory->add(['role' => 'user', 'content' => "Message $i"]);
        }

        $last = $this->memory->getLastMessages(2);

        $this->assertCount(2, $last);
        $this->assertStringContainsString('Message 4', $last[0]['content']);
        $this->assertStringContainsString('Message 5', $last[1]['content']);
    }

    public function testClear(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);
        $this->memory->clear();

        $this->assertEquals(0, $this->memory->count());
        $this->assertEquals(0, $this->memory->getEstimatedTokens());
    }

    public function testTokenEstimation(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'This is a test message']);

        $tokens = $this->memory->getEstimatedTokens();

        $this->assertGreaterThan(0, $tokens);
    }

    public function testTrimsMessages(): void
    {
        // Add more than maxMessages pairs
        for ($i = 1; $i <= 8; $i++) {
            $this->memory->add(['role' => 'user', 'content' => "User $i"]);
            $this->memory->add(['role' => 'assistant', 'content' => "Assistant $i"]);
        }

        // Should keep only last 3 pairs = 6 messages
        $this->assertLessThanOrEqual(6, $this->memory->count());
    }

    public function testSummarize(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Hello there']);
        $this->memory->add(['role' => 'assistant', 'content' => 'Hi! How can I help?']);

        $summary = $this->memory->summarize();

        $this->assertStringContainsString('[user]:', $summary);
        $this->assertStringContainsString('[assistant]:', $summary);
        $this->assertStringContainsString('Hello there', $summary);
    }

    public function testSummarizeTruncatesLongContent(): void
    {
        $longContent = str_repeat('This is a very long message. ', 20);
        $this->memory->add(['role' => 'user', 'content' => $longContent]);

        $summary = $this->memory->summarize();

        $this->assertStringContainsString('...', $summary);
        $this->assertLessThan(strlen($longContent), strlen($summary));
    }

    public function testHandlesArrayContent(): void
    {
        $this->memory->add([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => 'Hello'],
                ['type' => 'tool_use', 'name' => 'calculator'],
            ],
        ]);

        $tokens = $this->memory->getEstimatedTokens();
        $this->assertGreaterThan(0, $tokens);

        $summary = $this->memory->summarize();
        $this->assertStringContainsString('text, tool_use', $summary);
    }
}
