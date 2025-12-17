<?php

declare(strict_types=1);

namespace ClaudeAgents\ML;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * MetaLearning - Learn how to learn more effectively.
 *
 * Implements meta-learning strategies to optimize the learning process itself,
 * enabling agents to adapt quickly to new tasks with minimal examples.
 *
 * **Key Capabilities:**
 * - Few-shot learning: Learn from minimal examples
 * - Learning rate optimization: Adapt learning speed
 * - Algorithm selection: Choose best learning algorithm per task type
 * - Hyperparameter optimization: Tune learning parameters
 *
 * **Benefits:**
 * - Rapid adaptation to new tasks
 * - Improved sample efficiency
 * - Automatic algorithm selection
 * - Self-optimizing learning process
 *
 * @package ClaudeAgents\ML
 */
class MetaLearning
{
    private TaskHistoryStore $historyStore;
    private TaskEmbedder $embedder;
    private KNNMatcher $knnMatcher;
    private LoggerInterface $logger;
    private array $learningStrategies = [];
    private array $hyperparameters = [];

    /**
     * @param array<string, mixed> $options Configuration:
     *   - history_store_path: Path to meta-learning history
     *   - embedder: TaskEmbedder instance
     *   - client: ClaudePhp client (for embedder)
     *   - default_learning_rate: Default learning rate (default: 0.01)
     *   - adaptation_window: Window for adaptation (default: 5)
     *   - logger: PSR-3 logger
     */
    public function __construct(array $options = [])
    {
        $this->logger = $options['logger'] ?? new NullLogger();
        
        $historyPath = $options['history_store_path'] ?? __DIR__ . '/../../storage/meta_learning_history.json';
        $this->historyStore = new TaskHistoryStore($historyPath);

        if (isset($options['embedder'])) {
            $this->embedder = $options['embedder'];
        } else {
            $this->embedder = new TaskEmbedder();
        }

        $this->knnMatcher = new KNNMatcher();

        // Initialize default hyperparameters
        $this->hyperparameters = [
            'learning_rate' => $options['default_learning_rate'] ?? 0.01,
            'adaptation_window' => $options['adaptation_window'] ?? 5,
            'min_samples_for_adaptation' => 3,
            'meta_batch_size' => 10,
        ];

        // Initialize learning strategies
        $this->learningStrategies = [
            'gradient_based' => ['success_rate' => 0.5, 'sample_efficiency' => 0.5, 'used_count' => 0],
            'model_based' => ['success_rate' => 0.5, 'sample_efficiency' => 0.5, 'used_count' => 0],
            'metric_based' => ['success_rate' => 0.5, 'sample_efficiency' => 0.5, 'used_count' => 0],
            'optimization_based' => ['success_rate' => 0.5, 'sample_efficiency' => 0.5, 'used_count' => 0],
        ];
    }

    /**
     * Adapt quickly to a new task with few examples (few-shot learning).
     *
     * @param string $task The new task
     * @param array<array> $fewShotExamples Small set of examples (task, answer, quality)
     * @param array<string, mixed> $options Adaptation options
     * @return array{strategy: string, parameters: array, confidence: float, meta_features: array}
     */
    public function fewShotAdapt(
        string $task,
        array $fewShotExamples,
        array $options = []
    ): array {
        $this->logger->info("Few-shot adaptation", [
            'task' => substr($task, 0, 50),
            'examples' => count($fewShotExamples),
        ]);

        if (count($fewShotExamples) < 1) {
            throw new \InvalidArgumentException('At least 1 example required for few-shot learning');
        }

        // Embed the task
        $taskEmbedding = $this->embedder->embed([]);

        // Find similar meta-learning experiences
        $similarMetaExperiences = $this->historyStore->findSimilar($taskEmbedding, 5);

        // Extract meta-features from few-shot examples
        $metaFeatures = $this->extractMetaFeatures($fewShotExamples);

        // Select best learning strategy based on meta-features and history
        $strategy = $this->selectLearningStrategy($metaFeatures, $similarMetaExperiences);

        // Optimize hyperparameters for this task type
        $parameters = $this->optimizeHyperparameters($metaFeatures, $similarMetaExperiences);

        // Calculate confidence based on similar experiences
        $confidence = $this->calculateAdaptationConfidence($similarMetaExperiences, $metaFeatures);

        // Record this meta-learning episode
        $this->recordMetaExperience($task, $taskEmbedding, $strategy, $parameters, $metaFeatures);

        return [
            'strategy' => $strategy,
            'parameters' => $parameters,
            'confidence' => round($confidence, 3),
            'meta_features' => $metaFeatures,
            'few_shot_count' => count($fewShotExamples),
        ];
    }

    /**
     * Optimize learning rate dynamically based on performance.
     *
     * @param array<array> $recentPerformance Recent task performances
     * @return float Optimized learning rate
     */
    public function optimizeLearningRate(array $recentPerformance): float
    {
        if (empty($recentPerformance)) {
            return $this->hyperparameters['learning_rate'];
        }

        // Analyze performance trend
        $qualities = array_column($recentPerformance, 'quality');
        $trend = $this->calculateTrend($qualities);

        $currentLR = $this->hyperparameters['learning_rate'];

        // Adjust learning rate based on trend
        if ($trend > 0.1) {
            // Improving fast - increase learning rate
            $newLR = min(0.1, $currentLR * 1.2);
        } elseif ($trend < -0.1) {
            // Degrading - decrease learning rate
            $newLR = max(0.001, $currentLR * 0.8);
        } else {
            // Stable - maintain or slightly increase
            $newLR = min(0.1, $currentLR * 1.05);
        }

        $this->hyperparameters['learning_rate'] = $newLR;

        $this->logger->debug("Learning rate optimized", [
            'old_lr' => round($currentLR, 5),
            'new_lr' => round($newLR, 5),
            'trend' => round($trend, 3),
        ]);

        return $newLR;
    }

    /**
     * Select best learning algorithm for a task type.
     *
     * @param array<string, mixed> $taskCharacteristics Task characteristics
     * @return string Best algorithm identifier
     */
    public function selectAlgorithm(array $taskCharacteristics): string
    {
        // Embed task characteristics
        $taskVector = $this->characteristicsToVector($taskCharacteristics);

        // Find similar tasks and their successful algorithms
        $history = $this->historyStore->getAll();
        $algorithmScores = [];

        foreach ($this->learningStrategies as $algorithm => $metrics) {
            // Score based on historical performance
            $score = ($metrics['success_rate'] * 0.6) + ($metrics['sample_efficiency'] * 0.4);
            
            // Bonus for relevant experience
            $relevantUses = $this->countRelevantUses($algorithm, $taskCharacteristics, $history);
            $score += ($relevantUses * 0.1);

            $algorithmScores[$algorithm] = $score;
        }

        arsort($algorithmScores);
        $bestAlgorithm = array_key_first($algorithmScores);

        $this->logger->info("Algorithm selected", [
            'algorithm' => $bestAlgorithm,
            'score' => round($algorithmScores[$bestAlgorithm], 3),
        ]);

        return $bestAlgorithm;
    }

    /**
     * Update meta-learning model with new experience.
     *
     * @param string $strategy The strategy used
     * @param bool $success Whether it succeeded
     * @param int $samplesUsed Number of samples needed
     * @param float $qualityAchieved Final quality score
     */
    public function updateMetaModel(
        string $strategy,
        bool $success,
        int $samplesUsed,
        float $qualityAchieved
    ): void {
        if (!isset($this->learningStrategies[$strategy])) {
            $this->logger->warning("Unknown strategy: {$strategy}");
            return;
        }

        $currentMetrics = $this->learningStrategies[$strategy];

        // Update success rate (exponential moving average)
        $alpha = 0.1; // Learning rate for meta-learning itself
        $newSuccessRate = ($currentMetrics['success_rate'] * (1 - $alpha)) + (($success ? 1.0 : 0.0) * $alpha);

        // Update sample efficiency (inverse of samples needed)
        $efficiency = 1.0 / max(1, $samplesUsed);
        $newEfficiency = ($currentMetrics['sample_efficiency'] * (1 - $alpha)) + ($efficiency * $alpha);

        $this->learningStrategies[$strategy] = [
            'success_rate' => $newSuccessRate,
            'sample_efficiency' => $newEfficiency,
            'used_count' => $currentMetrics['used_count'] + 1,
            'last_quality' => $qualityAchieved,
        ];

        $this->logger->debug("Meta-model updated", [
            'strategy' => $strategy,
            'success_rate' => round($newSuccessRate, 3),
            'sample_efficiency' => round($newEfficiency, 3),
        ]);
    }

    /**
     * Get meta-learning statistics.
     *
     * @return array{strategies: array, hyperparameters: array, learning_efficiency: float}
     */
    public function getStatistics(): array
    {
        $history = $this->historyStore->getAll();

        // Calculate overall learning efficiency
        if (count($history) > 10) {
            $early = array_slice($history, 0, 10);
            $recent = array_slice($history, -10);
            
            $earlyQuality = array_sum(array_column($early, 'quality_score')) / 10;
            $recentQuality = array_sum(array_column($recent, 'quality_score')) / 10;
            
            $learningEfficiency = ($recentQuality - $earlyQuality) / 10;
        } else {
            $learningEfficiency = 0.0;
        }

        return [
            'strategies' => $this->learningStrategies,
            'hyperparameters' => $this->hyperparameters,
            'learning_efficiency' => round($learningEfficiency, 4),
            'total_meta_experiences' => count($history),
            'best_strategy' => $this->getBestStrategy(),
        ];
    }

    /**
     * Extract meta-features from few-shot examples.
     */
    private function extractMetaFeatures(array $examples): array
    {
        $qualities = array_column($examples, 'quality');
        $taskLengths = array_map(fn($e) => strlen($e['task']), $examples);

        return [
            'sample_count' => count($examples),
            'avg_quality' => array_sum($qualities) / count($qualities),
            'quality_variance' => $this->calculateVariance($qualities),
            'avg_task_length' => array_sum($taskLengths) / count($taskLengths),
            'complexity_indicator' => $this->estimateComplexity($examples),
        ];
    }

    /**
     * Select learning strategy based on meta-features.
     */
    private function selectLearningStrategy(array $metaFeatures, array $similarExperiences): string
    {
        // If we have similar experiences, use what worked before
        if (!empty($similarExperiences)) {
            $strategyVotes = [];
            foreach ($similarExperiences as $exp) {
                $metadata = $exp['metadata'] ?? [];
                if (isset($metadata['strategy']) && $exp['is_success']) {
                    $strategy = $metadata['strategy'];
                    $strategyVotes[$strategy] = ($strategyVotes[$strategy] ?? 0) + $exp['similarity'];
                }
            }

            if (!empty($strategyVotes)) {
                arsort($strategyVotes);
                return array_key_first($strategyVotes);
            }
        }

        // Otherwise, select based on meta-features and current performance
        return $this->selectAlgorithm($metaFeatures);
    }

    /**
     * Optimize hyperparameters for task type.
     */
    private function optimizeHyperparameters(array $metaFeatures, array $similarExperiences): array
    {
        $optimized = $this->hyperparameters;

        // Adjust learning rate based on complexity
        if ($metaFeatures['complexity_indicator'] > 0.7) {
            $optimized['learning_rate'] *= 0.5; // Slower for complex tasks
        } elseif ($metaFeatures['complexity_indicator'] < 0.3) {
            $optimized['learning_rate'] *= 1.5; // Faster for simple tasks
        }

        // Adjust adaptation window based on sample count
        if ($metaFeatures['sample_count'] < 3) {
            $optimized['adaptation_window'] = 3; // Smaller window for few examples
        } else {
            $optimized['adaptation_window'] = min(10, $metaFeatures['sample_count']);
        }

        return $optimized;
    }

    /**
     * Calculate adaptation confidence.
     */
    private function calculateAdaptationConfidence(array $similarExperiences, array $metaFeatures): float
    {
        if (empty($similarExperiences)) {
            // Low confidence without history
            return 0.3;
        }

        $avgSimilarity = array_sum(array_column($similarExperiences, 'similarity')) / count($similarExperiences);
        $avgQuality = array_sum(array_column($similarExperiences, 'quality_score')) / count($similarExperiences);
        
        $confidence = ($avgSimilarity * 0.5) + (($avgQuality / 10.0) * 0.5);

        // Bonus for more samples
        if ($metaFeatures['sample_count'] >= 5) {
            $confidence += 0.1;
        }

        return min(1.0, $confidence);
    }

    /**
     * Record meta-learning experience.
     */
    private function recordMetaExperience(
        string $task,
        array $taskEmbedding,
        string $strategy,
        array $parameters,
        array $metaFeatures
    ): void {
        $this->historyStore->recordTaskOutcome(
            task: $task,
            taskEmbedding: $taskEmbedding,
            agentId: 'meta_learner',
            qualityScore: 7.0, // Placeholder, will be updated with actual results
            isSuccess: true,
            duration: 0.0,
            metadata: [
                'strategy' => $strategy,
                'parameters' => $parameters,
                'meta_features' => $metaFeatures,
                'learning_type' => 'few_shot',
            ]
        );
    }

    /**
     * Calculate trend from time series data.
     */
    private function calculateTrend(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $n = count($values);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $values[$i];
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        return $slope;
    }

    /**
     * Convert characteristics to vector.
     */
    private function characteristicsToVector(array $characteristics): array
    {
        // Simple vectorization
        return array_values($characteristics);
    }

    /**
     * Count relevant uses of algorithm.
     */
    private function countRelevantUses(string $algorithm, array $taskChars, array $history): int
    {
        $count = 0;
        foreach ($history as $entry) {
            $metadata = $entry['metadata'] ?? [];
            if (($metadata['strategy'] ?? '') === $algorithm && $entry['is_success']) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Estimate complexity from examples.
     */
    private function estimateComplexity(array $examples): float
    {
        $avgLength = array_sum(array_map(fn($e) => strlen($e['task']), $examples)) / count($examples);
        $avgQuality = array_sum(array_column($examples, 'quality')) / count($examples);
        
        // Higher length and lower quality indicate higher complexity
        $lengthFactor = min(1.0, $avgLength / 200);
        $qualityFactor = 1.0 - ($avgQuality / 10.0);
        
        return ($lengthFactor * 0.5) + ($qualityFactor * 0.5);
    }

    /**
     * Calculate variance.
     */
    private function calculateVariance(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }

        $mean = array_sum($values) / count($values);
        $squareDiffs = array_map(fn($v) => ($v - $mean) ** 2, $values);
        
        return sqrt(array_sum($squareDiffs) / count($values));
    }

    /**
     * Get best performing strategy.
     */
    private function getBestStrategy(): string
    {
        $bestStrategy = '';
        $bestScore = -1.0;

        foreach ($this->learningStrategies as $strategy => $metrics) {
            $score = ($metrics['success_rate'] * 0.6) + ($metrics['sample_efficiency'] * 0.4);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestStrategy = $strategy;
            }
        }

        return $bestStrategy;
    }

    /**
     * Get history store.
     */
    public function getHistoryStore(): TaskHistoryStore
    {
        return $this->historyStore;
    }

    /**
     * Get learning strategies.
     */
    public function getLearningStrategies(): array
    {
        return $this->learningStrategies;
    }

    /**
     * Get hyperparameters.
     */
    public function getHyperparameters(): array
    {
        return $this->hyperparameters;
    }
}

