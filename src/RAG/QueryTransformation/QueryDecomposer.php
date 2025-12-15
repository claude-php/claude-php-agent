<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\QueryTransformation;

use ClaudePhp\ClaudePhp;

/**
 * Decomposes complex queries into simpler sub-queries.
 *
 * Breaks down multi-part questions into individual components
 * that can be answered separately and then combined.
 */
class QueryDecomposer
{
    /**
     * @param ClaudePhp $client Claude API client
     * @param string $model Model to use for decomposition
     */
    public function __construct(
        private readonly ClaudePhp $client,
        private readonly string $model = 'claude-3-haiku-20240307',
    ) {
    }

    /**
     * Decompose a complex query into sub-queries.
     *
     * @return array<string> Array of sub-queries
     */
    public function decompose(string $query): array
    {
        $prompt = <<<PROMPT
            Break down the following complex question into simpler sub-questions that can be
            answered independently. Each sub-question should be self-contained.

            Complex question: {$query}

            List each sub-question on a new line, numbered 1, 2, 3, etc.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => 500,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $content = $response->content[0] ?? null;
            if ($content === null || ! isset($content['text'])) {
                return [$query];
            }

            $text = $content['text'];
            $lines = explode("\n", $text);
            $subQueries = [];

            foreach ($lines as $line) {
                // Remove numbering and clean up
                $line = preg_replace('/^\d+[\.\)]\s*/', '', trim($line));
                if (! empty($line)) {
                    $subQueries[] = $line;
                }
            }

            // Return original if decomposition failed
            return ! empty($subQueries) ? $subQueries : [$query];
        } catch (\Throwable $e) {
            // Fallback to original query on error
            return [$query];
        }
    }

    /**
     * Check if a query should be decomposed.
     *
     * Returns true if query appears to be complex/multi-part.
     */
    public function shouldDecompose(string $query): bool
    {
        // Simple heuristics for detecting complex queries
        $indicators = [
            'and',
            'also',
            'as well as',
            'compare',
            'difference between',
            'both',
            'each',
            'multiple',
        ];

        $queryLower = strtolower($query);

        foreach ($indicators as $indicator) {
            if (strpos($queryLower, $indicator) !== false) {
                return true;
            }
        }

        // Check for multiple question marks or long queries
        return substr_count($query, '?') > 1 || str_word_count($query) > 20;
    }
}
