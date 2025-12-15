<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation\Summarization;

use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Summarization\BasicConversationSummarizer;
use ClaudeAgents\Conversation\Turn;
use PHPUnit\Framework\TestCase;

class BasicConversationSummarizerTest extends TestCase
{
    private BasicConversationSummarizer $summarizer;
    private Session $session;

    protected function setUp(): void
    {
        $this->summarizer = new BasicConversationSummarizer();
        $this->session = new Session('test_session');

        $this->session->addTurn(new Turn(
            'I need help with my PHP application',
            'I would be happy to help you with PHP'
        ));
        $this->session->addTurn(new Turn(
            'How do I connect to a database?',
            'You can use PDO to connect to databases in PHP'
        ));
        $this->session->addTurn(new Turn(
            'What about error handling?',
            'PHP has exception handling with try-catch blocks'
        ));
    }

    public function test_summarizes_conversation(): void
    {
        $summary = $this->summarizer->summarize($this->session);

        $this->assertNotEmpty($summary);
        $this->assertStringContainsString('3 turn', $summary);
    }

    public function test_summarizes_empty_conversation(): void
    {
        $emptySession = new Session('empty');

        $summary = $this->summarizer->summarize($emptySession);

        $this->assertStringContainsString('Empty conversation', $summary);
    }

    public function test_summary_includes_turn_count(): void
    {
        $summary = $this->summarizer->summarize($this->session, [
            'include_turn_count' => true,
        ]);

        $this->assertStringContainsString('3 turn', $summary);
    }

    public function test_summary_excludes_turn_count(): void
    {
        $summary = $this->summarizer->summarize($this->session, [
            'include_turn_count' => false,
        ]);

        $this->assertStringNotContainsString('turn', $summary);
    }

    public function test_extracts_topics(): void
    {
        $topics = $this->summarizer->extractTopics($this->session, 3);

        $this->assertNotEmpty($topics);
        $this->assertIsArray($topics);
        $this->assertLessThanOrEqual(3, count($topics));
    }

    public function test_summarizes_turns(): void
    {
        $turnSummaries = $this->summarizer->summarizeTurns($this->session);

        $this->assertCount(3, $turnSummaries);
        $this->assertArrayHasKey('turn_id', $turnSummaries[0]);
        $this->assertArrayHasKey('summary', $turnSummaries[0]);
        $this->assertArrayHasKey('timestamp', $turnSummaries[0]);
    }

    public function test_truncates_long_summary(): void
    {
        $summary = $this->summarizer->summarize($this->session, [
            'max_length' => 50,
        ]);

        $this->assertLessThanOrEqual(50, strlen($summary));
    }

    public function test_respects_max_topics_limit(): void
    {
        $topics = $this->summarizer->extractTopics($this->session, 2);

        $this->assertLessThanOrEqual(2, count($topics));
    }

    public function test_turn_summaries_include_excerpts(): void
    {
        $turnSummaries = $this->summarizer->summarizeTurns($this->session);

        foreach ($turnSummaries as $summary) {
            $this->assertStringContainsString('User:', $summary['summary']);
            $this->assertStringContainsString('Agent:', $summary['summary']);
        }
    }
}
