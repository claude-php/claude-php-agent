<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Reranking;

use ClaudePhp\ClaudePhp;

/**
 * LLM-based document re-ranker.
 *
 * Uses Claude to score relevance of documents to query.
 */
class LLMReranker implements RerankerInterface
{
    /**
     * @param ClaudePhp $client Claude API client
     * @param string $model Model to use for re-ranking
     */
    public function __construct(
        private readonly ClaudePhp $client,
        private readonly string $model = 'claude-3-haiku-20240307',
    ) {
    }

    public function rerank(string $query, array $documents, int $topK): array
    {
        if (empty($documents)) {
            return [];
        }

        $scored = [];

        foreach ($documents as $index => $doc) {
            $text = $doc['text'] ?? '';
            $score = $this->scoreRelevance($query, $text);

            $scored[] = [
                'document' => $doc,
                'score' => $score,
                'original_index' => $index,
            ];
        }

        // Sort by score descending
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Return top K
        return array_slice(
            array_map(fn ($x) => $x['document'], $scored),
            0,
            $topK
        );
    }

    /**
     * Score relevance of document to query.
     *
     * Returns a score from 0-10.
     */
    private function scoreRelevance(string $query, string $document): float
    {
        $prompt = <<<PROMPT
            Rate the relevance of the following document to the query on a scale of 0-10, where:
            - 0 = completely irrelevant
            - 5 = somewhat relevant
            - 10 = highly relevant and directly answers the query

            Query: {$query}

            Document: {$document}

            Respond with ONLY a number between 0 and 10, nothing else.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => 10,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $content = $response->content[0] ?? null;
            if ($content === null || ! isset($content['text'])) {
                return 5.0; // Default score if parsing fails
            }

            $text = trim($content['text']);
            $score = (float) $text;

            // Clamp to 0-10 range
            return max(0.0, min(10.0, $score));
        } catch (\Throwable $e) {
            // Return neutral score on error
            return 5.0;
        }
    }
}
