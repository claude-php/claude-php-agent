<?php

declare(strict_types=1);

namespace ClaudeAgents\Events;

use SplQueue;

/**
 * Thread-safe FIFO event queue for flow execution.
 *
 * Provides a PHP-native event queue inspired by Python's asyncio.Queue,
 * adapted for synchronous PHP operations with configurable size limits.
 *
 * @example
 * ```php
 * $queue = new EventQueue(maxSize: 100);
 * $queue->enqueue(new FlowEvent('token.received', ['token' => 'Hello']));
 * $event = $queue->dequeue(); // Returns FlowEvent or null if empty
 * ```
 */
class EventQueue
{
    private SplQueue $queue;
    private int $maxSize;
    private int $droppedEvents = 0;

    /**
     * @param int $maxSize Maximum queue size (default: 100, like Langflow's SSE_QUEUE_MAX_SIZE)
     */
    public function __construct(int $maxSize = 100)
    {
        $this->queue = new SplQueue();
        $this->maxSize = $maxSize;
    }

    /**
     * Add an event to the queue.
     *
     * @param FlowEvent $event Event to enqueue
     * @return bool True if enqueued, false if queue is full
     */
    public function enqueue(FlowEvent $event): bool
    {
        if ($this->size() >= $this->maxSize) {
            $this->droppedEvents++;
            return false;
        }

        $this->queue->enqueue($event);
        return true;
    }

    /**
     * Remove and return the next event from the queue.
     *
     * @return FlowEvent|null The next event, or null if queue is empty
     */
    public function dequeue(): ?FlowEvent
    {
        if ($this->isEmpty()) {
            return null;
        }

        return $this->queue->dequeue();
    }

    /**
     * Check if the queue is empty.
     */
    public function isEmpty(): bool
    {
        return $this->queue->isEmpty();
    }

    /**
     * Get the current number of events in the queue.
     */
    public function size(): int
    {
        return $this->queue->count();
    }

    /**
     * Get the maximum queue size.
     */
    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * Get the number of events dropped due to queue overflow.
     */
    public function getDroppedEventCount(): int
    {
        return $this->droppedEvents;
    }

    /**
     * Clear all events from the queue.
     */
    public function clear(): void
    {
        while (!$this->isEmpty()) {
            $this->dequeue();
        }
    }

    /**
     * Peek at the next event without removing it.
     *
     * @return FlowEvent|null The next event, or null if queue is empty
     */
    public function peek(): ?FlowEvent
    {
        if ($this->isEmpty()) {
            return null;
        }

        return $this->queue->bottom();
    }

    /**
     * Get queue statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'size' => $this->size(),
            'max_size' => $this->maxSize,
            'dropped_events' => $this->droppedEvents,
            'is_empty' => $this->isEmpty(),
            'utilization' => $this->maxSize > 0 ? ($this->size() / $this->maxSize) * 100 : 0,
        ];
    }
}
