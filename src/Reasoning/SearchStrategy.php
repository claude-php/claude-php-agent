<?php

declare(strict_types=1);

namespace ClaudeAgents\Reasoning;

/**
 * Search strategies for exploring thought trees.
 */
class SearchStrategy
{
    public const BREADTH_FIRST = 'breadth_first';
    public const DEPTH_FIRST = 'depth_first';
    public const BEST_FIRST = 'best_first';

    /**
     * Get next nodes to expand using breadth-first search.
     *
     * @param array<ThoughtNode> $frontier Current frontier of nodes
     * @param int $branchCount How many children to generate per node
     * @return array<ThoughtNode> Next nodes to expand
     */
    public static function breadthFirst(array $frontier, int $branchCount): array
    {
        // All frontier nodes expand, return their children
        $next = [];

        foreach ($frontier as $node) {
            // In practice, children would be generated and added
            // This shows the interface
            foreach ($node->getChildren() as $child) {
                $next[] = $child;
            }
        }

        return $next;
    }

    /**
     * Get next nodes using depth-first search.
     *
     * @param array<ThoughtNode> $frontier Current frontier
     * @param int $maxDepth Maximum depth to explore
     * @return array<ThoughtNode> Deepest unvisited nodes
     */
    public static function depthFirst(array $frontier, int $maxDepth = 10): array
    {
        $next = [];

        foreach ($frontier as $node) {
            // Continue down first child if within depth limit
            if ($node->getDepth() < $maxDepth) {
                $children = $node->getChildren();
                if (! empty($children)) {
                    $next[] = $children[0]; // Follow first child
                }
            }
        }

        return $next;
    }

    /**
     * Get next nodes using best-first search (expand highest-scored nodes).
     *
     * @param array<ThoughtNode> $frontier Current frontier
     * @param int $topK How many top nodes to expand
     * @return array<ThoughtNode> Top-scored nodes
     */
    public static function bestFirst(array $frontier, int $topK = 3): array
    {
        // Sort by score descending
        usort($frontier, fn ($a, $b) => $b->getScore() <=> $a->getScore());

        // Return top K and their children
        $next = [];

        foreach (array_slice($frontier, 0, $topK) as $node) {
            foreach ($node->getChildren() as $child) {
                $next[] = $child;
            }
        }

        return $next;
    }

    /**
     * Check if a strategy name is valid.
     */
    public static function isValid(string $strategy): bool
    {
        return in_array($strategy, [
            self::BREADTH_FIRST,
            self::DEPTH_FIRST,
            self::BEST_FIRST,
        ], true);
    }
}
