<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory;

use ClaudeAgents\Memory\ConversationSummaryMemory;
use ClaudeAgents\Memory\Summarizers\SummarizerInterface;
use PHPUnit\Framework\TestCase;

class ConversationSummaryMemoryTest extends TestCase
{
    private SummarizerInterface $summarizer;
    private ConversationSummaryMemory $memory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->summarizer = $this->createMock(SummarizerInterface::class);
        $this->memory = new ConversationSummaryMemory(
            $this->summarizer,
            summaryThreshold: 5,
            keepRecentCount: 2
        );
    }

    public function testConstructorValidatesThreshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ConversationSummaryMemory($this->summarizer, summaryThreshold: 0);
    }

    public function testConstructorValidatesKeepRecentCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ConversationSummaryMemory($this->summarizer, summaryThreshold: 5, keepRecentCount: -1);
    }

    public function testAddMessage(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Hello']);

        $this->assertEquals(1, $this->memory->count());
    }

    public function testSummarizesWhenThresholdExceeded(): void
    {
        $this->summarizer->expects($this->once())
            ->method('summarize')
            ->willReturn('Summary of conversation');

        // Add messages up to threshold + 1
        for ($i = 0; $i <= 5; $i++) {
            $this->memory->add(['role' => 'user', 'content' => "Message {$i}"]);
        }

        $this->assertTrue($this->memory->hasSummary());
        $this->assertEquals('Summary of conversation', $this->memory->getSummary());
    }

    public function testKeepsRecentMessages(): void
    {
        $this->summarizer->method('summarize')
            ->willReturn('Summary');

        for ($i = 0; $i <= 5; $i++) {
            $this->memory->add(['role' => 'user', 'content' => "Message {$i}"]);
        }

        // Should keep only last 2 messages
        $this->assertEquals(2, $this->memory->count());
    }

    public function testGetContext(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);

        $context = $this->memory->getContext();

        $this->assertIsArray($context);
        $this->assertNotEmpty($context);
    }

    public function testGetContextIncludesSummary(): void
    {
        $this->summarizer->method('summarize')
            ->willReturn('This is the summary');

        for ($i = 0; $i <= 5; $i++) {
            $this->memory->add(['role' => 'user', 'content' => "Message {$i}"]);
        }

        $context = $this->memory->getContext();

        // First message should be the summary
        $this->assertStringContainsString('This is the summary', $context[0]['content']);
    }

    public function testGetRecentMessages(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Recent']);

        $recent = $this->memory->getRecentMessages();

        $this->assertCount(1, $recent);
        $this->assertEquals('Recent', $recent[0]['content']);
    }

    public function testClear(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);
        $this->memory->clear();

        $this->assertEquals(0, $this->memory->count());
        $this->assertEmpty($this->memory->getSummary());
    }

    public function testHasSummary(): void
    {
        $this->assertFalse($this->memory->hasSummary());

        $this->summarizer->method('summarize')
            ->willReturn('Summary');

        for ($i = 0; $i <= 5; $i++) {
            $this->memory->add(['role' => 'user', 'content' => "Message {$i}"]);
        }

        $this->assertTrue($this->memory->hasSummary());
    }

    public function testForceSummarize(): void
    {
        $this->summarizer->expects($this->once())
            ->method('summarize')
            ->willReturn('Forced summary');

        // Add more messages than keepRecentCount (2) to have something to summarize
        $this->memory->add(['role' => 'user', 'content' => 'Test 1']);
        $this->memory->add(['role' => 'assistant', 'content' => 'Response 1']);
        $this->memory->add(['role' => 'user', 'content' => 'Test 2']);
        $this->memory->forceSummarize();

        $this->assertTrue($this->memory->hasSummary());
    }

    public function testAddMany(): void
    {
        $messages = [
            ['role' => 'user', 'content' => '1'],
            ['role' => 'assistant', 'content' => '2'],
        ];

        $this->memory->addMany($messages);

        $this->assertEquals(2, $this->memory->count());
    }
}
