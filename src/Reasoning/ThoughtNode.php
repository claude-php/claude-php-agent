<?php

declare(strict_types=1);

namespace ClaudeAgents\Reasoning;

/**
 * Represents a single node in the thought tree.
 */
class ThoughtNode
{
    /**
     * @var array<ThoughtNode>
     */
    private array $children = [];

    /**
     * @param string $id Unique node identifier
     * @param string $thought The thought/idea at this node
     * @param int $depth Depth in the tree (0 is root)
     * @param float $score Evaluation score
     * @param ThoughtNode|null $parent Parent node
     */
    public function __construct(
        private readonly string $id,
        private readonly string $thought,
        private readonly int $depth,
        private float $score = 0.0,
        private ?ThoughtNode $parent = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getThought(): string
    {
        return $this->thought;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): void
    {
        $this->score = $score;
    }

    public function getParent(): ?ThoughtNode
    {
        return $this->parent;
    }

    /**
     * @return array<ThoughtNode>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Add a child node.
     */
    public function addChild(ThoughtNode $child): void
    {
        $this->children[] = $child;
    }

    /**
     * Get all descendant nodes.
     *
     * @return array<ThoughtNode>
     */
    public function getDescendants(): array
    {
        $descendants = [];

        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getDescendants());
        }

        return $descendants;
    }

    /**
     * Get the path from root to this node.
     *
     * @return array<ThoughtNode>
     */
    public function getPath(): array
    {
        $path = [$this];
        $current = $this;

        while ($current->parent !== null) {
            array_unshift($path, $current->parent);
            $current = $current->parent;
        }

        return $path;
    }

    /**
     * Check if this is a leaf node.
     */
    public function isLeaf(): bool
    {
        return empty($this->children);
    }

    /**
     * Get child count.
     */
    public function getChildCount(): int
    {
        return count($this->children);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'thought' => $this->thought,
            'depth' => $this->depth,
            'score' => $this->score,
            'is_leaf' => $this->isLeaf(),
            'child_count' => $this->getChildCount(),
        ];
    }
}
