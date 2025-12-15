<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Reasoning;

use ClaudeAgents\Reasoning\ThoughtNode;
use ClaudeAgents\Reasoning\ThoughtTree;
use PHPUnit\Framework\TestCase;

class ThoughtTreeTest extends TestCase
{
    public function testConstructorCreatesRootNode(): void
    {
        $tree = new ThoughtTree('Root thought');

        $root = $tree->getRoot();
        $this->assertInstanceOf(ThoughtNode::class, $root);
        $this->assertSame('Root thought', $root->getThought());
        $this->assertSame(0, $root->getDepth());
    }

    public function testGetRoot(): void
    {
        $tree = new ThoughtTree('Test root');

        $root = $tree->getRoot();
        $this->assertSame('Test root', $root->getThought());
    }

    public function testAddThought(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child = $tree->addThought($root, 'Child thought');

        $this->assertInstanceOf(ThoughtNode::class, $child);
        $this->assertSame('Child thought', $child->getThought());
        $this->assertSame(1, $child->getDepth());
        $this->assertSame($root, $child->getParent());
        $this->assertContains($child, $root->getChildren());
    }

    public function testAddMultipleChildren(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child1 = $tree->addThought($root, 'Child 1');
        $child2 = $tree->addThought($root, 'Child 2');
        $child3 = $tree->addThought($root, 'Child 3');

        $children = $root->getChildren();
        $this->assertCount(3, $children);
        $this->assertContains($child1, $children);
        $this->assertContains($child2, $children);
        $this->assertContains($child3, $children);
    }

    public function testAddThoughtMultipleDepths(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child = $tree->addThought($root, 'Child');
        $grandchild = $tree->addThought($child, 'Grandchild');
        $greatGrandchild = $tree->addThought($grandchild, 'Great-grandchild');

        $this->assertSame(0, $root->getDepth());
        $this->assertSame(1, $child->getDepth());
        $this->assertSame(2, $grandchild->getDepth());
        $this->assertSame(3, $greatGrandchild->getDepth());
    }

    public function testGetNodesByDepthZero(): void
    {
        $tree = new ThoughtTree('Root');

        $nodes = $tree->getNodesByDepth(0);

        $this->assertCount(1, $nodes);
        $this->assertSame($tree->getRoot(), $nodes[0]);
    }

    public function testGetNodesByDepthOne(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child1 = $tree->addThought($root, 'Child 1');
        $child2 = $tree->addThought($root, 'Child 2');

        $nodes = $tree->getNodesByDepth(1);

        $this->assertCount(2, $nodes);
        $this->assertContains($child1, $nodes);
        $this->assertContains($child2, $nodes);
    }

    public function testGetNodesByDepthTwo(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child1 = $tree->addThought($root, 'Child 1');
        $child2 = $tree->addThought($root, 'Child 2');
        $grandchild1 = $tree->addThought($child1, 'Grandchild 1');
        $grandchild2 = $tree->addThought($child2, 'Grandchild 2');

        $nodes = $tree->getNodesByDepth(2);

        $this->assertCount(2, $nodes);
        $this->assertContains($grandchild1, $nodes);
        $this->assertContains($grandchild2, $nodes);
    }

    public function testGetLeafNodes(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child1 = $tree->addThought($root, 'Child 1');
        $child2 = $tree->addThought($root, 'Child 2');
        $grandchild = $tree->addThought($child1, 'Grandchild');

        $leaves = $tree->getLeafNodes();

        $this->assertCount(2, $leaves);
        $this->assertContains($child2, $leaves);
        $this->assertContains($grandchild, $leaves);
        $this->assertNotContains($root, $leaves);
        $this->assertNotContains($child1, $leaves);
    }

    public function testGetLeafNodesRootOnly(): void
    {
        $tree = new ThoughtTree('Root');

        $leaves = $tree->getLeafNodes();

        $this->assertCount(1, $leaves);
        $this->assertSame($tree->getRoot(), $leaves[0]);
    }

    public function testGetTopNodesByDepth(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child1 = $tree->addThought($root, 'Child 1');
        $child2 = $tree->addThought($root, 'Child 2');
        $child3 = $tree->addThought($root, 'Child 3');

        $child1->setScore(5.0);
        $child2->setScore(8.0);
        $child3->setScore(3.0);

        $topNodes = $tree->getTopNodesByDepth(1, 2);

        $this->assertCount(2, $topNodes);
        $this->assertSame($child2, $topNodes[0]); // Highest score
        $this->assertSame($child1, $topNodes[1]); // Second highest
    }

    public function testGetBestPath(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child1 = $tree->addThought($root, 'Child 1');
        $child2 = $tree->addThought($root, 'Child 2');

        $child1->setScore(5.0);
        $child2->setScore(8.0);

        $grandchild1 = $tree->addThought($child2, 'Grandchild 1');
        $grandchild2 = $tree->addThought($child2, 'Grandchild 2');

        $grandchild1->setScore(6.0);
        $grandchild2->setScore(9.0);

        $path = $tree->getBestPath();

        $this->assertCount(3, $path);
        $this->assertSame($root, $path[0]);
        $this->assertSame($child2, $path[1]); // Best child
        $this->assertSame($grandchild2, $path[2]); // Best grandchild
    }

    public function testGetBestPathRootOnly(): void
    {
        $tree = new ThoughtTree('Root');

        $path = $tree->getBestPath();

        $this->assertCount(1, $path);
        $this->assertSame($tree->getRoot(), $path[0]);
    }

    public function testGetMaxDepth(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child = $tree->addThought($root, 'Child');
        $grandchild = $tree->addThought($child, 'Grandchild');
        $tree->addThought($grandchild, 'Great-grandchild');

        $this->assertSame(3, $tree->getMaxDepth());
    }

    public function testGetMaxDepthRootOnly(): void
    {
        $tree = new ThoughtTree('Root');

        $this->assertSame(0, $tree->getMaxDepth());
    }

    public function testGetMaxDepthWithBranches(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child1 = $tree->addThought($root, 'Child 1');
        $child2 = $tree->addThought($root, 'Child 2');

        $tree->addThought($child1, 'Grandchild 1');
        $greatGrandchild = $tree->addThought($child2, 'Grandchild 2');
        $tree->addThought($greatGrandchild, 'Great-grandchild');

        $this->assertSame(3, $tree->getMaxDepth());
    }

    public function testGetNodeCount(): void
    {
        $tree = new ThoughtTree('Root');

        $this->assertSame(1, $tree->getNodeCount());

        $root = $tree->getRoot();
        $tree->addThought($root, 'Child 1');
        $this->assertSame(2, $tree->getNodeCount());

        $tree->addThought($root, 'Child 2');
        $this->assertSame(3, $tree->getNodeCount());
    }

    public function testToArray(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        $child = $tree->addThought($root, 'Child');
        $tree->addThought($child, 'Grandchild');

        $array = $tree->toArray();

        $this->assertArrayHasKey('root', $array);
        $this->assertArrayHasKey('total_nodes', $array);
        $this->assertArrayHasKey('max_depth', $array);
        $this->assertArrayHasKey('leaf_count', $array);

        $this->assertSame(3, $array['total_nodes']);
        $this->assertSame(2, $array['max_depth']);
        $this->assertSame(1, $array['leaf_count']);
    }

    public function testComplexTreeStructure(): void
    {
        $tree = new ThoughtTree('Root');
        $root = $tree->getRoot();

        // Build a complex tree
        $child1 = $tree->addThought($root, 'Child 1');
        $child2 = $tree->addThought($root, 'Child 2');
        $child3 = $tree->addThought($root, 'Child 3');

        $child1->setScore(7.0);
        $child2->setScore(9.0);
        $child3->setScore(5.0);

        $gc1 = $tree->addThought($child1, 'GC 1.1');
        $gc2 = $tree->addThought($child1, 'GC 1.2');
        $gc3 = $tree->addThought($child2, 'GC 2.1');

        $gc1->setScore(6.0);
        $gc2->setScore(8.0);
        $gc3->setScore(9.5);

        // Verify structure
        $this->assertSame(7, $tree->getNodeCount());
        $this->assertSame(2, $tree->getMaxDepth());
        $this->assertCount(4, $tree->getLeafNodes());

        // Verify best path selects highest scores
        $path = $tree->getBestPath();
        $this->assertCount(3, $path);
        $this->assertSame($root, $path[0]);
        $this->assertSame($child2, $path[1]);
        $this->assertSame($gc3, $path[2]);
    }
}
