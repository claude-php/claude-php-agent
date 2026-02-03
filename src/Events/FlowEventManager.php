<?php

declare(strict_types=1);

namespace ClaudeAgents\Events;

use ClaudeAgents\Services\ServiceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Enhanced event manager for flow execution with queue-based emission.
 *
 * Inspired by Langflow's EventManager, this class provides:
 * - Event registration with callbacks
 * - Queue-based non-blocking emission
 * - Multiple listener support (one-to-many broadcasting)
 * - Integration with existing EventDispatcher
 *
 * @example
 * ```php
 * $manager = new FlowEventManager($queue);
 * $manager->registerEvent('on_token', FlowEvent::TOKEN_RECEIVED);
 * $manager->emit(FlowEvent::TOKEN_RECEIVED, ['token' => 'Hello']);
 *
 * // With callback
 * $manager->registerEvent('on_error', FlowEvent::ERROR, function($event) {
 *     error_log($event->data['message']);
 * });
 * ```
 */
class FlowEventManager implements ServiceInterface
{
    private EventQueue $queue;
    private bool $initialized = false;

    /**
     * @var array<string, array{type: string, callback: callable|null}>
     */
    private array $registeredEvents = [];

    /**
     * @var array<string, callable>
     */
    private array $listeners = [];

    private LoggerInterface $logger;
    private ?EventDispatcher $dispatcher = null;

    /**
     * @param EventQueue $queue Event queue for emission
     * @param LoggerInterface|null $logger Optional logger
     */
    public function __construct(
        EventQueue $queue,
        ?LoggerInterface $logger = null
    ) {
        $this->queue = $queue;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Set the event dispatcher for integration with existing event system.
     */
    public function setEventDispatcher(EventDispatcher $dispatcher): self
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    /**
     * Register an event type with optional callback.
     *
     * Similar to Langflow's register_event(name, event_type, callback).
     *
     * @param string $name Event name (e.g., 'on_token', 'on_error')
     * @param string $type Event type constant (e.g., FlowEvent::TOKEN_RECEIVED)
     * @param callable|null $callback Optional callback: function(FlowEvent $event): void
     */
    public function registerEvent(string $name, string $type, ?callable $callback = null): self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Event name cannot be empty');
        }

        if (!str_starts_with($name, 'on_')) {
            throw new \InvalidArgumentException("Event name must start with 'on_', got: {$name}");
        }

        $this->registeredEvents[$name] = [
            'type' => $type,
            'callback' => $callback,
        ];

        $this->logger->debug("Registered event: {$name} -> {$type}");

        return $this;
    }

    /**
     * Emit an event to the queue and notify listeners.
     *
     * @param string $eventType Event type constant
     * @param array<string, mixed> $data Event data
     * @param string|null $id Optional event ID
     * @return bool True if event was queued successfully
     */
    public function emit(string $eventType, array $data = [], ?string $id = null): bool
    {
        $event = new FlowEvent(
            type: $eventType,
            data: $data,
            timestamp: microtime(true),
            id: $id ?? $this->generateEventId($eventType)
        );

        // Enqueue the event
        $queued = $this->queue->enqueue($event);

        if (!$queued) {
            $this->logger->warning("Failed to enqueue event: {$eventType} (queue full)");
            return false;
        }

        // Execute registered callbacks
        $this->executeCallbacks($event);

        // Notify subscribers
        $this->notifyListeners($event);

        // Integrate with legacy EventDispatcher if available
        $this->dispatchLegacyEvent($event);

        return true;
    }

    /**
     * Subscribe a listener to all events.
     *
     * @param callable $listener Callback: function(FlowEvent $event): void
     * @return string Listener ID for unsubscribing
     */
    public function subscribe(callable $listener): string
    {
        $listenerId = $this->generateListenerId();
        $this->listeners[$listenerId] = $listener;

        $this->logger->debug("New subscriber registered: {$listenerId}");

        return $listenerId;
    }

    /**
     * Unsubscribe a listener.
     *
     * @param string $listenerId Listener ID from subscribe()
     */
    public function unsubscribe(string $listenerId): void
    {
        if (isset($this->listeners[$listenerId])) {
            unset($this->listeners[$listenerId]);
            $this->logger->debug("Subscriber unregistered: {$listenerId}");
        }
    }

    /**
     * Get all registered event names.
     *
     * @return array<string>
     */
    public function getRegisteredEvents(): array
    {
        return array_keys($this->registeredEvents);
    }

    /**
     * Check if an event name is registered.
     */
    public function hasEvent(string $name): bool
    {
        return isset($this->registeredEvents[$name]);
    }

    /**
     * Get the event queue.
     */
    public function getQueue(): EventQueue
    {
        return $this->queue;
    }

    /**
     * Get the number of active listeners.
     */
    public function getListenerCount(): int
    {
        return count($this->listeners);
    }

    /**
     * Clear all listeners.
     */
    public function clearListeners(): void
    {
        $this->listeners = [];
        $this->logger->debug('All listeners cleared');
    }

    /**
     * Magic method to emit events by registered name.
     *
     * Example: $manager->on_token(['token' => 'text'])
     *
     * @param string $name Method name (should be registered event name)
     * @param array<mixed> $arguments Arguments [0] should be data array
     */
    public function __call(string $name, array $arguments): bool
    {
        if (!$this->hasEvent($name)) {
            $this->logger->warning("Attempted to emit unregistered event: {$name}");
            return false;
        }

        $eventConfig = $this->registeredEvents[$name];
        $data = $arguments[0] ?? [];

        return $this->emit($eventConfig['type'], $data);
    }

    /**
     * Execute registered callbacks for an event.
     */
    private function executeCallbacks(FlowEvent $event): void
    {
        foreach ($this->registeredEvents as $name => $config) {
            if ($config['type'] === $event->type && $config['callback'] !== null) {
                try {
                    ($config['callback'])($event);
                } catch (\Throwable $e) {
                    $this->logger->error("Error in event callback for {$name}: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Notify all subscribed listeners.
     */
    private function notifyListeners(FlowEvent $event): void
    {
        foreach ($this->listeners as $listenerId => $listener) {
            try {
                $listener($event);
            } catch (\Throwable $e) {
                $this->logger->error("Error in listener {$listenerId}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Dispatch event to legacy EventDispatcher if available.
     */
    private function dispatchLegacyEvent(FlowEvent $event): void
    {
        if ($this->dispatcher === null) {
            return;
        }

        // Map flow events to legacy agent events
        $agentEvent = match ($event->type) {
            FlowEvent::FLOW_STARTED => new AgentStartedEvent('flow', $event->timestamp, $event->data),
            FlowEvent::FLOW_COMPLETED => new AgentCompletedEvent('flow', $event->timestamp, $event->data),
            FlowEvent::FLOW_FAILED => new AgentFailedEvent('flow', $event->data['error'] ?? 'Unknown error', $event->timestamp),
            default => null,
        };

        if ($agentEvent !== null) {
            $this->dispatcher->dispatch($agentEvent);
        }
    }

    /**
     * Generate a unique event ID.
     */
    private function generateEventId(string $eventType): string
    {
        return $eventType . '-' . uniqid('', true);
    }

    /**
     * Generate a unique listener ID.
     */
    private function generateListenerId(): string
    {
        return 'listener-' . uniqid('', true);
    }

    /**
     * Register default events compatible with Langflow.
     */
    public function registerDefaultEvents(): self
    {
        return $this
            ->registerEvent('on_token', FlowEvent::TOKEN_RECEIVED)
            ->registerEvent('on_vertices_sorted', FlowEvent::VERTICES_SORTED)
            ->registerEvent('on_error', FlowEvent::ERROR)
            ->registerEvent('on_end', FlowEvent::FLOW_COMPLETED)
            ->registerEvent('on_message', FlowEvent::MESSAGE_ADDED)
            ->registerEvent('on_remove_message', FlowEvent::MESSAGE_REMOVED)
            ->registerEvent('on_end_vertex', FlowEvent::VERTEX_COMPLETED)
            ->registerEvent('on_build_start', FlowEvent::BUILD_STARTED)
            ->registerEvent('on_build_end', FlowEvent::BUILD_COMPLETED);
    }

    /**
     * Register streaming-focused events.
     */
    public function registerStreamingEvents(): self
    {
        return $this
            ->registerEvent('on_message', FlowEvent::MESSAGE_ADDED)
            ->registerEvent('on_token', FlowEvent::TOKEN_RECEIVED)
            ->registerEvent('on_end', FlowEvent::FLOW_COMPLETED);
    }

    /**
     * Get service name (ServiceInterface implementation).
     */
    public function getName(): string
    {
        return 'event_manager';
    }

    /**
     * Initialize service (ServiceInterface implementation).
     */
    public function initialize(): void
    {
        if (!$this->initialized) {
            $this->logger->debug('FlowEventManager initialized');
            $this->initialized = true;
        }
    }

    /**
     * Teardown service (ServiceInterface implementation).
     */
    public function teardown(): void
    {
        $this->clearListeners();
        $this->queue->clear();
        $this->initialized = false;
        $this->logger->debug('FlowEventManager torn down');
    }

    /**
     * Check if service is ready (ServiceInterface implementation).
     */
    public function isReady(): bool
    {
        return $this->initialized;
    }

    /**
     * Get service schema (ServiceInterface implementation).
     *
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'type' => 'event_manager',
            'queue_max_size' => $this->queue->getMaxSize(),
            'registered_events' => $this->getRegisteredEvents(),
            'listener_count' => $this->getListenerCount(),
        ];
    }
}
