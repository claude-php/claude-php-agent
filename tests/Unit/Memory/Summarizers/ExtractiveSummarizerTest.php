<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory\Summarizers;

use ClaudeAgents\Memory\Summarizers\ExtractiveSummarizer;
use PHPUnit\Framework\TestCase;

class ExtractiveSummarizerTest extends TestCase
{
    private ExtractiveSummarizer $summarizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->summarizer = new ExtractiveSummarizer([
            'max_sentences' => 3,
            'max_tokens' => 100,
        ]);
    }

    public function testGetMaxLength(): void
    {
        $this->assertEquals(100, $this->summarizer->getMaxLength());
    }

    public function testSummarizeMessages(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Tell me about Paris. I want to visit France.'],
            ['role' => 'assistant', 'content' => 'Paris is the capital of France. It is known for the Eiffel Tower. The city has many museums and cafes.'],
        ];

        $summary = $this->summarizer->summarizeMessages($messages);

        $this->assertIsString($summary);
        $this->assertNotEmpty($summary);
    }

    public function testHandlesEmptyMessages(): void
    {
        $summary = $this->summarizer->summarizeMessages([]);

        $this->assertIsString($summary);
        $this->assertEmpty($summary);
    }

    public function testPreservesExistingSummary(): void
    {
        $existing = 'Previous context.';
        $messages = [
            ['role' => 'user', 'content' => 'New information about Paris and France.'],
        ];

        $summary = $this->summarizer->summarize($messages, $existing);

        $this->assertStringContainsString($existing, $summary);
    }

    public function testSelectsImportantSentences(): void
    {
        $messages = [
            [
                'role' => 'assistant',
                'content' => 'Paris is the capital. It has cafes. The Eiffel Tower is famous. People drink coffee. Museums are numerous. Art is everywhere.',
            ],
        ];

        $summary = $this->summarizer->summarizeMessages($messages);

        // Should select max 3 sentences
        $sentenceCount = substr_count($summary, '.');
        $this->assertLessThanOrEqual(3, $sentenceCount);
    }

    public function testHandlesArrayContent(): void
    {
        $messages = [
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Paris is the capital of France.'],
                ],
            ],
        ];

        $summary = $this->summarizer->summarizeMessages($messages);

        $this->assertIsString($summary);
        $this->assertNotEmpty($summary);
    }
}
