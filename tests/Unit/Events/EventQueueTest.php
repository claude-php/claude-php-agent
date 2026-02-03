<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEvent;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ClaudeAgents\Events\EventQueue
 */
class EventQueueTest extends TestCase
{
    public function testCanEnqueueAndDequeueEvents(): void
    {
        $queue = new EventQueue(maxSize: 10);
        $event = FlowEvent::flowStarted(['test' => 'data']);

        $this->assertTrue($queue->enqueue($event));
        $this->assertFalse($queue->isEmpty());
        $this->assertEquals(1, $queue->size());

        $dequeuedEvent = $queue->dequeue();
        $this->assertInstanceOf(FlowEvent::class, $dequeuedEvent);
        $this->assertEquals($event->type, $dequeuedEvent->type);
        $this->assertEquals($event->data, $dequeuedEvent->data);
        $this->assertTrue($queue->isEmpty());
    }

    public function testDequeueReturnsNullWhenEmpty(): void
    {
        $queue = new EventQueue();

        $this->assertTrue($queue->isEmpty());
        $this->assertNull($queue->dequeue());
    }

    public function testQueueRespectsMaxSize(): void
    {
        $queue = new EventQueue(maxSize: 2);

        $event1 = FlowEvent::token('token1');
        $event2 = FlowEvent::token('token2');
        $event3 = FlowEvent::token('token3');

        $this->assertTrue($queue->enqueue($event1));
        $this->assertTrue($queue->enqueue($event2));
        $this->assertFalse($queue->enqueue($event3)); // Should fail, queue is full

        $this->assertEquals(2, $queue->size());
        $this->assertEquals(1, $queue->getDroppedEventCount());
    }

    public function testCanPeekWithoutRemoving(): void
    {
        $queue = new EventQueue();
        $event = FlowEvent::flowStarted(['test' => 'data']);

        $queue->enqueue($event);

        $peeked = $queue->peek();
        $this->assertInstanceOf(FlowEvent::class, $peeked);
        $this->assertEquals($event->type, $peeked->type);
        $this->assertEquals(1, $queue->size()); // Size unchanged

        $dequeued = $queue->dequeue();
        $this->assertEquals($event->type, $dequeued->type);
        $this->assertTrue($queue->isEmpty());
    }

    public function testPeekReturnsNullWhenEmpty(): void
    {
        $queue = new EventQueue();

        $this->assertNull($queue->peek());
    }

    public function testCanClearQueue(): void
    {
        $queue = new EventQueue();

        $queue->enqueue(FlowEvent::token('token1'));
        $queue->enqueue(FlowEvent::token('token2'));
        $queue->enqueue(FlowEvent::token('token3'));

        $this->assertEquals(3, $queue->size());

        $queue->clear();

        $this->assertTrue($queue->isEmpty());
        $this->assertEquals(0, $queue->size());
    }

    public function testGetStatsReturnsCorrectInformation(): void
    {
        $queue = new EventQueue(maxSize: 10);

        $queue->enqueue(FlowEvent::token('token1'));
        $queue->enqueue(FlowEvent::token('token2'));

        $stats = $queue->getStats();

        $this->assertEquals(2, $stats['size']);
        $this->assertEquals(10, $stats['max_size']);
        $this->assertEquals(0, $stats['dropped_events']);
        $this->assertFalse($stats['is_empty']);
        $this->assertEquals(20.0, $stats['utilization']); // 2/10 * 100
    }

    public function testFIFOOrdering(): void
    {
        $queue = new EventQueue();

        $event1 = FlowEvent::token('first');
        $event2 = FlowEvent::token('second');
        $event3 = FlowEvent::token('third');

        $queue->enqueue($event1);
        $queue->enqueue($event2);
        $queue->enqueue($event3);

        $this->assertEquals('first', $queue->dequeue()->data['token']);
        $this->assertEquals('second', $queue->dequeue()->data['token']);
        $this->assertEquals('third', $queue->dequeue()->data['token']);
    }

    public function testMultipleEnqueueDequeueOperations(): void
    {
        $queue = new EventQueue(maxSize: 100);

        // Enqueue 50 events
        for ($i = 1; $i <= 50; $i++) {
            $queue->enqueue(FlowEvent::token("token{$i}"));
        }

        $this->assertEquals(50, $queue->size());

        // Dequeue 25 events
        for ($i = 1; $i <= 25; $i++) {
            $event = $queue->dequeue();
            $this->assertEquals("token{$i}", $event->data['token']);
        }

        $this->assertEquals(25, $queue->size());

        // Enqueue 25 more
        for ($i = 51; $i <= 75; $i++) {
            $queue->enqueue(FlowEvent::token("token{$i}"));
        }

        $this->assertEquals(50, $queue->size());
    }

    public function testGetMaxSize(): void
    {
        $queue = new EventQueue(maxSize: 42);

        $this->assertEquals(42, $queue->getMaxSize());
    }
}
