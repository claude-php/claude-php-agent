<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\QueryTransformation;

use ClaudePhp\ClaudePhp;

/**
 * Generates multiple query variations to improve retrieval.
 *
 * Creates different phrasings of the same question to retrieve
 * more diverse and comprehensive results.
 */
class MultiQueryGenerator
{
    /**
     * @param ClaudePhp $client Claude API client
     * @param int $numQueries Number of query variations to generate
     * @param string $model Model to use for generation
     */
    public function __construct(
        private readonly ClaudePhp $client,
        private readonly int $numQueries = 3,
        private readonly string $model = 'claude-3-haiku-20240307',
    ) {
    }

    /**
     * Generate multiple query variations.
     *
     * @return array<string> Array of query variations
     */
    public function generate(string $query): array
    {
        $prompt = <<<PROMPT
            Generate {$this->numQueries} different variations of the following query.
            Each variation should ask the same thing but use different wording, phrasing, or perspective.

            Original query: {$query}

            Respond with ONLY the query variations, one per line, numbered 1-{$this->numQueries}.
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
            $queries = [];

            foreach ($lines as $line) {
                // Remove numbering and clean up
                $line = preg_replace('/^\d+[\.\)]\s*/', '', trim($line));
                if (! empty($line) && $line !== $query) {
                    $queries[] = $line;
                }
            }

            // Always include original query
            array_unshift($queries, $query);

            return array_slice($queries, 0, $this->numQueries + 1);
        } catch (\Throwable $e) {
            // Fallback to original query on error
            return [$query];
        }
    }
}
