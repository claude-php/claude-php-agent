<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Support\TextContentExtractor;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Learning Agent - Adapts behavior based on experience and feedback.
 *
 * Maintains experience replay, incorporates feedback, tracks performance,
 * and adapts strategies over time.
 */
class LearningAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $experiences = [];
    private array $performance = [];
    private array $strategies = [];
    private float $learningRate;
    private int $replayBufferSize;
    private LoggerInterface $logger;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - learning_rate: Learning rate (0-1, default: 0.1)
     *   - replay_buffer_size: Max experiences to keep (default: 1000)
     *   - initial_strategies: Array of strategy names
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'learning_agent';
        $this->learningRate = $options['learning_rate'] ?? 0.1;
        $this->replayBufferSize = $options['replay_buffer_size'] ?? 1000;
        $this->strategies = $options['initial_strategies'] ?? ['default'];
        $this->logger = $options['logger'] ?? new NullLogger();

        // Initialize performance tracking for each strategy
        foreach ($this->strategies as $strategy) {
            $this->performance[$strategy] = [
                'attempts' => 0,
                'successes' => 0,
                'total_reward' => 0.0,
                'avg_reward' => 0.0,
            ];
        }
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info("Learning agent: {$task}");

        try {
            // Select strategy based on performance
            $strategy = $this->selectStrategy();

            // Execute task with selected strategy
            $result = $this->executeWithStrategy($task, $strategy);

            // Record experience (will be updated with feedback later)
            $experienceId = $this->recordExperience($task, $strategy, $result);

            return AgentResult::success(
                answer: $result,
                messages: [],
                iterations: 1,
                metadata: [
                    'strategy_used' => $strategy,
                    'experience_id' => $experienceId,
                    'performance' => $this->performance,
                    'total_experiences' => count($this->experiences),
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Learning agent failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Provide feedback on a previous experience.
     *
     * @param string $experienceId Experience ID to update
     * @param float $reward Reward value (-1 to 1)
     * @param bool $success Whether the outcome was successful
     * @param array $feedback Additional feedback data
     */
    public function provideFeedback(
        string $experienceId,
        float $reward,
        bool $success,
        array $feedback = []
    ): void {
        $experience = $this->findExperience($experienceId);

        if (! $experience) {
            $this->logger->warning("Experience not found: {$experienceId}");

            return;
        }

        // Update experience with feedback
        $this->experiences[$experienceId]['reward'] = $reward;
        $this->experiences[$experienceId]['success'] = $success;
        $this->experiences[$experienceId]['feedback'] = $feedback;
        $this->experiences[$experienceId]['feedback_timestamp'] = microtime(true);

        // Update strategy performance
        $strategy = $experience['strategy'];
        $this->updatePerformance($strategy, $reward, $success);

        $this->logger->info("Feedback recorded for experience {$experienceId}", [
            'reward' => $reward,
            'success' => $success,
        ]);

        // Trigger learning if we have enough experiences
        if (count($this->experiences) % 10 === 0) {
            $this->learn();
        }
    }

    /**
     * Add a new strategy.
     */
    public function addStrategy(string $strategy): void
    {
        if (! in_array($strategy, $this->strategies)) {
            $this->strategies[] = $strategy;
            $this->performance[$strategy] = [
                'attempts' => 0,
                'successes' => 0,
                'total_reward' => 0.0,
                'avg_reward' => 0.0,
            ];

            $this->logger->info("Added new strategy: {$strategy}");
        }
    }

    /**
     * Get performance statistics.
     *
     * @return array<string, array>
     */
    public function getPerformance(): array
    {
        return $this->performance;
    }

    /**
     * Get recent experiences.
     *
     * @return array<array>
     */
    public function getExperiences(int $limit = 100): array
    {
        return array_slice($this->experiences, -$limit);
    }

    /**
     * Select strategy based on performance (epsilon-greedy).
     */
    private function selectStrategy(): string
    {
        // Exploration: 10% of the time, try a random strategy
        if (mt_rand() / mt_getrandmax() < 0.1) {
            return $this->strategies[array_rand($this->strategies)];
        }

        // Exploitation: Select best performing strategy
        $bestStrategy = null;
        $bestReward = -INF;

        foreach ($this->performance as $strategy => $perf) {
            if ($perf['attempts'] === 0) {
                // Always try untested strategies
                return $strategy;
            }

            if ($perf['avg_reward'] > $bestReward) {
                $bestReward = $perf['avg_reward'];
                $bestStrategy = $strategy;
            }
        }

        return $bestStrategy ?? $this->strategies[0];
    }

    /**
     * Execute task with a specific strategy.
     */
    private function executeWithStrategy(string $task, string $strategy): string
    {
        $prompt = $this->buildStrategyPrompt($task, $strategy);

        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 1024,
            'system' => "You are using the '{$strategy}' strategy to solve problems.",
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return TextContentExtractor::extractFromResponse($response);
    }

    /**
     * Build prompt incorporating strategy and past experiences.
     */
    private function buildStrategyPrompt(string $task, string $strategy): string
    {
        // Include relevant past experiences
        $similarExperiences = $this->findSimilarExperiences($task, 3);
        $experienceContext = $this->formatExperiences($similarExperiences);

        return <<<PROMPT
            Task: {$task}

            Strategy: {$strategy}

            {$experienceContext}

            Solve the task using the specified strategy.
            PROMPT;
    }

    /**
     * Record an experience.
     */
    private function recordExperience(string $task, string $strategy, string $result): string
    {
        $id = uniqid('exp_', true);

        $this->experiences[$id] = [
            'id' => $id,
            'task' => $task,
            'strategy' => $strategy,
            'result' => $result,
            'timestamp' => microtime(true),
            'reward' => null,
            'success' => null,
            'feedback' => [],
        ];

        // Limit replay buffer size
        if (count($this->experiences) > $this->replayBufferSize) {
            array_shift($this->experiences);
        }

        return $id;
    }

    /**
     * Find an experience by ID.
     */
    private function findExperience(string $id): ?array
    {
        return $this->experiences[$id] ?? null;
    }

    /**
     * Update performance for a strategy.
     */
    private function updatePerformance(string $strategy, float $reward, bool $success): void
    {
        $perf = &$this->performance[$strategy];

        $perf['attempts']++;
        if ($success) {
            $perf['successes']++;
        }

        $perf['total_reward'] += $reward;
        $perf['avg_reward'] = $perf['total_reward'] / $perf['attempts'];
    }

    /**
     * Learn from experiences (experience replay).
     */
    private function learn(): void
    {
        $this->logger->info('Learning from experiences...');

        // Analyze successful vs unsuccessful experiences
        $successfulExperiences = array_filter(
            $this->experiences,
            fn ($exp) => $exp['success'] === true
        );

        $failedExperiences = array_filter(
            $this->experiences,
            fn ($exp) => $exp['success'] === false
        );

        $this->logger->info('Success rate: ' .
            count($successfulExperiences) . '/' . count($this->experiences));

        // 1. Pattern Recognition - Identify successful patterns
        $this->identifySuccessPatterns($successfulExperiences);

        // 2. Strategy Evolution - Adjust strategy preferences
        $this->evolveStrategies();

        // 3. Parameter Tuning - Adjust learning rate based on performance
        $this->tuneParameters();

        // 4. Experience pruning - Remove low-value experiences
        $this->pruneExperiences();
    }

    /**
     * Identify patterns in successful experiences.
     *
     * @param array<array> $successfulExperiences
     */
    private function identifySuccessPatterns(array $successfulExperiences): void
    {
        if (empty($successfulExperiences)) {
            return;
        }

        // Group by strategy
        $strategySuccess = [];
        foreach ($successfulExperiences as $exp) {
            $strategy = $exp['strategy'];
            if (! isset($strategySuccess[$strategy])) {
                $strategySuccess[$strategy] = 0;
            }
            $strategySuccess[$strategy]++;
        }

        // Log most successful strategies
        arsort($strategySuccess);
        $topStrategies = array_slice($strategySuccess, 0, 3, true);

        $this->logger->info('Top performing strategies:', $topStrategies);
    }

    /**
     * Evolve strategies based on performance.
     */
    private function evolveStrategies(): void
    {
        // Remove consistently poor-performing strategies
        foreach ($this->performance as $strategy => $perf) {
            if ($perf['attempts'] >= 10 && $perf['avg_reward'] < -0.5) {
                $this->logger->info("Removing poorly performing strategy: {$strategy}");
                $this->removeStrategy($strategy);
            }
        }

        // Suggest new strategies based on gaps
        $avgReward = $this->calculateAverageReward();
        if ($avgReward < 0.3 && count($this->strategies) < 10) {
            $newStrategy = $this->suggestNewStrategy();
            if ($newStrategy) {
                $this->addStrategy($newStrategy);
                $this->logger->info("Suggested new strategy: {$newStrategy}");
            }
        }
    }

    /**
     * Remove a strategy.
     */
    private function removeStrategy(string $strategy): void
    {
        $key = array_search($strategy, $this->strategies);
        if ($key !== false) {
            unset($this->strategies[$key]);
            $this->strategies = array_values($this->strategies); // Re-index
            unset($this->performance[$strategy]);
        }
    }

    /**
     * Calculate overall average reward.
     */
    private function calculateAverageReward(): float
    {
        $totalReward = 0.0;
        $totalAttempts = 0;

        foreach ($this->performance as $perf) {
            $totalReward += $perf['total_reward'];
            $totalAttempts += $perf['attempts'];
        }

        return $totalAttempts > 0 ? $totalReward / $totalAttempts : 0.0;
    }

    /**
     * Suggest a new strategy based on current performance gaps.
     */
    private function suggestNewStrategy(): ?string
    {
        // Analyze task patterns and suggest complementary strategies
        $taskPatterns = $this->analyzeTaskPatterns();

        $potentialStrategies = [
            'analytical' => 0,
            'creative' => 0,
            'systematic' => 0,
            'adaptive' => 0,
            'collaborative' => 0,
        ];

        // Score strategies based on what's missing
        foreach ($taskPatterns as $pattern => $count) {
            if (str_contains($pattern, 'analyze') || str_contains($pattern, 'calculate')) {
                $potentialStrategies['analytical'] += $count;
            }
            if (str_contains($pattern, 'create') || str_contains($pattern, 'design')) {
                $potentialStrategies['creative'] += $count;
            }
            if (str_contains($pattern, 'step') || str_contains($pattern, 'process')) {
                $potentialStrategies['systematic'] += $count;
            }
        }

        // Find strategy not currently used
        arsort($potentialStrategies);
        foreach ($potentialStrategies as $strategy => $score) {
            if (! in_array($strategy, $this->strategies) && $score > 0) {
                return $strategy;
            }
        }

        return null;
    }

    /**
     * Analyze patterns in task descriptions.
     *
     * @return array<string, int>
     */
    private function analyzeTaskPatterns(): array
    {
        $patterns = [];

        foreach ($this->experiences as $exp) {
            $words = str_word_count(strtolower($exp['task']), 1);
            foreach ($words as $word) {
                if (strlen($word) > 4) { // Only significant words
                    $patterns[$word] = ($patterns[$word] ?? 0) + 1;
                }
            }
        }

        return $patterns;
    }

    /**
     * Tune learning parameters based on performance.
     */
    private function tuneParameters(): void
    {
        $avgReward = $this->calculateAverageReward();

        // Adjust learning rate based on stability
        if ($avgReward > 0.5) {
            // Good performance - reduce exploration
            $this->learningRate = max(0.05, $this->learningRate * 0.95);
        } elseif ($avgReward < 0.0) {
            // Poor performance - increase exploration
            $this->learningRate = min(0.3, $this->learningRate * 1.05);
        }

        $this->logger->debug("Learning rate adjusted to: {$this->learningRate}");
    }

    /**
     * Prune low-value experiences to maintain buffer quality.
     */
    private function pruneExperiences(): void
    {
        if (count($this->experiences) < $this->replayBufferSize * 0.9) {
            return; // Buffer not full enough to prune
        }

        // Score experiences by value
        $scoredExperiences = [];
        foreach ($this->experiences as $id => $exp) {
            if ($exp['reward'] === null) {
                continue; // Keep unlabeled experiences for now
            }

            // Value = |reward| + recency_bonus
            $recency = (microtime(true) - $exp['timestamp']) / 3600; // Hours ago
            $recencyBonus = max(0, 1 - ($recency / 24)); // Decay over 24 hours

            $value = abs($exp['reward']) + ($recencyBonus * 0.5);
            $scoredExperiences[$id] = $value;
        }

        // Remove lowest value experiences
        asort($scoredExperiences);
        $toRemove = (int)(count($scoredExperiences) * 0.1); // Remove bottom 10%
        $removeIds = array_slice(array_keys($scoredExperiences), 0, $toRemove);

        foreach ($removeIds as $id) {
            unset($this->experiences[$id]);
        }

        $this->logger->debug("Pruned {$toRemove} low-value experiences");
    }

    /**
     * Find similar past experiences.
     *
     * @return array<array>
     */
    private function findSimilarExperiences(string $task, int $limit): array
    {
        // Simple similarity: check for common words
        $taskWords = str_word_count(strtolower($task), 1);
        $similarities = [];

        foreach ($this->experiences as $exp) {
            if ($exp['reward'] === null) {
                continue; // Skip experiences without feedback
            }

            $expWords = str_word_count(strtolower($exp['task']), 1);
            $commonWords = count(array_intersect($taskWords, $expWords));

            if ($commonWords > 0) {
                $similarities[] = [
                    'experience' => $exp,
                    'similarity' => $commonWords,
                ];
            }
        }

        // Sort by similarity
        usort($similarities, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice(
            array_column($similarities, 'experience'),
            0,
            $limit
        );
    }

    /**
     * Format experiences for context.
     */
    private function formatExperiences(array $experiences): string
    {
        if (empty($experiences)) {
            return 'No relevant past experiences.';
        }

        $formatted = "Relevant past experiences:\n";

        foreach ($experiences as $i => $exp) {
            $outcome = $exp['success'] ? 'Success' : 'Failure';
            $formatted .= ($i + 1) . ". Task: {$exp['task']}\n";
            $formatted .= "   Strategy: {$exp['strategy']}, Outcome: {$outcome}, Reward: {$exp['reward']}\n";
        }

        return $formatted;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
