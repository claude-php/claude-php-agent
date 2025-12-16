<?php

declare(strict_types=1);

namespace ClaudeAgents\ML;

/**
 * K-Nearest Neighbors Matcher for vector similarity search.
 *
 * This utility provides k-NN functionality for finding similar items
 * in a vector space using various distance metrics.
 *
 * @package ClaudeAgents\ML
 */
class KNNMatcher
{
    /**
     * Find k nearest neighbors to a query vector.
     *
     * @param array<float> $queryVector The query vector
     * @param array<array{id: string, vector: array<float>, metadata?: array}> $candidates Candidate vectors with IDs
     * @param int $k Number of nearest neighbors to return
     * @param string $metric Distance metric: 'cosine', 'euclidean', 'manhattan'
     * @param array<string, mixed> $options Additional options:
     *   - min_similarity: Minimum similarity threshold (0-1) for cosine
     *   - max_distance: Maximum distance threshold for euclidean/manhattan
     *   - weights: Optional weights for temporal decay or importance
     * @return array<array{id: string, distance: float, similarity: float, metadata?: array}>
     */
    public function findNearest(
        array $queryVector,
        array $candidates,
        int $k = 5,
        string $metric = 'cosine',
        array $options = []
    ): array {
        if (empty($candidates)) {
            return [];
        }

        $scored = [];

        foreach ($candidates as $candidate) {
            $candidateVector = $candidate['vector'] ?? [];

            if (empty($candidateVector)) {
                continue;
            }

            // Calculate distance/similarity
            $result = match ($metric) {
                'cosine' => [
                    'distance' => 1 - $this->cosineSimilarity($queryVector, $candidateVector),
                    'similarity' => $this->cosineSimilarity($queryVector, $candidateVector),
                ],
                'euclidean' => [
                    'distance' => $this->euclideanDistance($queryVector, $candidateVector),
                    'similarity' => 1 / (1 + $this->euclideanDistance($queryVector, $candidateVector)),
                ],
                'manhattan' => [
                    'distance' => $this->manhattanDistance($queryVector, $candidateVector),
                    'similarity' => 1 / (1 + $this->manhattanDistance($queryVector, $candidateVector)),
                ],
                default => [
                    'distance' => 1 - $this->cosineSimilarity($queryVector, $candidateVector),
                    'similarity' => $this->cosineSimilarity($queryVector, $candidateVector),
                ],
            };

            // Apply optional weighting (e.g., temporal decay)
            if (isset($options['weights'][$candidate['id']])) {
                $weight = $options['weights'][$candidate['id']];
                $result['similarity'] *= $weight;
                $result['distance'] *= (2 - $weight); // inverse for distance
            }

            // Apply filters
            if (isset($options['min_similarity']) && $result['similarity'] < $options['min_similarity']) {
                continue;
            }

            if (isset($options['max_distance']) && $result['distance'] > $options['max_distance']) {
                continue;
            }

            $scored[] = [
                'id' => $candidate['id'],
                'distance' => $result['distance'],
                'similarity' => $result['similarity'],
                'metadata' => $candidate['metadata'] ?? [],
            ];
        }

        // Sort by similarity (descending) or distance (ascending)
        usort($scored, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Return top k
        return array_slice($scored, 0, $k);
    }

    /**
     * Calculate cosine similarity between two vectors.
     *
     * @param array<float> $a First vector
     * @param array<float> $b Second vector
     * @return float Similarity score (0-1, where 1 is identical)
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Calculate Euclidean distance between two vectors.
     *
     * @param array<float> $a First vector
     * @param array<float> $b Second vector
     * @return float Distance (0 = identical, higher = more different)
     */
    public function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return PHP_FLOAT_MAX;
        }

        $sum = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Calculate Manhattan distance between two vectors.
     *
     * @param array<float> $a First vector
     * @param array<float> $b Second vector
     * @return float Distance (0 = identical, higher = more different)
     */
    public function manhattanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return PHP_FLOAT_MAX;
        }

        $sum = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $sum += abs($a[$i] - $b[$i]);
        }

        return $sum;
    }

    /**
     * Calculate weighted Euclidean distance with feature importance.
     *
     * @param array<float> $a First vector
     * @param array<float> $b Second vector
     * @param array<float> $weights Feature weights (same dimension as vectors)
     * @return float Weighted distance
     */
    public function weightedEuclideanDistance(array $a, array $b, array $weights): float
    {
        if (count($a) !== count($b) || count($a) !== count($weights) || count($a) === 0) {
            return PHP_FLOAT_MAX;
        }

        $sum = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $weights[$i] * $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Calculate temporal weight for recency-based relevance.
     *
     * @param int $timestamp Unix timestamp of the event
     * @param float $halfLife Half-life in days (time for weight to decay to 0.5)
     * @return float Weight between 0 and 1
     */
    public function temporalWeight(int $timestamp, float $halfLife = 30.0): float
    {
        $ageInDays = (time() - $timestamp) / 86400;
        return exp(-log(2) * $ageInDays / $halfLife);
    }

    /**
     * Normalize a vector to unit length.
     *
     * @param array<float> $vector Input vector
     * @return array<float> Normalized vector
     */
    public function normalize(array $vector): array
    {
        $norm = 0.0;

        foreach ($vector as $value) {
            $norm += $value * $value;
        }

        $norm = sqrt($norm);

        if ($norm === 0.0) {
            return $vector;
        }

        return array_map(fn ($v) => $v / $norm, $vector);
    }

    /**
     * Create a weighted query vector based on feature importance.
     *
     * @param array<float> $vector Input vector
     * @param array<float> $weights Feature weights
     * @return array<float> Weighted vector
     */
    public function weightVector(array $vector, array $weights): array
    {
        if (count($vector) !== count($weights)) {
            return $vector;
        }

        $weighted = [];
        for ($i = 0; $i < count($vector); $i++) {
            $weighted[] = $vector[$i] * $weights[$i];
        }

        return $weighted;
    }
}

