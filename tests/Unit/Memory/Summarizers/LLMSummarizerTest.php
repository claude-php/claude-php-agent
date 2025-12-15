<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory\Summarizers;

use ClaudeAgents\Memory\Summarizers\LLMSummarizer;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class LLMSummarizerTest extends TestCase
{
    private ClaudePhp $client;
    private LLMSummarizer $summarizer;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no API key (for CI environments)
        if (! getenv('ANTHROPIC_API_KEY')) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $this->client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
        $this->summarizer = new LLMSummarizer($this->client, [
            'max_tokens' => 100,
            'focus' => 'key_points',
        ]);
    }

    public function testGetMaxLength(): void
    {
        $this->assertEquals(100, $this->summarizer->getMaxLength());
    }

    public function testSummarizeMessages(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'What is the capital of France?'],
            ['role' => 'assistant', 'content' => 'The capital of France is Paris.'],
        ];

        $summary = $this->summarizer->summarizeMessages($messages);

        $this->assertIsString($summary);
        $this->assertNotEmpty($summary);
        $this->assertStringContainsString('Paris', $summary);
    }

    public function testSummarizeWithExistingSummary(): void
    {
        $existingSummary = 'Previous topic: Geography of Europe';
        $messages = [
            ['role' => 'user', 'content' => 'What about Germany?'],
            ['role' => 'assistant', 'content' => 'The capital of Germany is Berlin.'],
        ];

        $summary = $this->summarizer->summarize($messages, $existingSummary);

        $this->assertIsString($summary);
        $this->assertNotEmpty($summary);
    }

    public function testHandlesEmptyMessages(): void
    {
        $summary = $this->summarizer->summarizeMessages([]);

        $this->assertIsString($summary);
    }

    public function testHandlesArrayContent(): void
    {
        $messages = [
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Paris is the capital.'],
                    ['type' => 'tool_use', 'name' => 'search'],
                ],
            ],
        ];

        $summary = $this->summarizer->summarizeMessages($messages);

        $this->assertIsString($summary);
    }
}
