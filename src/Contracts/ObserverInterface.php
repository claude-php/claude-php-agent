<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for the Observer pattern.
 *
 * Observers watch for events and state changes in agents, chains,
 * and other components, enabling logging, monitoring, and reactive behavior.
 */
interface ObserverInterface
{
    /**
     * Handle an event notification.
     *
     * @param string $event The event name/type
     * @param array<string, mixed> $data Event data and context
     */
    public function update(string $event, array $data = []): void;

    /**
     * Get the observer's identifier.
     */
    public function getId(): string;

    /**
     * Get events this observer is interested in.
     *
     * Return empty array to observe all events.
     *
     * @return array<string> Array of event names
     */
    public function getEvents(): array;
}
