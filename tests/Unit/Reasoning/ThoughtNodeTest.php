<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Reasoning;

use ClaudeAgents\Reasoning\ThoughtNode;
use PHPUnit\Framework\TestCase;

class ThoughtNodeTest extends TestCase
{
    public function testConstructor(): void
    {
        $node = new ThoughtNode('node_1', 'Test thought', 0);

        $this->assertSame('node_1', $node->getId());
        $this->assertSame('Test thought', $node->getThought());
        $this->assertSame(0, $node->getDepth());
        $this->assertSame(0.0, $node->getScore());
        $this->assertNull($node->getParent());
    }

    public function testConstructorWithScore(): void
    {
        $node = new ThoughtNode('node_1', 'Test', 0, 7.5);

        $this->assertSame(7.5, $node->getScore());
    }

    public function testConstructorWithParent(): void
    {
        $parent = new ThoughtNode('parent', 'Parent thought', 0);
        $child = new ThoughtNode('child', 'Child thought', 1, 0.0, $parent);

        $this->assertSame($parent, $child->getParent());
    }

    public function testGetId(): void
    {
        $node = new ThoughtNode('test_id', 'Thought', 0);

        $this->assertSame('test_id', $node->getId());
    }

    public function testGetThought(): void
    {
        $node = new ThoughtNode('id', 'This is a thought', 0);

        $this->assertSame('This is a thought', $node->getThought());
    }

    public function testGetDepth(): void
    {
        $node = new ThoughtNode('id', 'Thought', 3);

        $this->assertSame(3, $node->getDepth());
    }

    public function testGetAndSetScore(): void
    {
        $node = new ThoughtNode('id', 'Thought', 0);

        $this->assertSame(0.0, $node->getScore());

        $node->setScore(8.5);
        $this->assertSame(8.5, $node->getScore());
    }

    public function testGetParent(): void
    {
        $parent = new ThoughtNode('parent', 'Parent', 0);
        $child = new ThoughtNode('child', 'Child', 1, 0.0, $parent);

        $this->assertSame($parent, $child->getParent());
    }

    public function testGetChildren(): void
    {
        $node = new ThoughtNode('id', 'Thought', 0);

        $this->assertSame([], $node->getChildren());
    }

    public function testAddChild(): void
    {
        $parent = new ThoughtNode('parent', 'Parent', 0);
        $child = new ThoughtNode('child', 'Child', 1, 0.0, $parent);

        $parent->addChild($child);

        $children = $parent->getChildren();
        $this->assertCount(1, $children);
        $this->assertSame($child, $children[0]);
    }

    public function testAddMultipleChildren(): void
    {
        $parent = new ThoughtNode('parent', 'Parent', 0);
        $child1 = new ThoughtNode('child1', 'Child 1', 1, 0.0, $parent);
        $child2 = new ThoughtNode('child2', 'Child 2', 1, 0.0, $parent);
        $child3 = new ThoughtNode('child3', 'Child 3', 1, 0.0, $parent);

        $parent->addChild($child1);
        $parent->addChild($child2);
        $parent->addChild($child3);

        $children = $parent->getChildren();
        $this->assertCount(3, $children);
    }

    public function testGetDescendants(): void
    {
        $root = new ThoughtNode('root', 'Root', 0);
        $child1 = new ThoughtNode('child1', 'Child 1', 1, 0.0, $root);
        $child2 = new ThoughtNode('child2', 'Child 2', 1, 0.0, $root);
        $grandchild = new ThoughtNode('gc', 'Grandchild', 2, 0.0, $child1);

        $root->addChild($child1);
        $root->addChild($child2);
        $child1->addChild($grandchild);

        $descendants = $root->getDescendants();

        $this->assertCount(3, $descendants);
        $this->assertContains($child1, $descendants);
        $this->assertContains($child2, $descendants);
        $this->assertContains($grandchild, $descendants);
    }

    public function testGetDescendantsEmpty(): void
    {
        $node = new ThoughtNode('id', 'Thought', 0);

        $this->assertSame([], $node->getDescendants());
    }

    public function testGetPath(): void
    {
        $root = new ThoughtNode('root', 'Root', 0);
        $child = new ThoughtNode('child', 'Child', 1, 0.0, $root);
        $grandchild = new ThoughtNode('gc', 'Grandchild', 2, 0.0, $child);

        $path = $grandchild->getPath();

        $this->assertCount(3, $path);
        $this->assertSame($root, $path[0]);
        $this->assertSame($child, $path[1]);
        $this->assertSame($grandchild, $path[2]);
    }

    public function testGetPathRootNode(): void
    {
        $root = new ThoughtNode('root', 'Root', 0);

        $path = $root->getPath();

        $this->assertCount(1, $path);
        $this->assertSame($root, $path[0]);
    }

    public function testIsLeaf(): void
    {
        $parent = new ThoughtNode('parent', 'Parent', 0);
        $child = new ThoughtNode('child', 'Child', 1, 0.0, $parent);

        $this->assertTrue($parent->isLeaf());

        $parent->addChild($child);

        $this->assertFalse($parent->isLeaf());
        $this->assertTrue($child->isLeaf());
    }

    public function testGetChildCount(): void
    {
        $parent = new ThoughtNode('parent', 'Parent', 0);

        $this->assertSame(0, $parent->getChildCount());

        $child1 = new ThoughtNode('child1', 'Child 1', 1, 0.0, $parent);
        $child2 = new ThoughtNode('child2', 'Child 2', 1, 0.0, $parent);

        $parent->addChild($child1);
        $this->assertSame(1, $parent->getChildCount());

        $parent->addChild($child2);
        $this->assertSame(2, $parent->getChildCount());
    }

    public function testToArray(): void
    {
        $node = new ThoughtNode('test_node', 'Test thought', 2, 7.5);

        $array = $node->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('thought', $array);
        $this->assertArrayHasKey('depth', $array);
        $this->assertArrayHasKey('score', $array);
        $this->assertArrayHasKey('is_leaf', $array);
        $this->assertArrayHasKey('child_count', $array);

        $this->assertSame('test_node', $array['id']);
        $this->assertSame('Test thought', $array['thought']);
        $this->assertSame(2, $array['depth']);
        $this->assertSame(7.5, $array['score']);
        $this->assertTrue($array['is_leaf']);
        $this->assertSame(0, $array['child_count']);
    }

    public function testToArrayWithChildren(): void
    {
        $parent = new ThoughtNode('parent', 'Parent', 0);
        $child = new ThoughtNode('child', 'Child', 1, 0.0, $parent);
        $parent->addChild($child);

        $array = $parent->toArray();

        $this->assertFalse($array['is_leaf']);
        $this->assertSame(1, $array['child_count']);
    }
}
