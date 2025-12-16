<?php

declare(strict_types=1);

namespace ClaudeAgents\ML;

/**
 * Task History Store - Persists task execution history for learning.
 *
 * Stores task vectors, agent selections, and outcomes to enable
 * k-NN based agent selection and adaptive learning.
 *
 * @package ClaudeAgents\ML
 */
class TaskHistoryStore
{
    private string $storePath;
    private array $history = [];
    private bool $autoSave;
    private int $maxHistorySize;

    /**
     * Create a new Task History Store.
     *
     * @param string $storePath Path to store history file
     * @param bool $autoSave Automatically save after each addition (default: true)
     * @param int $maxHistorySize Maximum number of history entries to keep (default: 1000)
     */
    public function __construct(
        string $storePath = 'storage/agent_history.json',
        bool $autoSave = true,
        int $maxHistorySize = 1000
    ) {
        $this->storePath = $storePath;
        $this->autoSave = $autoSave;
        $this->maxHistorySize = $maxHistorySize;
        $this->load();
    }

    /**
     * Record a task execution.
     *
     * @param array $record Task execution record:
     *   - id: Unique identifier
     *   - task: Original task string
     *   - task_vector: Feature vector
     *   - task_analysis: Task analysis data
     *   - agent_id: Selected agent ID
     *   - agent_type: Agent type
     *   - success: Whether task succeeded
     *   - quality_score: Quality score (0-10)
     *   - duration: Execution duration
     *   - timestamp: Unix timestamp
     *   - metadata: Additional metadata
     */
    public function record(array $record): void
    {
        // Ensure required fields
        if (!isset($record['id'], $record['task_vector'], $record['agent_id'])) {
            throw new \InvalidArgumentException('Record must include id, task_vector, and agent_id');
        }

        // Add timestamp if not provided
        if (!isset($record['timestamp'])) {
            $record['timestamp'] = time();
        }

        $this->history[] = $record;

        // Enforce max size (remove oldest entries)
        if (count($this->history) > $this->maxHistorySize) {
            usort($this->history, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);
            $this->history = array_slice($this->history, 0, $this->maxHistorySize);
        }

        if ($this->autoSave) {
            $this->save();
        }
    }

    /**
     * Find similar historical tasks using k-NN.
     *
     * @param array<float> $taskVector Query task vector
     * @param int $k Number of nearest neighbors
     * @param array $filters Optional filters (e.g., ['success' => true])
     * @return array<array> Similar task records with distance/similarity
     */
    public function findSimilar(array $taskVector, int $k = 5, array $filters = []): array
    {
        $candidates = $this->history;

        // Apply filters
        if (!empty($filters)) {
            $candidates = array_filter($candidates, function ($record) use ($filters) {
                foreach ($filters as $key => $value) {
                    if (is_array($value)) {
                        if (!in_array($record[$key] ?? null, $value, true)) {
                            return false;
                        }
                    } else {
                        if (($record[$key] ?? null) !== $value) {
                            return false;
                        }
                    }
                }
                return true;
            });
        }

        if (empty($candidates)) {
            return [];
        }

        // Convert to k-NN format
        $knnCandidates = array_map(function ($record) {
            return [
                'id' => $record['id'],
                'vector' => $record['task_vector'],
                'metadata' => $record,
            ];
        }, $candidates);

        // Use k-NN matcher
        $matcher = new KNNMatcher();
        
        // Calculate temporal weights for recency
        $weights = [];
        foreach ($candidates as $record) {
            $weights[$record['id']] = $matcher->temporalWeight($record['timestamp'], 30.0);
        }

        return $matcher->findNearest(
            $taskVector,
            $knnCandidates,
            $k,
            'cosine',
            ['weights' => $weights]
        );
    }

    /**
     * Get agent performance on similar tasks.
     *
     * @param array<float> $taskVector Query task vector
     * @param string $agentId Agent to evaluate
     * @param int $k Number of similar tasks to consider
     * @return array Performance statistics
     */
    public function getAgentPerformanceOnSimilar(array $taskVector, string $agentId, int $k = 5): array
    {
        $similar = $this->findSimilar($taskVector, $k);

        if (empty($similar)) {
            return [
                'attempts' => 0,
                'successes' => 0,
                'success_rate' => 0.0,
                'avg_quality' => 0.0,
                'avg_duration' => 0.0,
            ];
        }

        $agentTasks = array_filter($similar, fn ($s) => ($s['metadata']['agent_id'] ?? null) === $agentId);

        if (empty($agentTasks)) {
            return [
                'attempts' => 0,
                'successes' => 0,
                'success_rate' => 0.0,
                'avg_quality' => 0.0,
                'avg_duration' => 0.0,
            ];
        }

        $successes = array_filter($agentTasks, fn ($t) => $t['metadata']['success'] ?? false);
        $qualities = array_column(array_column($agentTasks, 'metadata'), 'quality_score');
        $durations = array_column(array_column($agentTasks, 'metadata'), 'duration');

        return [
            'attempts' => count($agentTasks),
            'successes' => count($successes),
            'success_rate' => count($agentTasks) > 0 ? count($successes) / count($agentTasks) : 0.0,
            'avg_quality' => count($qualities) > 0 ? array_sum($qualities) / count($qualities) : 0.0,
            'avg_duration' => count($durations) > 0 ? array_sum($durations) / count($durations) : 0.0,
            'sample_size' => count($agentTasks),
            'avg_similarity' => count($agentTasks) > 0 ? array_sum(array_column($agentTasks, 'similarity')) / count($agentTasks) : 0.0,
        ];
    }

    /**
     * Get best performing agents for similar tasks.
     *
     * @param array<float> $taskVector Query task vector
     * @param int $k Number of similar tasks to consider
     * @param int $topN Number of top agents to return
     * @return array<array> Top agents with performance data
     */
    public function getBestAgentsForSimilar(array $taskVector, int $k = 10, int $topN = 3): array
    {
        $similar = $this->findSimilar($taskVector, $k);

        if (empty($similar)) {
            return [];
        }

        // Group by agent
        $agentPerformance = [];

        foreach ($similar as $task) {
            $agentId = $task['metadata']['agent_id'] ?? 'unknown';
            
            if (!isset($agentPerformance[$agentId])) {
                $agentPerformance[$agentId] = [
                    'agent_id' => $agentId,
                    'attempts' => 0,
                    'successes' => 0,
                    'total_quality' => 0.0,
                    'total_similarity' => 0.0,
                ];
            }

            $agentPerformance[$agentId]['attempts']++;
            
            if ($task['metadata']['success'] ?? false) {
                $agentPerformance[$agentId]['successes']++;
            }
            
            $agentPerformance[$agentId]['total_quality'] += $task['metadata']['quality_score'] ?? 0.0;
            $agentPerformance[$agentId]['total_similarity'] += $task['similarity'] ?? 0.0;
        }

        // Calculate metrics
        $results = [];
        foreach ($agentPerformance as $agentId => $data) {
            $successRate = $data['attempts'] > 0 ? $data['successes'] / $data['attempts'] : 0.0;
            $avgQuality = $data['attempts'] > 0 ? $data['total_quality'] / $data['attempts'] : 0.0;
            $avgSimilarity = $data['attempts'] > 0 ? $data['total_similarity'] / $data['attempts'] : 0.0;
            
            // Weighted score: success rate (40%) + quality (40%) + similarity (20%)
            $score = ($successRate * 0.4) + ($avgQuality / 10 * 0.4) + ($avgSimilarity * 0.2);

            $results[] = [
                'agent_id' => $agentId,
                'score' => $score,
                'success_rate' => $successRate,
                'avg_quality' => $avgQuality,
                'avg_similarity' => $avgSimilarity,
                'attempts' => $data['attempts'],
            ];
        }

        // Sort by score and return top N
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $topN);
    }

    /**
     * Calculate adaptive quality threshold based on similar tasks.
     *
     * @param array<float> $taskVector Query task vector
     * @param int $k Number of similar tasks to consider
     * @param float $defaultThreshold Default threshold if no history
     * @return float Recommended quality threshold
     */
    public function getAdaptiveThreshold(array $taskVector, int $k = 10, float $defaultThreshold = 7.0): float
    {
        $similar = $this->findSimilar($taskVector, $k, ['success' => true]);

        if (empty($similar)) {
            return $defaultThreshold;
        }

        $qualities = array_column(array_column($similar, 'metadata'), 'quality_score');
        $qualities = array_filter($qualities, fn ($q) => $q !== null);

        if (empty($qualities)) {
            return $defaultThreshold;
        }

        // Calculate mean and standard deviation
        $mean = array_sum($qualities) / count($qualities);
        $variance = 0.0;
        
        foreach ($qualities as $quality) {
            $variance += ($quality - $mean) ** 2;
        }
        
        $stdDev = sqrt($variance / count($qualities));

        // Set threshold to mean - 0.5 standard deviations (achievable but challenging)
        $threshold = max(5.0, min(9.5, $mean - 0.5 * $stdDev));

        return round($threshold, 1);
    }

    /**
     * Get all history records.
     *
     * @return array All stored records
     */
    public function getAll(): array
    {
        return $this->history;
    }

    /**
     * Clear all history.
     */
    public function clear(): void
    {
        $this->history = [];
        
        if ($this->autoSave) {
            $this->save();
        }
    }

    /**
     * Get history statistics.
     *
     * @return array Statistics about stored history
     */
    public function getStats(): array
    {
        if (empty($this->history)) {
            return [
                'total_records' => 0,
                'unique_agents' => 0,
                'success_rate' => 0.0,
                'avg_quality' => 0.0,
            ];
        }

        $uniqueAgents = array_unique(array_column($this->history, 'agent_id'));
        $successes = array_filter($this->history, fn ($r) => $r['success'] ?? false);
        $qualities = array_column($this->history, 'quality_score');
        $qualities = array_filter($qualities, fn ($q) => $q !== null);

        return [
            'total_records' => count($this->history),
            'unique_agents' => count($uniqueAgents),
            'success_rate' => count($successes) / count($this->history),
            'avg_quality' => count($qualities) > 0 ? array_sum($qualities) / count($qualities) : 0.0,
            'oldest_record' => min(array_column($this->history, 'timestamp')),
            'newest_record' => max(array_column($this->history, 'timestamp')),
        ];
    }

    /**
     * Save history to file.
     */
    public function save(): void
    {
        $dir = dirname($this->storePath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($this->history, JSON_PRETTY_PRINT);
        file_put_contents($this->storePath, $json);
    }

    /**
     * Load history from file.
     */
    private function load(): void
    {
        if (!file_exists($this->storePath)) {
            $this->history = [];
            return;
        }

        $json = file_get_contents($this->storePath);
        $data = json_decode($json, true);

        if (is_array($data)) {
            $this->history = $data;
        } else {
            $this->history = [];
        }
    }
}

