<?php

declare(strict_types=1);

namespace ClaudeAgents\ML;

/**
 * Task Embedder - Converts task analysis into feature vectors.
 *
 * Creates numerical feature representations of tasks for k-NN matching
 * and similarity-based agent selection.
 *
 * @package ClaudeAgents\ML
 */
class TaskEmbedder
{
    private const COMPLEXITY_MAP = [
        'simple' => 0.25,
        'medium' => 0.50,
        'complex' => 0.75,
        'extreme' => 1.00,
    ];

    private const DOMAIN_MAP = [
        'general' => 0,
        'technical' => 1,
        'creative' => 2,
        'analytical' => 3,
        'conversational' => 4,
        'monitoring' => 5,
    ];

    private const QUALITY_MAP = [
        'standard' => 0.33,
        'high' => 0.66,
        'extreme' => 1.00,
    ];

    /**
     * Convert task analysis to a feature vector.
     *
     * Creates a normalized vector representation suitable for k-NN matching.
     *
     * @param array $taskAnalysis Task analysis from AdaptiveAgentService
     * @return array<float> Feature vector
     */
    public function embed(array $taskAnalysis): array
    {
        $features = [];

        // 1. Complexity (1 dimension)
        $features[] = self::COMPLEXITY_MAP[$taskAnalysis['complexity'] ?? 'medium'] ?? 0.5;

        // 2. Domain (one-hot encoded, 6 dimensions)
        $domain = $taskAnalysis['domain'] ?? 'general';
        $domainIndex = self::DOMAIN_MAP[$domain] ?? 0;
        for ($i = 0; $i < 6; $i++) {
            $features[] = ($i === $domainIndex) ? 1.0 : 0.0;
        }

        // 3. Binary flags (5 dimensions)
        $features[] = ($taskAnalysis['requires_tools'] ?? false) ? 1.0 : 0.0;
        $features[] = ($taskAnalysis['requires_knowledge'] ?? false) ? 1.0 : 0.0;
        $features[] = ($taskAnalysis['requires_reasoning'] ?? false) ? 1.0 : 0.0;
        $features[] = ($taskAnalysis['requires_iteration'] ?? false) ? 1.0 : 0.0;

        // 4. Quality requirement (1 dimension)
        $quality = $taskAnalysis['requires_quality'] ?? 'standard';
        $features[] = self::QUALITY_MAP[$quality] ?? 0.33;

        // 5. Estimated steps (normalized, 1 dimension)
        $steps = $taskAnalysis['estimated_steps'] ?? 10;
        $features[] = min(1.0, $steps / 50.0); // normalize to 0-1, cap at 50 steps

        // 6. Key requirements count (normalized, 1 dimension)
        $reqCount = count($taskAnalysis['key_requirements'] ?? []);
        $features[] = min(1.0, $reqCount / 10.0); // normalize to 0-1, cap at 10 requirements

        // Total: 16 dimensions
        // [complexity, domain×6, flags×4, quality, steps, req_count]

        return $features;
    }

    /**
     * Create a weighted embedding emphasizing certain features.
     *
     * @param array $taskAnalysis Task analysis
     * @param array<string, float> $weights Feature weights
     * @return array<float> Weighted feature vector
     */
    public function embedWeighted(array $taskAnalysis, array $weights = []): array
    {
        $features = $this->embed($taskAnalysis);
        
        $defaultWeights = [
            'complexity' => 1.0,
            'domain' => 1.0,
            'tools' => 1.2,      // slightly more important
            'knowledge' => 1.1,
            'reasoning' => 1.2,
            'iteration' => 0.9,
            'quality' => 1.3,    // very important
            'steps' => 0.8,      // less important
            'requirements' => 0.9,
        ];

        $weights = array_merge($defaultWeights, $weights);

        // Apply weights to corresponding features
        $weighted = [];
        $weighted[] = $features[0] * $weights['complexity'];
        
        // Domain (6 features)
        for ($i = 1; $i <= 6; $i++) {
            $weighted[] = $features[$i] * $weights['domain'];
        }
        
        // Binary flags
        $weighted[] = $features[7] * $weights['tools'];
        $weighted[] = $features[8] * $weights['knowledge'];
        $weighted[] = $features[9] * $weights['reasoning'];
        $weighted[] = $features[10] * $weights['iteration'];
        
        // Quality, steps, requirements
        $weighted[] = $features[11] * $weights['quality'];
        $weighted[] = $features[12] * $weights['steps'];
        $weighted[] = $features[13] * $weights['requirements'];

        return $weighted;
    }

    /**
     * Get feature names for debugging/visualization.
     *
     * @return array<string> Feature names
     */
    public function getFeatureNames(): array
    {
        return [
            'complexity',
            'domain_general',
            'domain_technical',
            'domain_creative',
            'domain_analytical',
            'domain_conversational',
            'domain_monitoring',
            'requires_tools',
            'requires_knowledge',
            'requires_reasoning',
            'requires_iteration',
            'requires_quality',
            'estimated_steps_norm',
            'key_requirements_count',
        ];
    }

    /**
     * Calculate feature importance based on variance in historical data.
     *
     * @param array<array<float>> $historicalVectors Historical feature vectors
     * @return array<float> Feature importance weights (higher = more discriminative)
     */
    public function calculateFeatureImportance(array $historicalVectors): array
    {
        if (empty($historicalVectors)) {
            return array_fill(0, 14, 1.0);
        }

        $dimensions = count($historicalVectors[0]);
        $importance = [];

        for ($dim = 0; $dim < $dimensions; $dim++) {
            $values = array_column($historicalVectors, $dim);
            
            // Calculate variance (features with more variance are more discriminative)
            $mean = array_sum($values) / count($values);
            $variance = 0.0;
            
            foreach ($values as $value) {
                $variance += ($value - $mean) ** 2;
            }
            
            $variance /= count($values);
            
            // Convert variance to importance weight (add 0.5 to avoid zero weights)
            $importance[] = $variance + 0.5;
        }

        // Normalize weights
        $sum = array_sum($importance);
        if ($sum > 0) {
            $importance = array_map(fn ($w) => $w / $sum * $dimensions, $importance);
        }

        return $importance;
    }

    /**
     * Get the dimensionality of the embedding space.
     *
     * @return int Number of dimensions
     */
    public function getDimensions(): int
    {
        return 14;
    }
}

