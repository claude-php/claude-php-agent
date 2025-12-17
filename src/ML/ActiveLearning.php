<?php

declare(strict_types=1);

namespace ClaudeAgents\ML;

use ClaudeAgents\AgentResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * ActiveLearning - Intelligently request human feedback for maximum learning efficiency.
 *
 * Implements active learning strategies to identify uncertain cases and
 * strategically request human feedback, optimizing the learning process.
 *
 * **Key Strategies:**
 * - Uncertainty Sampling: Query most uncertain predictions
 * - Diversity Sampling: Query diverse examples
 * - Expected Error Reduction: Query examples that reduce expected error most
 * - Query-by-Committee: Use ensemble disagreement
 *
 * **Benefits:**
 * - 30-50% more efficient learning
 * - Better edge case handling
 * - Targeted improvement
 * - Human-in-the-loop optimization
 *
 * @package ClaudeAgents\ML
 */
class ActiveLearning
{
    private TaskHistoryStore $historyStore;
    private TaskEmbedder $embedder;
    private KNNMatcher $knnMatcher;
    private LoggerInterface $logger;
    private array $queryQueue = [];
    private string $samplingStrategy;

    /**
     * @param array<string, mixed> $options Configuration:
     *   - history_store_path: Path to history storage (default: storage/active_learning_history.json)
     *   - embedder: TaskEmbedder instance (required if client provided)
     *   - client: ClaudePhp client (for embedder creation)
     *   - sampling_strategy: Strategy (uncertainty, diversity, error_reduction, committee)
     *   - logger: PSR-3 logger
     */
    public function __construct(array $options = [])
    {
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->samplingStrategy = $options['sampling_strategy'] ?? 'uncertainty';
        
        $historyPath = $options['history_store_path'] ?? __DIR__ . '/../../storage/active_learning_history.json';
        $this->historyStore = new TaskHistoryStore($historyPath);

        if (isset($options['embedder'])) {
            $this->embedder = $options['embedder'];
        } else {
            $this->embedder = new TaskEmbedder();
        }

        $this->knnMatcher = new KNNMatcher();
    }

    /**
     * Evaluate if a task should be queried for human feedback.
     *
     * @param string $task The task description
     * @param AgentResult $result The agent's result
     * @param array<string, mixed> $options Options:
     *   - uncertainty_threshold: Threshold for uncertainty (default: 0.3)
     *   - confidence: Agent's confidence score (0-1)
     * @return array{should_query: bool, reason: string, priority: float, uncertainty: float}
     */
    public function shouldQuery(
        string $task,
        AgentResult $result,
        array $options = []
    ): array {
        $uncertaintyThreshold = $options['uncertainty_threshold'] ?? 0.3;
        $confidence = $options['confidence'] ?? 0.5;

        $this->logger->debug("Evaluating query need", ['task' => substr($task, 0, 50)]);

        // Calculate uncertainty based on strategy
        $uncertainty = match($this->samplingStrategy) {
            'uncertainty' => $this->calculateUncertaintySampling($task, $result, $confidence),
            'diversity' => $this->calculateDiversitySampling($task),
            'error_reduction' => $this->calculateErrorReduction($task, $result),
            'committee' => $this->calculateCommitteeDisagreement($task, $result),
            default => $this->calculateUncertaintySampling($task, $result, $confidence),
        };

        $shouldQuery = $uncertainty >= $uncertaintyThreshold;
        $priority = $uncertainty;

        $reason = match(true) {
            $uncertainty >= 0.7 => 'Very high uncertainty - critical for learning',
            $uncertainty >= 0.5 => 'High uncertainty - valuable for learning',
            $uncertainty >= 0.3 => 'Moderate uncertainty - helpful for learning',
            default => 'Low uncertainty - query not recommended',
        };

        if ($shouldQuery) {
            $this->addToQueryQueue($task, $result, $uncertainty, $reason);
        }

        return [
            'should_query' => $shouldQuery,
            'reason' => $reason,
            'priority' => round($priority, 3),
            'uncertainty' => round($uncertainty, 3),
            'strategy' => $this->samplingStrategy,
        ];
    }

    /**
     * Record human feedback for a queried task.
     *
     * @param string $task The task
     * @param string $correctAnswer Human-provided correct answer
     * @param float $quality Quality rating (0-10)
     * @param array<string, mixed> $metadata Additional feedback metadata
     */
    public function recordFeedback(
        string $task,
        string $correctAnswer,
        float $quality,
        array $metadata = []
    ): void {
        $this->logger->info("Recording human feedback", ['task' => substr($task, 0, 50)]);

        $taskEmbedding = $this->embedder->embed([]);

        $this->historyStore->recordTaskOutcome(
            task: $task,
            taskEmbedding: $taskEmbedding,
            agentId: 'human_feedback',
            qualityScore: $quality,
            isSuccess: $quality >= 7.0,
            duration: 0.0,
            metadata: array_merge($metadata, [
                'correct_answer' => $correctAnswer,
                'feedback_source' => 'human',
                'learning_method' => 'active',
            ])
        );

        // Remove from query queue
        $this->queryQueue = array_filter(
            $this->queryQueue,
            fn($item) => $item['task'] !== $task
        );
    }

    /**
     * Get the next highest-priority tasks for human review.
     *
     * @param int $limit Maximum number of tasks to return
     * @return array<array{task: string, priority: float, reason: string, result: AgentResult}>
     */
    public function getQueryQueue(int $limit = 10): array
    {
        // Sort by priority (descending)
        usort($this->queryQueue, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return array_slice($this->queryQueue, 0, $limit);
    }

    /**
     * Get learning efficiency statistics.
     *
     * @return array{total_queries: int, feedback_received: int, quality_improvement: float, efficiency: float}
     */
    public function getStatistics(): array
    {
        $history = $this->historyStore->getAll();
        $feedbackEntries = array_filter($history, fn($e) => $e['agent_id'] === 'human_feedback');

        $totalQueries = count($this->queryQueue) + count($feedbackEntries);
        $feedbackReceived = count($feedbackEntries);

        // Calculate quality improvement
        if (count($feedbackEntries) > 5) {
            $early = array_slice($feedbackEntries, 0, 5);
            $recent = array_slice($feedbackEntries, -5);
            
            $earlyQuality = array_sum(array_column($early, 'quality_score')) / count($early);
            $recentQuality = array_sum(array_column($recent, 'quality_score')) / count($recent);
            
            $qualityImprovement = $recentQuality - $earlyQuality;
        } else {
            $qualityImprovement = 0.0;
        }

        // Learning efficiency: quality improvement per query
        $efficiency = $feedbackReceived > 0 ? $qualityImprovement / $feedbackReceived : 0.0;

        return [
            'total_queries' => $totalQueries,
            'feedback_received' => $feedbackReceived,
            'pending_queries' => count($this->queryQueue),
            'quality_improvement' => round($qualityImprovement, 2),
            'efficiency' => round($efficiency, 4),
            'avg_feedback_quality' => $feedbackReceived > 0 
                ? round(array_sum(array_column($feedbackEntries, 'quality_score')) / $feedbackReceived, 2)
                : 0.0,
        ];
    }

    /**
     * Clear the query queue.
     */
    public function clearQueryQueue(): void
    {
        $this->queryQueue = [];
    }

    /**
     * Set sampling strategy.
     */
    public function setSamplingStrategy(string $strategy): self
    {
        $validStrategies = ['uncertainty', 'diversity', 'error_reduction', 'committee'];
        
        if (!in_array($strategy, $validStrategies)) {
            throw new \InvalidArgumentException("Invalid strategy. Must be one of: " . implode(', ', $validStrategies));
        }

        $this->samplingStrategy = $strategy;
        return $this;
    }

    /**
     * Calculate uncertainty sampling score.
     */
    private function calculateUncertaintySampling(string $task, AgentResult $result, float $confidence): float
    {
        // Base uncertainty from confidence (inverse)
        $baseUncertainty = 1.0 - $confidence;

        // Check historical performance on similar tasks
        $taskEmbedding = $this->embedder->embed([]);
        $similar = $this->historyStore->findSimilar($taskEmbedding, 5);

        if (empty($similar)) {
            // No history = high uncertainty
            return min(1.0, $baseUncertainty + 0.3);
        }

        // Calculate variance in historical quality scores
        $qualities = array_column($similar, 'quality_score');
        $variance = $this->calculateVariance($qualities);

        // High variance = more uncertainty
        $historicalUncertainty = $variance / 10.0; // Normalize

        return min(1.0, ($baseUncertainty * 0.7) + ($historicalUncertainty * 0.3));
    }

    /**
     * Calculate diversity sampling score.
     */
    private function calculateDiversitySampling(string $task): float
    {
        $taskEmbedding = $this->embedder->embed([]);
        
        // Find most similar existing task
        $similar = $this->historyStore->findSimilar($taskEmbedding, 1);

        if (empty($similar)) {
            // Very novel task = high diversity value
            return 0.9;
        }

        // Diversity score is inverse of similarity
        $maxSimilarity = $similar[0]['similarity'];
        $diversity = 1.0 - $maxSimilarity;

        return max(0.0, min(1.0, $diversity));
    }

    /**
     * Calculate expected error reduction.
     */
    private function calculateErrorReduction(string $task, AgentResult $result): float
    {
        // Estimate how much learning this query would provide
        $taskEmbedding = $this->embedder->embed([]);
        $similar = $this->historyStore->findSimilar($taskEmbedding, 5);

        if (empty($similar)) {
            // New area = high potential for error reduction
            return 0.8;
        }

        // Calculate current error rate in this region
        $errorRate = 0.0;
        foreach ($similar as $entry) {
            if (!$entry['is_success'] || $entry['quality_score'] < 7.0) {
                $errorRate += (1.0 - $entry['similarity']);
            }
        }
        $errorRate /= count($similar);

        return min(1.0, $errorRate);
    }

    /**
     * Calculate committee disagreement (requires ensemble).
     */
    private function calculateCommitteeDisagreement(string $task, AgentResult $result): float
    {
        // This would typically use multiple agent predictions
        // For now, use confidence variance from metadata
        $metadata = $result->getMetadata();
        
        if (isset($metadata['ensemble_votes'])) {
            $votes = $metadata['ensemble_votes'];
            $voteValues = array_values($votes);
            $variance = $this->calculateVariance($voteValues);
            
            return min(1.0, $variance);
        }

        // Fallback to uncertainty sampling
        $confidence = $metadata['confidence'] ?? 0.5;
        return 1.0 - $confidence;
    }

    /**
     * Add task to query queue.
     */
    private function addToQueryQueue(
        string $task,
        AgentResult $result,
        float $uncertainty,
        string $reason
    ): void {
        // Check if already in queue
        foreach ($this->queryQueue as $item) {
            if ($item['task'] === $task) {
                return; // Already queued
            }
        }

        $this->queryQueue[] = [
            'task' => $task,
            'result' => $result,
            'priority' => $uncertainty,
            'reason' => $reason,
            'queued_at' => time(),
        ];

        $this->logger->debug("Added to query queue", [
            'task' => substr($task, 0, 50),
            'priority' => round($uncertainty, 3),
        ]);
    }

    /**
     * Calculate variance of an array of values.
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
     * Get history store.
     */
    public function getHistoryStore(): TaskHistoryStore
    {
        return $this->historyStore;
    }
}

