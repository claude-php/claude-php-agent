<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Reasoning;

use ClaudeAgents\Reasoning\SearchStrategy;
use ClaudeAgents\Reasoning\ThoughtNode;
use PHPUnit\Framework\TestCase;

class SearchStrategyTest extends TestCase
{
    public function testBreadthFirst(): void
    {
        $root = new ThoughtNode('root', 'Root', 0);
        $child1 = new ThoughtNode('child1', 'Child 1', 1, 5.0, $root);
        $child2 = new ThoughtNode('child2', 'Child 2', 1, 8.0, $root);

        $gc1 = new ThoughtNode('gc1', 'GC 1', 2, 6.0, $child1);
        $gc2 = new ThoughtNode('gc2', 'GC 2', 2, 9.0, $child2);

        $child1->addChild($gc1);
        $child2->addChild($gc2);
        $root->addChild($child1);
        $root->addChild($child2);

        $frontier = [$child1, $child2];
        $next = SearchStrategy::breadthFirst($frontier, 2);

        $this->assertCount(2, $next);
        $this->assertContains($gc1, $next);
        $this->assertContains($gc2, $next);
    }

    public function testBreadthFirstEmptyChildren(): void
    {
        $node1 = new ThoughtNode('node1', 'Node 1', 1);
        $node2 = new ThoughtNode('node2', 'Node 2', 1);

        $frontier = [$node1, $node2];
        $next = SearchStrategy::breadthFirst($frontier, 2);

        $this->assertCount(0, $next);
    }

    public function testDepthFirst(): void
    {
        $root = new ThoughtNode('root', 'Root', 0);
        $child1 = new ThoughtNode('child1', 'Child 1', 1, 5.0, $root);
        $child2 = new ThoughtNode('child2', 'Child 2', 1, 8.0, $root);

        $gc1 = new ThoughtNode('gc1', 'GC 1', 2, 6.0, $child1);

        $child1->addChild($gc1);
        $root->addChild($child1);
        $root->addChild($child2);

        $frontier = [$child1, $child2];
        $next = SearchStrategy::depthFirst($frontier, 5);

        // Should follow first child of each node
        $this->assertCount(1, $next);
        $this->assertContains($gc1, $next);
    }

    public function testDepthFirstRespectsMaxDepth(): void
    {
        $root = new ThoughtNode('root', 'Root', 0);
        $child = new ThoughtNode('child', 'Child', 1, 5.0, $root);
        $grandchild = new ThoughtNode('gc', 'GC', 2, 6.0, $child);

        $child->addChild($grandchild);
        $root->addChild($child);

        $frontier = [$child];
        $next = SearchStrategy::depthFirst($frontier, 1);

        // Should not go deeper than maxDepth
        $this->assertCount(0, $next);
    }

    public function testDepthFirstNoChildren(): void
    {
        $node = new ThoughtNode('node', 'Node', 1);

        $frontier = [$node];
        $next = SearchStrategy::depthFirst($frontier, 5);

        $this->assertCount(0, $next);
    }

    public function testBestFirst(): void
    {
        $root = new ThoughtNode('root', 'Root', 0);

        $child1 = new ThoughtNode('child1', 'Child 1', 1, 5.0, $root);
        $child2 = new ThoughtNode('child2', 'Child 2', 1, 8.0, $root);
        $child3 = new ThoughtNode('child3', 'Child 3', 1, 3.0, $root);

        $gc1 = new ThoughtNode('gc1', 'GC 1', 2, 6.0, $child1);
        $gc2 = new ThoughtNode('gc2', 'GC 2', 2, 9.0, $child2);
        $gc3 = new ThoughtNode('gc3', 'GC 3', 2, 4.0, $child3);

        $child1->addChild($gc1);
        $child2->addChild($gc2);
        $child3->addChild($gc3);

        $frontier = [$child1, $child2, $child3];
        $next = SearchStrategy::bestFirst($frontier, 2);

        // Should expand top 2 nodes by score
        $this->assertCount(2, $next);
        $this->assertContains($gc2, $next); // From child2 (score 8.0)
        $this->assertContains($gc1, $next); // From child1 (score 5.0)
        $this->assertNotContains($gc3, $next); // From child3 (score 3.0) - not in top 2
    }

    public function testBestFirstWithTopK(): void
    {
        $root = new ThoughtNode('root', 'Root', 0);

        $child1 = new ThoughtNode('child1', 'Child 1', 1, 10.0, $root);
        $child2 = new ThoughtNode('child2', 'Child 2', 1, 8.0, $root);
        $child3 = new ThoughtNode('child3', 'Child 3', 1, 6.0, $root);
        $child4 = new ThoughtNode('child4', 'Child 4', 1, 4.0, $root);

        $gc1 = new ThoughtNode('gc1', 'GC 1', 2, 5.0, $child1);
        $gc2 = new ThoughtNode('gc2', 'GC 2', 2, 5.0, $child2);
        $gc3 = new ThoughtNode('gc3', 'GC 3', 2, 5.0, $child3);

        $child1->addChild($gc1);
        $child2->addChild($gc2);
        $child3->addChild($gc3);

        $frontier = [$child1, $child2, $child3, $child4];
        $next = SearchStrategy::bestFirst($frontier, 1);

        // Should only expand top 1 node
        $this->assertCount(1, $next);
        $this->assertContains($gc1, $next);
    }

    public function testBestFirstDefaultTopK(): void
    {
        $root = new ThoughtNode('root', 'Root', 0);

        $nodes = [];
        for ($i = 0; $i < 5; $i++) {
            $node = new ThoughtNode("node$i", "Node $i", 1, (float)$i, $root);
            $child = new ThoughtNode("child$i", "Child $i", 2, (float)$i, $node);
            $node->addChild($child);
            $nodes[] = $node;
        }

        $next = SearchStrategy::bestFirst($nodes);

        // Default topK is 3
        $this->assertCount(3, $next);
    }

    public function testBestFirstEmptyChildren(): void
    {
        $node1 = new ThoughtNode('node1', 'Node 1', 1, 10.0);
        $node2 = new ThoughtNode('node2', 'Node 2', 1, 8.0);

        $frontier = [$node1, $node2];
        $next = SearchStrategy::bestFirst($frontier, 2);

        $this->assertCount(0, $next);
    }

    public function testBestFirstSortsCorrectly(): void
    {
        $root = new ThoughtNode('root', 'Root', 0);

        // Create nodes with specific scores
        $child1 = new ThoughtNode('child1', 'Child 1', 1, 3.0, $root);
        $child2 = new ThoughtNode('child2', 'Child 2', 1, 9.0, $root);
        $child3 = new ThoughtNode('child3', 'Child 3', 1, 6.0, $root);

        $gc1 = new ThoughtNode('gc1', 'GC 1', 2, 1.0, $child1);
        $gc2 = new ThoughtNode('gc2', 'GC 2', 2, 1.0, $child2);
        $gc3 = new ThoughtNode('gc3', 'GC 3', 2, 1.0, $child3);

        $child1->addChild($gc1);
        $child2->addChild($gc2);
        $child3->addChild($gc3);

        // Pass in random order
        $frontier = [$child1, $child3, $child2];
        $next = SearchStrategy::bestFirst($frontier, 2);

        // Should select children of top 2 scored parents
        $this->assertCount(2, $next);
        $this->assertContains($gc2, $next); // From child2 (9.0)
        $this->assertContains($gc3, $next); // From child3 (6.0)
        $this->assertNotContains($gc1, $next); // From child1 (3.0)
    }

    public function testIsValid(): void
    {
        $this->assertTrue(SearchStrategy::isValid(SearchStrategy::BREADTH_FIRST));
        $this->assertTrue(SearchStrategy::isValid(SearchStrategy::DEPTH_FIRST));
        $this->assertTrue(SearchStrategy::isValid(SearchStrategy::BEST_FIRST));

        $this->assertFalse(SearchStrategy::isValid('invalid_strategy'));
        $this->assertFalse(SearchStrategy::isValid('random'));
        $this->assertFalse(SearchStrategy::isValid(''));
    }

    public function testConstants(): void
    {
        $this->assertSame('breadth_first', SearchStrategy::BREADTH_FIRST);
        $this->assertSame('depth_first', SearchStrategy::DEPTH_FIRST);
        $this->assertSame('best_first', SearchStrategy::BEST_FIRST);
    }
}
