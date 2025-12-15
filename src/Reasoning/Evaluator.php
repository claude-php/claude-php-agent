<?php

declare(strict_types=1);

namespace ClaudeAgents\Reasoning;

use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Evaluates quality of thought branches.
 */
class Evaluator
{
    private LoggerInterface $logger;

    /**
     * @param ClaudePhp $client Claude API client
     * @param string $problem The problem being solved
     * @param string $criteria Evaluation criteria
     * @param array<string, mixed> $options Configuration
     */
    public function __construct(
        private readonly ClaudePhp $client,
        private readonly string $problem,
        private readonly string $criteria = 'likelihood of success and efficiency',
        array $options = [],
    ) {
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Evaluate a thought/approach.
     *
     * @param string $thought The thought to evaluate
     * @return float Score from 0.0 to 10.0
     */
    public function evaluate(string $thought): float
    {
        try {
            $prompt = "Problem: {$this->problem}\n\n" .
                     "Proposed approach: {$thought}\n\n" .
                     "Evaluate this approach on a scale of 1-10, considering:\n" .
                     "- {$this->criteria}\n\n" .
                     'Respond with ONLY a number from 1-10, no other text.';

            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 100,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $text = $this->extractTextContent($response->content ?? []);
            $score = (float) preg_replace('/[^0-9.]/', '', $text);

            return max(0.0, min(10.0, $score));
        } catch (\Throwable $e) {
            $this->logger->warning("Evaluation failed: {$e->getMessage()}");

            return 5.0; // Default middle score on error
        }
    }

    /**
     * Evaluate multiple thoughts and return scores.
     *
     * @param array<string> $thoughts Array of thoughts to evaluate
     * @return array<string, float> Map of thought to score
     */
    public function evaluateMultiple(array $thoughts): array
    {
        $scores = [];

        foreach ($thoughts as $thought) {
            $scores[$thought] = $this->evaluate($thought);
        }

        return $scores;
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
