<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Reranking;

/**
 * Simple score-based re-ranker.
 *
 * Re-ranks based on multiple scoring factors like
 * keyword density, position, recency, etc.
 */
class ScoreReranker implements RerankerInterface
{
    /**
     * @param array<string, float> $weights Weights for different factors
     */
    public function __construct(
        private readonly array $weights = [
            'keyword_density' => 1.0,
            'exact_match' => 2.0,
            'title_match' => 1.5,
            'recency' => 0.5,
        ],
    ) {
    }

    public function rerank(string $query, array $documents, int $topK): array
    {
        if (empty($documents)) {
            return [];
        }

        $queryLower = strtolower($query);
        $queryWords = array_filter(
            preg_split('/\W+/', $queryLower, -1, PREG_SPLIT_NO_EMPTY),
            fn ($w) => strlen($w) > 2
        );

        $scored = [];

        foreach ($documents as $doc) {
            $score = 0.0;

            // Keyword density score
            if (! empty($queryWords)) {
                $text = strtolower($doc['text'] ?? '');
                $wordCount = str_word_count($text);
                $matches = 0;

                foreach ($queryWords as $word) {
                    $matches += substr_count($text, $word);
                }

                $density = $wordCount > 0 ? ($matches / $wordCount) : 0;
                $score += $density * ($this->weights['keyword_density'] ?? 1.0);
            }

            // Exact match score
            if (isset($doc['text']) && strpos(strtolower($doc['text']), $queryLower) !== false) {
                $score += $this->weights['exact_match'] ?? 2.0;
            }

            // Title match score
            if (isset($doc['source'])) {
                $titleLower = strtolower($doc['source']);
                foreach ($queryWords as $word) {
                    if (strpos($titleLower, $word) !== false) {
                        $score += $this->weights['title_match'] ?? 1.5;
                    }
                }
            }

            // Recency score (if timestamp available)
            if (isset($doc['metadata']['timestamp'])) {
                $age = time() - $doc['metadata']['timestamp'];
                $daysSinceCreated = $age / 86400;
                $recencyScore = max(0, 1 - ($daysSinceCreated / 365)); // Decay over a year
                $score += $recencyScore * ($this->weights['recency'] ?? 0.5);
            }

            $scored[] = [
                'document' => $doc,
                'score' => $score,
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
}
