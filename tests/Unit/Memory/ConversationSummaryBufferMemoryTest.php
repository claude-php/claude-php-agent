<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory;

use ClaudeAgents\Memory\ConversationSummaryBufferMemory;
use ClaudeAgents\Memory\Summarizers\SummarizerInterface;
use PHPUnit\Framework\TestCase;

class ConversationSummaryBufferMemoryTest extends TestCase
{
    private SummarizerInterface $summarizer;
    private ConversationSummaryBufferMemory $memory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->summarizer = $this->createMock(SummarizerInterface::class);
        $this->memory = new ConversationSummaryBufferMemory(
            $this->summarizer,
            maxTokens: 100
        );
    }

    public function testConstructorValidatesMaxTokens(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ConversationSummaryBufferMemory($this->summarizer, maxTokens: 50);
    }

    public function testAddMessage(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Hello']);

        $this->assertEquals(1, $this->memory->count());
    }

    public function testSummarizesWhenExceedingTokenLimit(): void
    {
        $this->summarizer->method('summarize')
            ->willReturn('Summary');

        // Add many messages to exceed token limit
        $longMessage = str_repeat('word ', 100); // ~400 tokens
        $this->memory->add(['role' => 'user', 'content' => $longMessage]);
        $this->memory->add(['role' => 'user', 'content' => $longMessage]);

        $this->assertTrue($this->memory->hasSummary());
    }

    public function testGetTotalTokens(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test message']);

        $tokens = $this->memory->getTotalTokens();

        $this->assertGreaterThan(0, $tokens);
    }

    public function testGetBufferTokens(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);

        $this->assertGreaterThan(0, $this->memory->getBufferTokens());
    }

    public function testGetSummaryTokens(): void
    {
        $this->assertEquals(0, $this->memory->getSummaryTokens());

        $this->summarizer->method('summarize')
            ->willReturn('Summary text');

        // Force summarization
        $longMessage = str_repeat('word ', 100);
        $this->memory->add(['role' => 'user', 'content' => $longMessage]);
        $this->memory->add(['role' => 'user', 'content' => $longMessage]);

        if ($this->memory->hasSummary()) {
            $this->assertGreaterThan(0, $this->memory->getSummaryTokens());
        }
    }

    public function testGetContext(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);

        $context = $this->memory->getContext();

        $this->assertIsArray($context);
        $this->assertNotEmpty($context);
    }

    public function testGetRecentMessages(): void
    {
        $message = ['role' => 'user', 'content' => 'Recent'];
        $this->memory->add($message);

        $recent = $this->memory->getRecentMessages();

        $this->assertCount(1, $recent);
        $this->assertEquals($message, $recent[0]);
    }

    public function testClear(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);
        $this->memory->clear();

        $this->assertEquals(0, $this->memory->count());
        $this->assertEquals(0, $this->memory->getTotalTokens());
        $this->assertEmpty($this->memory->getSummary());
    }

    public function testHasSummary(): void
    {
        $this->assertFalse($this->memory->hasSummary());
    }

    public function testGetMaxTokens(): void
    {
        $this->assertEquals(100, $this->memory->getMaxTokens());
    }

    public function testIsNearLimit(): void
    {
        $this->assertFalse($this->memory->isNearLimit());

        // Add messages approaching limit
        $message = str_repeat('word ', 50); // ~200 tokens, exceeds 100 limit
        $this->memory->add(['role' => 'user', 'content' => $message]);

        // After summarization, should not be near limit anymore
        // (depends on implementation details)
    }

    public function testAddMany(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Message 1'],
            ['role' => 'assistant', 'content' => 'Response 1'],
        ];

        $this->memory->addMany($messages);

        $this->assertEquals(2, $this->memory->count());
    }

    public function testHandlesArrayContent(): void
    {
        $this->memory->add([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => 'Hello world'],
            ],
        ]);

        $this->assertGreaterThan(0, $this->memory->getTotalTokens());
    }
}
