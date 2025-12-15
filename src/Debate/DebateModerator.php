<?php

declare(strict_types=1);

namespace ClaudeAgents\Debate;

use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Moderates debates and synthesizes conclusions.
 */
class DebateModerator
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ClaudePhp $client,
        array $options = [],
    ) {
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Synthesize debate into a balanced conclusion.
     *
     * @param string $topic The debate topic
     * @param array<DebateRound> $rounds All debate rounds
     * @return string The synthesized conclusion
     */
    public function synthesize(string $topic, array $rounds): string
    {
        $this->logger->info("Moderator synthesizing debate on: {$topic}");

        // Build debate transcript
        $transcript = '';
        foreach ($rounds as $round) {
            $transcript .= '=== Round ' . $round->getRoundNumber() . " ===\n";
            foreach ($round->getStatements() as $agent => $statement) {
                $transcript .= "\n{$agent}:\n{$statement}\n";
            }
            $transcript .= "\n";
        }

        $prompt = "Topic: {$topic}\n\n" .
                 "Debate transcript:\n{$transcript}\n\n" .
                 "Synthesize this debate into a balanced conclusion:\n" .
                 "1. Key areas of agreement\n" .
                 "2. Valid concerns from all sides\n" .
                 "3. Recommended decision with rationale\n" .
                 '4. Potential risks and mitigations';

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 2048,
                'system' => 'You synthesize multi-agent debates into clear, balanced conclusions.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            return $this->extractTextContent($response->content ?? []);
        } catch (\Throwable $e) {
            $this->logger->error("Synthesis failed: {$e->getMessage()}");

            return "Synthesis error: {$e->getMessage()}";
        }
    }

    /**
     * Measure agreement level across statements.
     *
     * @param array<string> $statements Array of statement texts
     * @return float Agreement score from 0.0 to 1.0
     */
    public function measureAgreement(array $statements): float
    {
        $agreementWords = ['agree', 'correct', 'yes', 'indeed', 'support', 'affirm', 'valid', 'point'];
        $disagreementWords = ['disagree', 'however', 'but', 'concern', 'risk', 'problem', 'issue', 'however'];

        $agreementCount = 0;
        $disagreementCount = 0;

        foreach ($statements as $statement) {
            $lower = strtolower($statement);
            foreach ($agreementWords as $word) {
                $agreementCount += substr_count($lower, $word);
            }
            foreach ($disagreementWords as $word) {
                $disagreementCount += substr_count($lower, $word);
            }
        }

        $total = $agreementCount + $disagreementCount;

        return $total > 0 ? $agreementCount / $total : 0.5;
    }

    /**
     * Extract text content from response blocks.
     *
     * @param array<mixed> $content
     */
    private function extractTextContent(array $content): string
    {
        $texts = [];

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }
}
