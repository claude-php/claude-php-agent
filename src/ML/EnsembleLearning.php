<?php

declare(strict_types=1);

namespace ClaudeAgents\ML;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * EnsembleLearning - Combines multiple agents for improved accuracy.
 *
 * Implements ensemble learning techniques to combine predictions from
 * multiple agents, improving overall accuracy and reliability.
 *
 * **Supported Strategies:**
 * - Voting: Simple majority voting
 * - Weighted Voting: Weighted by agent historical performance
 * - Bagging: Bootstrap aggregating with random sampling
 * - Stacking: Use a meta-agent to combine results
 * - Best-of-N: Select best result based on quality metrics
 *
 * @package ClaudeAgents\ML
 */
class EnsembleLearning
{
    private TaskHistoryStore $historyStore;
    private TaskEmbedder $embedder;
    private LoggerInterface $logger;
    private string $strategy;
    private array $agentWeights = [];

    /**
     * @param array<string, mixed> $options Configuration:
     *   - strategy: Ensemble strategy (voting, weighted_voting, bagging, stacking, best_of_n)
     *   - history_store_path: Path to ensemble history (default: storage/ensemble_history.json)
     *   - logger: PSR-3 logger
     */
    public function __construct(array $options = [])
    {
        $this->strategy = $options['strategy'] ?? 'weighted_voting';
        $this->logger = $options['logger'] ?? new NullLogger();
        
        $historyPath = $options['history_store_path'] ?? __DIR__ . '/../../storage/ensemble_history.json';
        
        if (isset($options['client'])) {
            $this->embedder = new TaskEmbedder();
        }
        
        $this->historyStore = new TaskHistoryStore($historyPath, false, 1000);
    }

    /**
     * Combine results from multiple agents using the configured strategy.
     *
     * @param string $task The task description
     * @param array<string, AgentInterface> $agents Map of agent ID to agent instance
     * @param array<string, mixed> $options Options:
     *   - strategy: Override default strategy
     *   - k: Number of agents for bagging (default: all)
     *   - confidence_threshold: Minimum confidence to accept result (default: 0.6)
     * @return AgentResult Combined ensemble result
     */
    public function combine(string $task, array $agents, array $options = []): AgentResult
    {
        if (empty($agents)) {
            return AgentResult::failure('No agents provided for ensemble');
        }

        $strategy = $options['strategy'] ?? $this->strategy;
        
        $this->logger->info('Running ensemble', [
            'strategy' => $strategy,
            'agent_count' => count($agents),
        ]);

        $startTime = microtime(true);

        // Execute all agents
        $results = [];
        $executionTimes = [];
        
        foreach ($agents as $agentId => $agent) {
            try {
                $agentStart = microtime(true);
                $result = $agent->run($task);
                $executionTimes[$agentId] = microtime(true) - $agentStart;
                
                $results[$agentId] = $result;
                
                $this->logger->debug("Agent {$agentId} completed", [
                    'success' => $result->isSuccess(),
                    'duration' => $executionTimes[$agentId],
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning("Agent {$agentId} failed: {$e->getMessage()}");
                $results[$agentId] = AgentResult::failure($e->getMessage());
            }
        }

        // Apply ensemble strategy
        $ensembleResult = match($strategy) {
            'voting' => $this->voting($task, $results),
            'weighted_voting' => $this->weightedVoting($task, $results),
            'bagging' => $this->bagging($task, $results, $options['k'] ?? count($agents)),
            'stacking' => $this->stacking($task, $results),
            'best_of_n' => $this->bestOfN($task, $results),
            default => $this->weightedVoting($task, $results),
        };

        $duration = microtime(true) - $startTime;

        // Record performance
        $this->recordEnsemblePerformance($task, $strategy, $results, $ensembleResult, $duration);

        return $ensembleResult;
    }

    /**
     * Simple majority voting.
     */
    private function voting(string $task, array $results): AgentResult
    {
        $votes = [];
        $successCount = 0;

        foreach ($results as $agentId => $result) {
            if (!$result->isSuccess()) {
                continue;
            }

            $successCount++;
            $answer = $this->normalizeAnswer($result->getAnswer());
            
            if (!isset($votes[$answer])) {
                $votes[$answer] = ['count' => 0, 'raw' => $result->getAnswer(), 'agents' => []];
            }
            
            $votes[$answer]['count']++;
            $votes[$answer]['agents'][] = $agentId;
        }

        if (empty($votes)) {
            return AgentResult::failure('All agents failed');
        }

        // Get winner
        uasort($votes, fn($a, $b) => $b['count'] <=> $a['count']);
        $winner = array_values($votes)[0];

        $confidence = $winner['count'] / max(1, $successCount);

        return AgentResult::success(
            answer: $winner['raw'],
            messages: [],
            iterations: count($results),
            metadata: [
                'strategy' => 'voting',
                'votes' => $votes,
                'confidence' => round($confidence, 3),
                'voting_agents' => $winner['agents'],
            ]
        );
    }

    /**
     * Weighted voting based on historical agent performance.
     */
    private function weightedVoting(string $task, array $results): AgentResult
    {
        // Get agent weights from history
        $weights = $this->getAgentWeights(array_keys($results));

        $votes = [];
        $totalWeight = 0;

        foreach ($results as $agentId => $result) {
            if (!$result->isSuccess()) {
                continue;
            }

            $weight = $weights[$agentId] ?? 1.0;
            $totalWeight += $weight;

            $answer = $this->normalizeAnswer($result->getAnswer());
            
            if (!isset($votes[$answer])) {
                $votes[$answer] = ['weight' => 0, 'raw' => $result->getAnswer(), 'agents' => []];
            }
            
            $votes[$answer]['weight'] += $weight;
            $votes[$answer]['agents'][] = ['agent' => $agentId, 'weight' => $weight];
        }

        if (empty($votes)) {
            return AgentResult::failure('All agents failed');
        }

        // Get weighted winner
        uasort($votes, fn($a, $b) => $b['weight'] <=> $a['weight']);
        $winner = array_values($votes)[0];

        $confidence = $totalWeight > 0 ? $winner['weight'] / $totalWeight : 0;

        return AgentResult::success(
            answer: $winner['raw'],
            messages: [],
            iterations: count($results),
            metadata: [
                'strategy' => 'weighted_voting',
                'votes' => $votes,
                'confidence' => round($confidence, 3),
                'agent_weights' => $weights,
            ]
        );
    }

    /**
     * Bootstrap aggregating - run subset of agents multiple times.
     */
    private function bagging(string $task, array $results, int $k): AgentResult
    {
        // For bagging, we would ideally run agents multiple times with different samples
        // For simplicity, we'll use weighted voting with bootstrap sampling
        $successfulResults = array_filter($results, fn($r) => $r->isSuccess());
        
        if (empty($successfulResults)) {
            return AgentResult::failure('All agents failed');
        }

        // Sample with replacement
        $samples = [];
        $agentIds = array_keys($successfulResults);
        
        for ($i = 0; $i < $k; $i++) {
            $randomIndex = array_rand($agentIds);
            $agentId = $agentIds[$randomIndex];
            $samples[$agentId] = $successfulResults[$agentId];
        }

        return $this->weightedVoting($task, $samples);
    }

    /**
     * Stacking - use meta-learner to combine results.
     */
    private function stacking(string $task, array $results): AgentResult
    {
        // Simplified stacking: score each result and pick best
        $scoredResults = [];
        
        foreach ($results as $agentId => $result) {
            if (!$result->isSuccess()) {
                continue;
            }

            $score = $this->scoreResult($result, $agentId);
            $scoredResults[$agentId] = [
                'result' => $result,
                'score' => $score,
            ];
        }

        if (empty($scoredResults)) {
            return AgentResult::failure('All agents failed');
        }

        uasort($scoredResults, fn($a, $b) => $b['score'] <=> $a['score']);
        $best = array_values($scoredResults)[0];

        return AgentResult::success(
            answer: $best['result']->getAnswer(),
            messages: [],
            iterations: count($results),
            metadata: [
                'strategy' => 'stacking',
                'best_agent' => array_keys($scoredResults)[0],
                'score' => $best['score'],
                'all_scores' => array_map(fn($s) => $s['score'], $scoredResults),
            ]
        );
    }

    /**
     * Best-of-N - select single best result.
     */
    private function bestOfN(string $task, array $results): AgentResult
    {
        $scoredResults = [];
        
        foreach ($results as $agentId => $result) {
            if (!$result->isSuccess()) {
                continue;
            }

            // Score based on metadata quality metrics
            $metadata = $result->getMetadata();
            $qualityScore = $metadata['quality_score'] ?? 5.0;
            $iterations = $result->getIterations();
            
            // Lower iterations (more efficient) is better
            $efficiencyScore = max(0, 10 - ($iterations * 0.5));
            
            $totalScore = ($qualityScore * 0.7) + ($efficiencyScore * 0.3);
            
            $scoredResults[$agentId] = [
                'result' => $result,
                'score' => $totalScore,
            ];
        }

        if (empty($scoredResults)) {
            return AgentResult::failure('All agents failed');
        }

        uasort($scoredResults, fn($a, $b) => $b['score'] <=> $a['score']);
        $best = array_values($scoredResults)[0];
        $bestAgentId = array_keys($scoredResults)[0];

        return AgentResult::success(
            answer: $best['result']->getAnswer(),
            messages: $best['result']->getMessages(),
            iterations: $best['result']->getIterations(),
            metadata: array_merge($best['result']->getMetadata(), [
                'ensemble_strategy' => 'best_of_n',
                'selected_agent' => $bestAgentId,
                'selection_score' => round($best['score'], 2),
                'all_scores' => array_map(fn($s) => round($s['score'], 2), $scoredResults),
            ])
        );
    }

    /**
     * Get agent weights from historical performance.
     */
    private function getAgentWeights(array $agentIds): array
    {
        $weights = [];
        $stats = $this->historyStore->getHistoryStats();
        
        foreach ($agentIds as $agentId) {
            // Calculate weight from success rate and quality
            $agentHistory = $this->historyStore->getHistory();
            $agentRecords = array_filter($agentHistory, fn($h) => $h['agent_id'] === $agentId);
            
            if (empty($agentRecords)) {
                $weights[$agentId] = 1.0; // Default weight
                continue;
            }

            $successRate = count(array_filter($agentRecords, fn($r) => $r['is_success'])) / count($agentRecords);
            $avgQuality = array_sum(array_column($agentRecords, 'quality_score')) / count($agentRecords);
            
            // Weight combines success rate and quality
            $weights[$agentId] = ($successRate * 0.5) + (($avgQuality / 10.0) * 0.5);
        }

        // Normalize weights
        $totalWeight = array_sum($weights);
        if ($totalWeight > 0) {
            foreach ($weights as $agentId => $weight) {
                $weights[$agentId] = $weight / $totalWeight * count($weights);
            }
        }

        return $weights;
    }

    /**
     * Score a result for stacking.
     */
    private function scoreResult(AgentResult $result, string $agentId): float
    {
        $metadata = $result->getMetadata();
        
        $qualityScore = $metadata['quality_score'] ?? 5.0;
        $iterations = $result->getIterations();
        $answerLength = strlen($result->getAnswer());
        
        // Base score from quality
        $score = $qualityScore;
        
        // Penalize too many iterations
        $score -= min(2, $iterations * 0.1);
        
        // Reward reasonable answer length
        if ($answerLength > 50 && $answerLength < 1000) {
            $score += 1.0;
        }
        
        // Historical performance boost
        $weights = $this->getAgentWeights([$agentId]);
        $score *= ($weights[$agentId] ?? 1.0);
        
        return max(0, $score);
    }

    /**
     * Normalize answer for comparison.
     */
    private function normalizeAnswer(string $answer): string
    {
        // Remove extra whitespace, lowercase, remove punctuation
        $normalized = strtolower(trim($answer));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        
        return substr($normalized, 0, 200); // Truncate for hashing
    }

    /**
     * Record ensemble performance.
     */
    private function recordEnsemblePerformance(
        string $task,
        string $strategy,
        array $individualResults,
        AgentResult $ensembleResult,
        float $duration
    ): void {
        try {
            if (!isset($this->embedder)) {
                return; // Can't embed without client
            }

            $taskEmbedding = $this->embedder->embed($task, []);
            
            $successCount = count(array_filter($individualResults, fn($r) => $r->isSuccess()));
            $qualityScore = $ensembleResult->getMetadata()['quality_score'] ?? 
                            ($ensembleResult->isSuccess() ? 8.0 : 0.0);
            
            $this->historyStore->recordTaskOutcome(
                task: $task,
                taskEmbedding: $taskEmbedding,
                agentId: "ensemble:{$strategy}",
                qualityScore: $qualityScore,
                isSuccess: $ensembleResult->isSuccess(),
                duration: $duration,
                metadata: [
                    'strategy' => $strategy,
                    'agent_count' => count($individualResults),
                    'successful_agents' => $successCount,
                    'ensemble_confidence' => $ensembleResult->getMetadata()['confidence'] ?? 0,
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error("Failed to record ensemble performance: {$e->getMessage()}");
        }
    }

    /**
     * Get ensemble statistics.
     */
    public function getStatistics(): array
    {
        return $this->historyStore->getHistoryStats();
    }

    /**
     * Set custom agent weights.
     */
    public function setAgentWeights(array $weights): self
    {
        $this->agentWeights = $weights;
        return $this;
    }

}

