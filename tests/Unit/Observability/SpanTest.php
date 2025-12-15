<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use ClaudeAgents\Observability\Span;
use PHPUnit\Framework\TestCase;

class SpanTest extends TestCase
{
    public function testSpanCreation(): void
    {
        $span = new Span('test.span', ['foo' => 'bar']);

        $this->assertEquals('test.span', $span->getName());
        $this->assertNotEmpty($span->getSpanId());
        $this->assertNotEmpty($span->getTraceId());
        $this->assertNull($span->getParentSpanId());
        $this->assertEquals('bar', $span->getAttribute('foo'));
        $this->assertEquals('UNSET', $span->getStatus());
        $this->assertTrue($span->isRecording());
    }

    public function testSpanWithParent(): void
    {
        $parent = new Span('parent');
        $child = new Span('child', [], $parent);

        $this->assertEquals($parent->getTraceId(), $child->getTraceId());
        $this->assertEquals($parent->getSpanId(), $child->getParentSpanId());
    }

    public function testSpanDuration(): void
    {
        $span = new Span('test');
        usleep(10000); // 10ms
        $span->end();

        $duration = $span->getDuration();
        $this->assertGreaterThanOrEqual(10, $duration);
        $this->assertFalse($span->isRecording());
    }

    public function testSetStatus(): void
    {
        $span = new Span('test');
        $span->setStatus('OK', 'All good');

        $this->assertEquals('OK', $span->getStatus());
        $this->assertEquals('All good', $span->getStatusMessage());
    }

    public function testInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $span = new Span('test');
        $span->setStatus('INVALID');
    }

    public function testAddEvent(): void
    {
        $span = new Span('test');
        $span->addEvent('cache.hit', ['key' => 'foo']);

        $events = $span->getEvents();
        $this->assertCount(1, $events);
        $this->assertEquals('cache.hit', $events[0]['name']);
        $this->assertEquals(['key' => 'foo'], $events[0]['attributes']);
    }

    public function testSetAttributes(): void
    {
        $span = new Span('test', ['a' => 1]);
        $span->setAttribute('b', 2);
        $span->setAttributes(['c' => 3, 'd' => 4]);

        $attributes = $span->getAttributes();
        $this->assertEquals(1, $attributes['a']);
        $this->assertEquals(2, $attributes['b']);
        $this->assertEquals(3, $attributes['c']);
        $this->assertEquals(4, $attributes['d']);
    }

    public function testToArray(): void
    {
        $span = new Span('test', ['foo' => 'bar']);
        $span->addEvent('test.event');
        $span->setStatus('OK');
        $span->end();

        $array = $span->toArray();

        $this->assertEquals('test', $array['name']);
        $this->assertArrayHasKey('span_id', $array);
        $this->assertArrayHasKey('trace_id', $array);
        $this->assertEquals('OK', $array['status']);
        $this->assertArrayHasKey('duration_ms', $array);
        $this->assertCount(1, $array['events']);
    }

    public function testToOpenTelemetry(): void
    {
        $span = new Span('test', ['http.method' => 'GET']);
        $span->setStatus('OK');
        $span->end();

        $otel = $span->toOpenTelemetry();

        $this->assertArrayHasKey('traceId', $otel);
        $this->assertArrayHasKey('spanId', $otel);
        $this->assertEquals('test', $otel['name']);
        $this->assertArrayHasKey('attributes', $otel);
        $this->assertEquals('OK', $otel['status']['code']);
    }
}
