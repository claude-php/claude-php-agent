<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use ClaudeAgents\Observability\Tracer;
use PHPUnit\Framework\TestCase;

class TracerTest extends TestCase
{
    public function testStartAndEndSpan(): void
    {
        $tracer = new Tracer();
        $span = $tracer->startSpan('test');

        $this->assertCount(1, $tracer->getActiveSpans());
        $this->assertCount(0, $tracer->getSpans());

        $tracer->endSpan($span);

        $this->assertCount(0, $tracer->getActiveSpans());
        $this->assertCount(1, $tracer->getSpans());
    }

    public function testParentChildSpans(): void
    {
        $tracer = new Tracer();
        $parent = $tracer->startSpan('parent');
        $child = $tracer->startSpan('child', [], $parent);

        $this->assertEquals($parent->getTraceId(), $child->getTraceId());
        $this->assertEquals($parent->getSpanId(), $child->getParentSpanId());

        $tracer->endSpan($child);
        $tracer->endSpan($parent);

        $spans = $tracer->getSpans();
        $this->assertCount(2, $spans);
    }

    public function testGetSpansByTraceId(): void
    {
        $tracer = new Tracer();

        $tracer->startTrace();
        $span1 = $tracer->startSpan('span1');
        $span2 = $tracer->startSpan('span2');
        $tracer->endSpan($span1);
        $tracer->endSpan($span2);

        $traceId = $span1->getTraceId();
        $spansByTrace = $tracer->getSpansByTraceId($traceId);

        $this->assertCount(2, $spansByTrace);
    }

    public function testTraceLifecycle(): void
    {
        $tracer = new Tracer();

        $traceId = $tracer->startTrace();
        $this->assertNotNull($traceId);
        $this->assertEquals($traceId, $tracer->getCurrentTraceId());

        $span = $tracer->startSpan('test');
        $this->assertEquals($traceId, $span->getTraceId());

        $tracer->endTrace();
        $this->assertNull($tracer->getCurrentTraceId());
        $this->assertCount(0, $tracer->getActiveSpans());
    }

    public function testBuildSpanTree(): void
    {
        $tracer = new Tracer();

        $root = $tracer->startSpan('root');
        $child1 = $tracer->startSpan('child1', [], $root);
        $child2 = $tracer->startSpan('child2', [], $root);
        $grandchild = $tracer->startSpan('grandchild', [], $child1);

        $tracer->endSpan($grandchild);
        $tracer->endSpan($child2);
        $tracer->endSpan($child1);
        $tracer->endSpan($root);

        $tree = $tracer->buildSpanTree();

        $this->assertCount(1, $tree); // One root
        $this->assertEquals('root', $tree[0]['span']->getName());
        $this->assertCount(2, $tree[0]['children']); // Two children
    }

    public function testToArray(): void
    {
        $tracer = new Tracer();
        $span = $tracer->startSpan('test');
        $tracer->endSpan($span);

        $array = $tracer->toArray();

        $this->assertArrayHasKey('spans', $array);
        $this->assertArrayHasKey('span_count', $array);
        $this->assertArrayHasKey('total_duration', $array);
        $this->assertEquals(1, $array['span_count']);
    }

    public function testReset(): void
    {
        $tracer = new Tracer();
        $span = $tracer->startSpan('test');
        $tracer->endSpan($span);

        $tracer->reset();

        $this->assertCount(0, $tracer->getSpans());
        $this->assertCount(0, $tracer->getActiveSpans());
        $this->assertNull($tracer->getCurrentTraceId());
    }
}
