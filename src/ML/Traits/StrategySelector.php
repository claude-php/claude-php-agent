<?php

declare(strict_types=1);

namespace ClaudeAgents\ML\Traits;

use ClaudeAgents\ML\KNNMatcher;
use ClaudeAgents\ML\TaskEmbedder;
use ClaudeAgents\ML\TaskHistoryStore;

/**
 * Trait for learning-based strategy selection.
 *
 * Learns which execution strategy (loop pattern, search algorithm, etc.)
 * works best for which types of tasks.
 *
 * @package ClaudeAgents\ML\Traits
 */
trait StrategySelector
{
    private ?TaskHistoryStore $strategyHistory = null;
    private ?TaskEmbedder $strategyEmbedder = null;
    private ?KNNMatcher $strategyMatcher = null;
    private array $availableStrategies = [];
    private string $defaultStrategy = '';

    /**
     * Enable strategy selection learning.
     *
     * @param array $strategies Available strategies to choose from
     * @param string $defaultStrategy Default strategy if no history
     * @param string $historyPath Path to store strategy history
     * @return self
     */
    public function enableStrategyLearning(
        array $strategies,
        string $defaultStrategy,
        string $historyPath = 'storage/strategy_learning.json'
    ): self {
        $this->availableStrategies = $strategies;
        $this->defaultStrategy = $defaultStrategy;
        $this->strategyHistory = new TaskHistoryStore($historyPath);
        $this->strategyEmbedder = new TaskEmbedder();
        $this->strategyMatcher = new KNNMatcher();

        return $this;
    }

    /**
     * Select best strategy for a task based on historical performance.
     *
     * @param string $task The task to select strategy for
     * @param int $k Number of similar tasks to consider
     * @return string Selected strategy name
     */
    protected function selectBestStrategy(string $task, int $k = 10): string
    {
        if (!$this->strategyHistory || !$this->strategyEmbedder) {
            return $this->defaultStrategy;
        }

        $taskAnalysis = method_exists($this, 'analyzeTaskForLearning')
            ? $this->analyzeTaskForLearning($task)
            : ['complexity' => 'medium'];

        $taskVector = $this->strategyEmbedder->embed($taskAnalysis);
        $similar = $this->strategyHistory->findSimilar($taskVector, $k);

        if (empty($similar)) {
            return $this->defaultStrategy;
        }

        // Score each strategy based on performance on similar tasks
        $strategyScores = [];

        foreach ($this->availableStrategies as $strategy) {
            $scores = [];
            $weights = [];

            foreach ($similar as $record) {
                if (($record['metadata']['strategy'] ?? null) === $strategy) {
                    $quality = $record['metadata']['quality_score'] ?? 5.0;
                    $success = $record['metadata']['success'] ?? false;
                    
                    // Score combines quality and success
                    $score = $success ? $quality : $quality * 0.5;
                    $scores[] = $score;
                    
                    // Weight by similarity
                    $weights[] = $record['similarity'];
                }
            }

            if (empty($scores)) {
                $strategyScores[$strategy] = 0.0;
                continue;
            }

            // Calculate weighted average score
            $weightedSum = 0;
            $totalWeight = array_sum($weights);

            foreach ($scores as $i => $score) {
                $weightedSum += $score * $weights[$i];
            }

            $strategyScores[$strategy] = $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;
        }

        // Select strategy with highest score
        if (!empty($strategyScores)) {
            arsort($strategyScores);
            return array_key_first($strategyScores);
        }

        return $this->defaultStrategy;
    }

    /**
     * Record strategy performance.
     *
     * @param string $task The task executed
     * @param string $strategy Strategy used
     * @param bool $success Whether execution succeeded
     * @param float $qualityScore Quality score (0-10)
     * @param float $duration Duration in seconds
     * @param array $additionalMetadata Additional metadata to store
     */
    protected function recordStrategyPerformance(
        string $task,
        string $strategy,
        bool $success,
        float $qualityScore,
        float $duration,
        array $additionalMetadata = []
    ): void {
        if (!$this->strategyHistory || !$this->strategyEmbedder) {
            return;
        }

        try {
            $taskAnalysis = method_exists($this, 'analyzeTaskForLearning')
                ? $this->analyzeTaskForLearning($task)
                : ['complexity' => 'medium'];

            $taskVector = $this->strategyEmbedder->embed($taskAnalysis);

            $this->strategyHistory->record([
                'id' => uniqid('strat_', true),
                'task' => substr($task, 0, 500),
                'task_vector' => $taskVector,
                'task_analysis' => $taskAnalysis,
                'agent_id' => $this->getAgentIdentifier(),
                'success' => $success,
                'quality_score' => $qualityScore,
                'duration' => $duration,
                'timestamp' => time(),
                'metadata' => array_merge($additionalMetadata, [
                    'strategy' => $strategy,
                    'quality_score' => $qualityScore,
                    'success' => $success,
                ]),
            ]);
        } catch (\Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Get strategy performance analysis.
     *
     * @return array Performance breakdown by strategy
     */
    public function getStrategyPerformance(): array
    {
        if (!$this->strategyHistory) {
            return [];
        }

        $allHistory = $this->strategyHistory->getAll();
        $performance = [];

        foreach ($this->availableStrategies as $strategy) {
            $strategyRecords = array_filter(
                $allHistory,
                fn ($r) => ($r['metadata']['strategy'] ?? null) === $strategy
            );

            if (empty($strategyRecords)) {
                continue;
            }

            $successes = array_filter($strategyRecords, fn ($r) => $r['success'] ?? false);
            $qualities = array_column($strategyRecords, 'quality_score');
            $durations = array_column($strategyRecords, 'duration');

            $performance[$strategy] = [
                'attempts' => count($strategyRecords),
                'successes' => count($successes),
                'success_rate' => count($successes) / count($strategyRecords),
                'avg_quality' => array_sum($qualities) / count($qualities),
                'avg_duration' => array_sum($durations) / count($durations),
            ];
        }

        return $performance;
    }

    /**
     * Get strategy selection confidence.
     *
     * @param string $task Task to check confidence for
     * @param int $k Number of similar tasks to consider
     * @return array Contains 'strategy', 'confidence', and 'reasoning'
     */
    protected function getStrategyConfidence(string $task, int $k = 10): array
    {
        if (!$this->strategyHistory || !$this->strategyEmbedder) {
            return [
                'strategy' => $this->defaultStrategy,
                'confidence' => 0.5,
                'reasoning' => 'No history available, using default strategy',
            ];
        }

        $taskAnalysis = method_exists($this, 'analyzeTaskForLearning')
            ? $this->analyzeTaskForLearning($task)
            : ['complexity' => 'medium'];

        $taskVector = $this->strategyEmbedder->embed($taskAnalysis);
        $similar = $this->strategyHistory->findSimilar($taskVector, $k);

        $selectedStrategy = $this->selectBestStrategy($task, $k);

        if (empty($similar)) {
            return [
                'strategy' => $selectedStrategy,
                'confidence' => 0.5,
                'reasoning' => 'No similar historical tasks found',
            ];
        }

        // Calculate confidence based on similarity and agreement
        $avgSimilarity = array_sum(array_column($similar, 'similarity')) / count($similar);
        $strategyAgreement = count(array_filter(
            $similar,
            fn ($r) => ($r['metadata']['strategy'] ?? null) === $selectedStrategy
        )) / count($similar);

        $confidence = ($avgSimilarity * 0.5) + ($strategyAgreement * 0.5);

        return [
            'strategy' => $selectedStrategy,
            'confidence' => $confidence,
            'reasoning' => sprintf(
                'Based on %d similar tasks (avg similarity: %.2f, strategy agreement: %.1f%%)',
                count($similar),
                $avgSimilarity,
                $strategyAgreement * 100
            ),
        ];
    }

    /**
     * Get agent identifier (required for trait).
     */
    abstract protected function getAgentIdentifier(): string;
}

