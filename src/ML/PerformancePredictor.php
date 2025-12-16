<?php

declare(strict_types=1);

namespace ClaudeAgents\ML;

/**
 * Performance Predictor - Estimates execution metrics using k-NN.
 *
 * Predicts duration, token usage, and success probability based
 * on historical performance of similar tasks.
 *
 * @package ClaudeAgents\ML
 */
class PerformancePredictor
{
    private TaskHistoryStore $history;
    private TaskEmbedder $embedder;
    private KNNMatcher $matcher;

    public function __construct(string $historyPath = 'storage/performance_history.json')
    {
        $this->history = new TaskHistoryStore($historyPath);
        $this->embedder = new TaskEmbedder();
        $this->matcher = new KNNMatcher();
    }

    /**
     * Predict execution duration for a task.
     *
     * @param string $task Task to predict for
     * @param string|null $agentType Optional agent type filter
     * @param int $k Number of similar tasks to consider
     * @return array Prediction with min, max, avg, confidence
     */
    public function predictDuration(
        string $task,
        ?string $agentType = null,
        int $k = 10
    ): array {
        $taskAnalysis = $this->analyzeTask($task);
        $taskVector = $this->embedder->embed($taskAnalysis);

        $filters = [];
        if ($agentType) {
            $filters['agent_type'] = $agentType;
        }

        $similar = $this->history->findSimilar($taskVector, $k, $filters);

        if (empty($similar)) {
            return [
                'estimated_duration' => 30.0, // Default 30 seconds
                'min_duration' => 10.0,
                'max_duration' => 60.0,
                'confidence' => 0.0,
                'sample_size' => 0,
            ];
        }

        $durations = array_column(array_column($similar, 'metadata'), 'duration');
        $durations = array_filter($durations, fn ($d) => $d !== null && $d > 0);

        if (empty($durations)) {
            return [
                'estimated_duration' => 30.0,
                'min_duration' => 10.0,
                'max_duration' => 60.0,
                'confidence' => 0.0,
                'sample_size' => 0,
            ];
        }

        $avgSimilarity = array_sum(array_column($similar, 'similarity')) / count($similar);

        return [
            'estimated_duration' => array_sum($durations) / count($durations),
            'min_duration' => min($durations),
            'max_duration' => max($durations),
            'confidence' => $avgSimilarity,
            'sample_size' => count($durations),
        ];
    }

    /**
     * Predict success probability for a task.
     *
     * @param string $task Task to predict for
     * @param string|null $agentType Optional agent type filter
     * @param int $k Number of similar tasks to consider
     * @return array Prediction with probability and confidence
     */
    public function predictSuccess(
        string $task,
        ?string $agentType = null,
        int $k = 10
    ): array {
        $taskAnalysis = $this->analyzeTask($task);
        $taskVector = $this->embedder->embed($taskAnalysis);

        $filters = [];
        if ($agentType) {
            $filters['agent_type'] = $agentType;
        }

        $similar = $this->history->findSimilar($taskVector, $k, $filters);

        if (empty($similar)) {
            return [
                'success_probability' => 0.5,
                'confidence' => 0.0,
                'sample_size' => 0,
            ];
        }

        $successes = array_filter(
            $similar,
            fn ($r) => $r['metadata']['success'] ?? false
        );

        $avgSimilarity = array_sum(array_column($similar, 'similarity')) / count($similar);

        return [
            'success_probability' => count($successes) / count($similar),
            'confidence' => $avgSimilarity,
            'sample_size' => count($similar),
        ];
    }

    /**
     * Predict quality score for a task.
     *
     * @param string $task Task to predict for
     * @param string|null $agentType Optional agent type filter
     * @param int $k Number of similar tasks to consider
     * @return array Prediction with expected quality and range
     */
    public function predictQuality(
        string $task,
        ?string $agentType = null,
        int $k = 10
    ): array {
        $taskAnalysis = $this->analyzeTask($task);
        $taskVector = $this->embedder->embed($taskAnalysis);

        $filters = [];
        if ($agentType) {
            $filters['agent_type'] = $agentType;
        }

        $similar = $this->history->findSimilar($taskVector, $k, $filters);

        if (empty($similar)) {
            return [
                'expected_quality' => 7.0,
                'min_quality' => 5.0,
                'max_quality' => 9.0,
                'confidence' => 0.0,
                'sample_size' => 0,
            ];
        }

        $qualities = array_column(array_column($similar, 'metadata'), 'quality_score');
        $qualities = array_filter($qualities, fn ($q) => $q !== null);

        if (empty($qualities)) {
            return [
                'expected_quality' => 7.0,
                'min_quality' => 5.0,
                'max_quality' => 9.0,
                'confidence' => 0.0,
                'sample_size' => 0,
            ];
        }

        $avgSimilarity = array_sum(array_column($similar, 'similarity')) / count($similar);

        return [
            'expected_quality' => array_sum($qualities) / count($qualities),
            'min_quality' => min($qualities),
            'max_quality' => max($qualities),
            'confidence' => $avgSimilarity,
            'sample_size' => count($qualities),
        ];
    }

    /**
     * Get comprehensive performance prediction.
     *
     * @param string $task Task to predict for
     * @param string|null $agentType Optional agent type filter
     * @param int $k Number of similar tasks to consider
     * @return array Complete prediction including duration, success, quality
     */
    public function predict(
        string $task,
        ?string $agentType = null,
        int $k = 10
    ): array {
        return [
            'duration' => $this->predictDuration($task, $agentType, $k),
            'success' => $this->predictSuccess($task, $agentType, $k),
            'quality' => $this->predictQuality($task, $agentType, $k),
        ];
    }

    /**
     * Record actual performance for learning.
     *
     * @param string $task Task that was executed
     * @param string $agentType Agent type used
     * @param bool $success Whether execution succeeded
     * @param float $duration Actual duration in seconds
     * @param float $qualityScore Actual quality score
     * @param array $additionalMetadata Additional metadata
     */
    public function recordPerformance(
        string $task,
        string $agentType,
        bool $success,
        float $duration,
        float $qualityScore,
        array $additionalMetadata = []
    ): void {
        $taskAnalysis = $this->analyzeTask($task);
        $taskVector = $this->embedder->embed($taskAnalysis);

        $this->history->record([
            'id' => uniqid('perf_', true),
            'task' => substr($task, 0, 500),
            'task_vector' => $taskVector,
            'task_analysis' => $taskAnalysis,
            'agent_id' => $agentType,
            'agent_type' => $agentType,
            'success' => $success,
            'quality_score' => $qualityScore,
            'duration' => $duration,
            'timestamp' => time(),
            'metadata' => array_merge($additionalMetadata, [
                'success' => $success,
                'duration' => $duration,
                'quality_score' => $qualityScore,
            ]),
        ]);
    }

    /**
     * Get prediction accuracy statistics.
     *
     * @return array Statistics about prediction accuracy
     */
    public function getAccuracyStats(): array
    {
        return $this->history->getStats();
    }

    /**
     * Simple task analysis.
     *
     * @param string $task Task to analyze
     * @return array Task analysis
     */
    private function analyzeTask(string $task): array
    {
        $length = strlen($task);
        $wordCount = str_word_count($task);

        return [
            'complexity' => match (true) {
                $length < 50 => 'simple',
                $length < 200 => 'medium',
                default => 'complex',
            },
            'domain' => 'general',
            'requires_tools' => false,
            'requires_knowledge' => false,
            'requires_reasoning' => true,
            'requires_iteration' => false,
            'requires_quality' => 'standard',
            'estimated_steps' => max(1, min(20, (int) ($wordCount / 5))),
            'key_requirements' => [],
        ];
    }
}

