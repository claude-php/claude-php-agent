<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Reasoning\Evaluator;
use ClaudeAgents\Reasoning\SearchStrategy;
use ClaudeAgents\Reasoning\ThoughtTree;
use ClaudePhp\ClaudePhp;

/**
 * Tree-of-Thoughts agent for multi-path exploration.
 */
class TreeOfThoughtsAgent extends AbstractAgent
{
    private int $branchCount;
    private int $maxDepth;
    private string $searchStrategy;
    private Evaluator $evaluator;

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

        $this->evaluator = new Evaluator(
            $this->client,
            '',
            'feasibility and likelihood of success',
            ['logger' => $this->logger]
        );
    }

    public function run(string $task): AgentResult
    {
        $this->logStart($task, ['strategy' => $this->searchStrategy]);

        try {
            // Initialize thought tree
            $tree = new ThoughtTree($task);
            $evaluator = new Evaluator($this->client, $task, '', ['logger' => $this->logger]);

            // Explore the thought tree
            $frontier = [$tree->getRoot()];
            $totalTokens = ['input' => 0, 'output' => 0];

            for ($depth = 0; $depth < $this->maxDepth; $depth++) {
                $this->logDebug("Exploring depth {$depth}");

                $nextFrontier = [];

                foreach ($frontier as $node) {
                    // Generate child thoughts
                    $thoughts = $this->generateThoughts($task, $node->getThought(), $node->getDepth());

                    foreach ($thoughts as $thought) {
                        $childNode = $tree->addThought($node, $thought);

                        // Evaluate the thought
                        $score = $evaluator->evaluate($thought);
                        $childNode->setScore($score);

                        $nextFrontier[] = $childNode;
                    }
                }

                // Prune frontier based on search strategy
                $frontier = match ($this->searchStrategy) {
                    'best_first' => SearchStrategy::bestFirst($nextFrontier, min(3, count($nextFrontier))),
                    'breadth_first' => SearchStrategy::breadthFirst($nextFrontier, 1),
                    'depth_first' => SearchStrategy::depthFirst($nextFrontier, $this->maxDepth),
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

            $this->logSuccess([
                'total_nodes' => $tree->getNodeCount(),
                'path_length' => count($bestPath),
            ]);

            return AgentResult::success(
                answer: $answer,
                messages: [],
                iterations: 1,
                metadata: [
                    'strategy' => $this->searchStrategy,
                    'total_nodes' => $tree->getNodeCount(),
                    'max_depth' => $tree->getMaxDepth(),
                    'path_length' => count($bestPath),
                    'best_score' => $bestPath[count($bestPath) - 1]->getScore() ?? 0,
                    'tokens' => $totalTokens,
                ],
            );
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
     * @return array<string> Generated thoughts
     */
    private function generateThoughts(string $problem, string $context, int $depth): array
    {
        try {
            $prompt = "Problem: {$problem}\n\n";

            if ($depth > 0) {
                $prompt .= "Current context: {$context}\n\n";
            }

            $prompt .= "Generate {$this->branchCount} different approaches or next steps. " .
                      "For each, provide briefly:\n" .
                      "1. The approach/step\n" .
                      "2. Why this might work\n\n" .
                      "Format as:\n";

            for ($i = 1; $i <= $this->branchCount; $i++) {
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
                $approaches = array_slice($lines, 0, $this->branchCount);
            }

            return array_slice($approaches, 0, $this->branchCount);
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to generate thoughts: {$e->getMessage()}");

            return [];
        }
    }
}
