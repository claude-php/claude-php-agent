<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Reasoning\Evaluator;
use ClaudeAgents\Reasoning\SearchStrategy;
use ClaudeAgents\Reasoning\ThoughtTree;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Tree-of-Thoughts agent for multi-path exploration.
 */
class TreeOfThoughtsAgent implements AgentInterface
{
    private string $name;
    private int $branchCount;
    private int $maxDepth;
    private string $searchStrategy;
    private LoggerInterface $logger;
    private Evaluator $evaluator;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - branch_count: How many thoughts per node (default: 3)
     *   - max_depth: Maximum tree depth (default: 4)
     *   - search_strategy: 'best_first', 'breadth_first', 'depth_first' (default: best_first)
     *   - logger: PSR-3 logger
     */
    public function __construct(
        private readonly ClaudePhp $client,
        array $options = [],
    ) {
        $this->name = $options['name'] ?? 'tot_agent';
        $this->branchCount = $options['branch_count'] ?? 3;
        $this->maxDepth = $options['max_depth'] ?? 4;
        $this->searchStrategy = $options['search_strategy'] ?? 'best_first';
        $this->logger = $options['logger'] ?? new NullLogger();

        $this->evaluator = new Evaluator($client, '', 'feasibility and likelihood of success', ['logger' => $this->logger]);
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info("ToT Agent: {$task}");

        try {
            // Initialize thought tree
            $tree = new ThoughtTree($task);
            $evaluator = new Evaluator($this->client, $task, '', ['logger' => $this->logger]);

            // Explore the thought tree
            $frontier = [$tree->getRoot()];
            $totalTokens = ['input' => 0, 'output' => 0];

            for ($depth = 0; $depth < $this->maxDepth; $depth++) {
                $this->logger->debug("Exploring depth {$depth}");

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
            $this->logger->error("ToT Agent failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    public function getName(): string
    {
        return $this->name;
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
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 1024,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $text = $this->extractTextContent($response->content ?? []);

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

    /**
     * Extract text content from response blocks.
     *
     * @param array<mixed> $content
     */
    private function extractTextContent(array $content): string
    {
        $texts = [];

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }
}
