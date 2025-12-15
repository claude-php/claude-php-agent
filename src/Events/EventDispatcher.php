<?php

declare(strict_types=1);

namespace ClaudeAgents\Events;

/**
 * Simple event dispatcher for agent lifecycle events.
 *
 * Implements the Observer pattern to allow monitoring of agent execution.
 *
 * @example
 * ```php
 * $dispatcher = new EventDispatcher();
 * $dispatcher->listen(AgentStartedEvent::class, function($event) {
 *     echo "Agent started: " . $event->getAgentName();
 * });
 * ```
 */
class EventDispatcher
{
    /**
     * @var array<string, array<callable>>
     */
    private array $listeners = [];

    /**
     * Register an event listener.
     *
     * @param class-string<AgentEvent> $eventClass
     * @param callable $listener
     */
    public function listen(string $eventClass, callable $listener): void
    {
        if (! isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }

        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Dispatch an event to all registered listeners.
     */
    public function dispatch(AgentEvent $event): void
    {
        $eventClass = get_class($event);

        if (! isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $listener) {
            $listener($event);
        }
    }

    /**
     * Remove all listeners for an event type.
     *
     * @param class-string<AgentEvent> $eventClass
     */
    public function clearListeners(string $eventClass): void
    {
        unset($this->listeners[$eventClass]);
    }

    /**
     * Remove all listeners.
     */
    public function clearAllListeners(): void
    {
        $this->listeners = [];
    }

    /**
     * Check if any listeners are registered for an event type.
     *
     * @param class-string<AgentEvent> $eventClass
     */
    public function hasListeners(string $eventClass): bool
    {
        return isset($this->listeners[$eventClass]) && ! empty($this->listeners[$eventClass]);
    }
}
