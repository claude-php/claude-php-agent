<?php

declare(strict_types=1);

namespace ClaudeAgents\Reasoning;

/**
 * Tree structure for organizing and traversing thoughts.
 */
class ThoughtTree
{
    private ThoughtNode $root;
    private int $nodeCounter = 0;

    /**
     * @param string $rootThought The root thought
     */
    public function __construct(string $rootThought)
    {
        $this->root = new ThoughtNode('node_0', $rootThought, 0);
        $this->nodeCounter = 1;
    }

    public function getRoot(): ThoughtNode
    {
        return $this->root;
    }

    /**
     * Add a thought as a child of a parent node.
     *
     * @param ThoughtNode $parent The parent node
     * @param string $thought The child thought
     * @return ThoughtNode The newly created node
     */
    public function addThought(ThoughtNode $parent, string $thought): ThoughtNode
    {
        $child = new ThoughtNode(
            id: 'node_' . $this->nodeCounter++,
            thought: $thought,
            depth: $parent->getDepth() + 1,
            parent: $parent,
        );

        $parent->addChild($child);

        return $child;
    }

    /**
     * Get all nodes at a specific depth.
     *
     * @param int $depth The depth level
     * @return array<ThoughtNode>
     */
    public function getNodesByDepth(int $depth): array
    {
        if ($depth === 0) {
            return [$this->root];
        }

        $nodes = [];
        $this->collectNodesByDepth($this->root, $depth, $nodes);

        return $nodes;
    }

    /**
     * Collect nodes at a specific depth recursively.
     *
     * @param array<ThoughtNode> $result
     */
    private function collectNodesByDepth(ThoughtNode $node, int $targetDepth, array &$result): void
    {
        if ($node->getDepth() === $targetDepth) {
            $result[] = $node;

            return;
        }

        if ($node->getDepth() < $targetDepth) {
            foreach ($node->getChildren() as $child) {
                $this->collectNodesByDepth($child, $targetDepth, $result);
            }
        }
    }

    /**
     * Get all leaf nodes.
     *
     * @return array<ThoughtNode>
     */
    public function getLeafNodes(): array
    {
        $leaves = [];
        $this->collectLeaves($this->root, $leaves);

        return $leaves;
    }

    /**
     * Collect all leaf nodes recursively.
     *
     * @param array<ThoughtNode> $result
     */
    private function collectLeaves(ThoughtNode $node, array &$result): void
    {
        if ($node->isLeaf()) {
            $result[] = $node;
        } else {
            foreach ($node->getChildren() as $child) {
                $this->collectLeaves($child, $result);
            }
        }
    }

    /**
     * Get the best nodes at a specific depth by score.
     *
     * @param int $depth The depth level
     * @param int $topK Number of top nodes to return
     * @return array<ThoughtNode>
     */
    public function getTopNodesByDepth(int $depth, int $topK = 3): array
    {
        $nodes = $this->getNodesByDepth($depth);

        usort($nodes, fn ($a, $b) => $b->getScore() <=> $a->getScore());

        return array_slice($nodes, 0, $topK);
    }

    /**
     * Get the highest-scored path from root to a leaf.
     *
     * @return array<ThoughtNode>
     */
    public function getBestPath(): array
    {
        return $this->findBestPath($this->root);
    }

    /**
     * Find the best path recursively.
     *
     * @return array<ThoughtNode>
     */
    private function findBestPath(ThoughtNode $node): array
    {
        if ($node->isLeaf()) {
            return [$node];
        }

        // Find child with highest score
        $bestChild = null;
        $bestScore = -1;

        foreach ($node->getChildren() as $child) {
            if ($child->getScore() > $bestScore) {
                $bestScore = $child->getScore();
                $bestChild = $child;
            }
        }

        if ($bestChild === null) {
            return [$node];
        }

        return array_merge([$node], $this->findBestPath($bestChild));
    }

    /**
     * Get the maximum depth of the tree.
     */
    public function getMaxDepth(): int
    {
        return $this->findMaxDepth($this->root);
    }

    /**
     * Find maximum depth recursively.
     */
    private function findMaxDepth(ThoughtNode $node): int
    {
        if ($node->isLeaf()) {
            return $node->getDepth();
        }

        $maxChildDepth = $node->getDepth();
        foreach ($node->getChildren() as $child) {
            $maxChildDepth = max($maxChildDepth, $this->findMaxDepth($child));
        }

        return $maxChildDepth;
    }

    /**
     * Get total node count.
     */
    public function getNodeCount(): int
    {
        return $this->nodeCounter;
    }

    /**
     * Convert tree to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'root' => $this->root->toArray(),
            'total_nodes' => $this->getNodeCount(),
            'max_depth' => $this->getMaxDepth(),
            'leaf_count' => count($this->getLeafNodes()),
        ];
    }
}
