<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\ML\Traits\LearnableAgent;
use ClaudeAgents\ML\Traits\ParameterOptimizer;
use ClaudeAgents\ML\Traits\StrategySelector;
use ClaudeAgents\Reasoning\Evaluator;
use ClaudeAgents\Reasoning\SearchStrategy;
use ClaudeAgents\Reasoning\ThoughtTree;
use ClaudePhp\ClaudePhp;

/**
 * Tree-of-Thoughts agent for multi-path exploration.
 *
 * **ML-Enhanced Features:**
 * - Learns optimal search strategy (BFS/DFS/Best-First) for different task types
 * - Learns optimal branch_count and max_depth parameters
 * - Tracks and improves performance over time
 *
 * @package ClaudeAgents\Agents
 */
class TreeOfThoughtsAgent extends AbstractAgent
{
    use LearnableAgent;
    use ParameterOptimizer;
    use StrategySelector;

    private int $branchCount;
    private int $maxDepth;
    private string $searchStrategy;
    private Evaluator $evaluator;
    private bool $useMLOptimization = false;

    protected const DEFAULT_NAME = 'tot_agent';

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - branch_count: How many thoughts per node (default: 3)
     *   - max_depth: Maximum tree depth (default: 4)
     *   - search_strategy: 'best_first', 'breadth_first', 'depth_first' (default: best_first)
     *   - model: Model to use
     *   - max_tokens: Max tokens per response
     *   - logger: PSR-3 logger
     *   - enable_ml_optimization: Enable ML-based parameter and strategy learning (default: false)
     *   - ml_history_path: Path for ML history storage
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        parent::__construct($client, $options);
    }

    /**
     * Initialize agent-specific configuration.
     *
     * @param array<string, mixed> $options
     */
    protected function initialize(array $options): void
    {
        $this->branchCount = $options['branch_count'] ?? 3;
        $this->maxDepth = $options['max_depth'] ?? 4;
        $this->searchStrategy = $options['search_strategy'] ?? 'best_first';
        $this->useMLOptimization = $options['enable_ml_optimization'] ?? false;

        $this->evaluator = new Evaluator(
            $this->client,
            '',
            'feasibility and likelihood of success',
            ['logger' => $this->logger]
        );

        // Enable ML features if requested
        if ($this->useMLOptimization) {
            $historyPath = $options['ml_history_path'] ?? 'storage/tot_history.json';
            
            $this->enableLearning($historyPath);
            
            $this->enableParameterOptimization(
                historyPath: str_replace('.json', '_params.json', $historyPath),
                defaults: [
                    'branch_count' => $this->branchCount,
                    'max_depth' => $this->maxDepth,
                ]
            );
            
            $this->enableStrategyLearning(
                strategies: ['best_first', 'breadth_first', 'depth_first'],
                defaultStrategy: $this->searchStrategy,
                historyPath: str_replace('.json', '_strategy.json', $historyPath)
            );
        }
    }

    public function run(string $task): AgentResult
    {
        $startTime = microtime(true);
        
        // Learn optimal parameters if ML enabled
        if ($this->useMLOptimization) {
            $learned = $this->learnOptimalParameters($task, ['branch_count', 'max_depth']);
            $branchCount = (int) ($learned['branch_count'] ?? $this->branchCount);
            $maxDepth = (int) ($learned['max_depth'] ?? $this->maxDepth);
            
            // Learn optimal strategy
            $strategyInfo = $this->getStrategyConfidence($task);
            $searchStrategy = $strategyInfo['strategy'];
            
            $this->logDebug("ML-optimized parameters: branch_count={$branchCount}, max_depth={$maxDepth}, strategy={$searchStrategy}");
            $this->logDebug("Strategy confidence: " . round($strategyInfo['confidence'] * 100, 1) . "%");
        } else {
            $branchCount = $this->branchCount;
            $maxDepth = $this->maxDepth;
            $searchStrategy = $this->searchStrategy;
        }
        
        $this->logStart($task, ['strategy' => $searchStrategy]);

        try {
            // Initialize thought tree
            $tree = new ThoughtTree($task);
            $evaluator = new Evaluator($this->client, $task, '', ['logger' => $this->logger]);

            // Explore the thought tree
            $frontier = [$tree->getRoot()];
            $totalTokens = ['input' => 0, 'output' => 0];

            for ($depth = 0; $depth < $maxDepth; $depth++) {
                $this->logDebug("Exploring depth {$depth}");

                $nextFrontier = [];

                foreach ($frontier as $node) {
                    // Generate child thoughts (using learned branch count)
                    $thoughts = $this->generateThoughts($task, $node->getThought(), $node->getDepth(), $branchCount);

                    foreach ($thoughts as $thought) {
                        $childNode = $tree->addThought($node, $thought);

                        // Evaluate the thought
                        $score = $evaluator->evaluate($thought);
                        $childNode->setScore($score);

                        $nextFrontier[] = $childNode;
                    }
                }

                // Prune frontier based on search strategy (using learned strategy)
                $frontier = match ($searchStrategy) {
                    'best_first' => SearchStrategy::bestFirst($nextFrontier, min(3, count($nextFrontier))),
                    'breadth_first' => SearchStrategy::breadthFirst($nextFrontier, 1),
                    'depth_first' => SearchStrategy::depthFirst($nextFrontier, $maxDepth),
                    default => SearchStrategy::bestFirst($nextFrontier, 3),
                };

                if (empty($frontier)) {
                    break;
                }
            }

            // Get the best path
            $bestPath = $tree->getBestPath();
            $answer = "Best solution path found:\n\n";

            foreach ($bestPath as $i => $node) {
                $answer .= 'Step ' . ($i + 1) . ' (Score: ' . round($node->getScore(), 2) . '): ' .
                          substr($node->getThought(), 0, 100) . "...\n\n";
            }

            $duration = microtime(true) - $startTime;
            $bestScore = $bestPath[count($bestPath) - 1]->getScore() ?? 0;
            
            $this->logSuccess([
                'total_nodes' => $tree->getNodeCount(),
                'path_length' => count($bestPath),
            ]);

            $result = AgentResult::success(
                answer: $answer,
                messages: [],
                iterations: 1,
                metadata: [
                    'strategy' => $searchStrategy,
                    'branch_count' => $branchCount,
                    'max_depth_used' => $maxDepth,
                    'total_nodes' => $tree->getNodeCount(),
                    'max_depth_reached' => $tree->getMaxDepth(),
                    'path_length' => count($bestPath),
                    'best_score' => $bestScore,
                    'tokens' => $totalTokens,
                    'ml_enabled' => $this->useMLOptimization,
                ],
            );

            // Record for ML learning (if enabled)
            if ($this->useMLOptimization) {
                $this->recordExecution($task, $result, [
                    'duration' => $duration,
                    'best_score' => $bestScore,
                ]);
                
                $this->recordParameterPerformance(
                    $task,
                    parameters: [
                        'branch_count' => $branchCount,
                        'max_depth' => $maxDepth,
                    ],
                    success: true,
                    qualityScore: min(10, $bestScore * 2), // Scale score to 0-10
                    duration: $duration
                );
                
                $this->recordStrategyPerformance(
                    $task,
                    strategy: $searchStrategy,
                    success: true,
                    qualityScore: min(10, $bestScore * 2),
                    duration: $duration,
                    additionalMetadata: [
                        'total_nodes' => $tree->getNodeCount(),
                        'path_length' => count($bestPath),
                    ]
                );
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logError($e->getMessage());

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Generate multiple thought branches.
     *
     * @param string $problem The original problem
     * @param string $context Current context/thought
     * @param int $depth Current depth in tree
     * @param int $branchCount Number of branches to generate
     * @return array<string> Generated thoughts
     */
    private function generateThoughts(string $problem, string $context, int $depth, int $branchCount): array
    {
        try {
            $prompt = "Problem: {$problem}\n\n";

            if ($depth > 0) {
                $prompt .= "Current context: {$context}\n\n";
            }

            $prompt .= "Generate {$branchCount} different approaches or next steps. " .
                      "For each, provide briefly:\n" .
                      "1. The approach/step\n" .
                      "2. Why this might work\n\n" .
                      "Format as:\n";

            for ($i = 1; $i <= $branchCount; $i++) {
                $prompt .= "Approach {$i}: [description]\n";
            }

            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => 1024,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $text = $this->extractTextContent($response);

            // Parse approaches from response
            $approaches = [];
            if (preg_match_all('/Approach \d+:\s*([^\n]+)/', $text, $matches)) {
                $approaches = $matches[1];
            }

            // If parsing fails, split by lines and filter
            if (empty($approaches)) {
                $lines = array_filter(
                    array_map('trim', explode("\n", $text)),
                    fn ($l) => ! empty($l) && strlen($l) > 10
                );
                $approaches = array_slice($lines, 0, $branchCount);
            }

            return array_slice($approaches, 0, $branchCount);
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to generate thoughts: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Override to customize task analysis for learning.
     */
    protected function analyzeTaskForLearning(string $task): array
    {
        $wordCount = str_word_count($task);
        $length = strlen($task);

        return [
            'complexity' => match (true) {
                $length > 300 || $wordCount > 50 => 'complex',
                $length > 100 || $wordCount > 20 => 'medium',
                default => 'simple',
            },
            'domain' => 'reasoning',
            'requires_tools' => false,
            'requires_knowledge' => false,
            'requires_reasoning' => true,
            'requires_iteration' => true,
            'requires_quality' => 'high',
            'estimated_steps' => max(3, min(20, (int) ($wordCount / 10))),
            'key_requirements' => ['exploration', 'evaluation', 'selection'],
        ];
    }

    /**
     * Override to evaluate thought tree quality.
     */
    protected function evaluateResultQuality(AgentResult $result): float
    {
        if (!$result->isSuccess()) {
            return 0.0;
        }

        $metadata = $result->getMetadata();
        $bestScore = $metadata['best_score'] ?? 0;

        // Tree of Thoughts quality based on best path score
        return min(10, max(0, $bestScore * 2));
    }
}
