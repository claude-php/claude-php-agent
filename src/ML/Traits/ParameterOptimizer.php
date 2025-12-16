<?php

declare(strict_types=1);

namespace ClaudeAgents\ML\Traits;

use ClaudeAgents\ML\TaskEmbedder;
use ClaudeAgents\ML\TaskHistoryStore;

/**
 * Trait for automatic parameter optimization using k-NN.
 *
 * Learns optimal parameter values based on historical performance
 * for similar tasks.
 *
 * @package ClaudeAgents\ML\Traits
 */
trait ParameterOptimizer
{
    private ?TaskHistoryStore $parameterHistory = null;
    private ?TaskEmbedder $parameterEmbedder = null;
    private array $parameterDefaults = [];

    /**
     * Enable parameter optimization.
     *
     * @param string $historyPath Path to store parameter history
     * @param array $defaults Default parameter values
     * @return self
     */
    public function enableParameterOptimization(
        string $historyPath = 'storage/parameter_optimization.json',
        array $defaults = []
    ): self {
        $this->parameterHistory = new TaskHistoryStore($historyPath);
        $this->parameterEmbedder = new TaskEmbedder();
        $this->parameterDefaults = $defaults;

        return $this;
    }

    /**
     * Learn optimal parameters for a task.
     *
     * @param string $task The task to optimize for
     * @param array $parameterNames Parameters to optimize
     * @param int $k Number of similar tasks to consider
     * @return array Optimized parameters
     */
    protected function learnOptimalParameters(
        string $task,
        array $parameterNames,
        int $k = 10
    ): array {
        if (!$this->parameterHistory || !$this->parameterEmbedder) {
            return $this->parameterDefaults;
        }

        $taskAnalysis = method_exists($this, 'analyzeTaskForLearning')
            ? $this->analyzeTaskForLearning($task)
            : ['complexity' => 'medium'];

        $taskVector = $this->parameterEmbedder->embed($taskAnalysis);
        $similar = $this->parameterHistory->findSimilar($taskVector, $k, ['success' => true]);

        if (empty($similar)) {
            return $this->parameterDefaults;
        }

        $optimized = [];

        foreach ($parameterNames as $paramName) {
            $values = [];
            $weights = [];

            foreach ($similar as $record) {
                if (isset($record['metadata']['parameters'][$paramName])) {
                    $values[] = $record['metadata']['parameters'][$paramName];
                    // Weight by similarity and quality
                    $weights[] = $record['similarity'] * ($record['metadata']['quality_score'] ?? 5.0) / 10;
                }
            }

            if (empty($values)) {
                $optimized[$paramName] = $this->parameterDefaults[$paramName] ?? null;
                continue;
            }

            // Calculate weighted average
            $weightedSum = 0;
            $totalWeight = array_sum($weights);

            foreach ($values as $i => $value) {
                if (is_numeric($value)) {
                    $weightedSum += $value * $weights[$i];
                }
            }

            if ($totalWeight > 0 && is_numeric($values[0])) {
                $optimized[$paramName] = $weightedSum / $totalWeight;

                // Round integers
                if (is_int($values[0])) {
                    $optimized[$paramName] = (int) round($optimized[$paramName]);
                }
            } else {
                // For non-numeric, use most common value
                $valueCounts = array_count_values($values);
                arsort($valueCounts);
                $optimized[$paramName] = array_key_first($valueCounts);
            }
        }

        return array_merge($this->parameterDefaults, $optimized);
    }

    /**
     * Record parameter performance.
     *
     * @param string $task The task executed
     * @param array $parameters Parameters used
     * @param bool $success Whether execution succeeded
     * @param float $qualityScore Quality score (0-10)
     * @param float $duration Duration in seconds
     */
    protected function recordParameterPerformance(
        string $task,
        array $parameters,
        bool $success,
        float $qualityScore,
        float $duration
    ): void {
        if (!$this->parameterHistory || !$this->parameterEmbedder) {
            return;
        }

        try {
            $taskAnalysis = method_exists($this, 'analyzeTaskForLearning')
                ? $this->analyzeTaskForLearning($task)
                : ['complexity' => 'medium'];

            $taskVector = $this->parameterEmbedder->embed($taskAnalysis);

            $this->parameterHistory->record([
                'id' => uniqid('param_', true),
                'task' => substr($task, 0, 500),
                'task_vector' => $taskVector,
                'task_analysis' => $taskAnalysis,
                'agent_id' => $this->getAgentIdentifier(),
                'success' => $success,
                'quality_score' => $qualityScore,
                'duration' => $duration,
                'timestamp' => time(),
                'metadata' => [
                    'parameters' => $parameters,
                    'quality_score' => $qualityScore,
                ],
            ]);
        } catch (\Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Get parameter optimization statistics.
     *
     * @return array Statistics about parameter optimization
     */
    public function getParameterStats(): array
    {
        if (!$this->parameterHistory) {
            return ['enabled' => false];
        }

        return $this->parameterHistory->getStats();
    }

    /**
     * Get agent identifier (required for trait).
     */
    abstract protected function getAgentIdentifier(): string;
}

