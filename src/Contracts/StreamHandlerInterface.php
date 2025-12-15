<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\Streaming\StreamEvent;

/**
 * Interface for streaming event handlers.
 */
interface StreamHandlerInterface
{
    /**
     * Handle a stream event.
     */
    public function handle(StreamEvent $event): void;

    /**
     * Get the handler name.
     */
    public function getName(): string;
}
