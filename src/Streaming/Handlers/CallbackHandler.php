<?php

declare(strict_types=1);

namespace ClaudeAgents\Streaming\Handlers;

use ClaudeAgents\Contracts\StreamHandlerInterface;
use ClaudeAgents\Streaming\StreamEvent;

/**
 * Stream handler that calls a custom callback for each event.
 */
class CallbackHandler implements StreamHandlerInterface
{
    /**
     * @var callable
     */
    private $callback;

    /**
     * @param callable $callback Function that receives StreamEvent
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function handle(StreamEvent $event): void
    {
        ($this->callback)($event);
    }

    public function getName(): string
    {
        return 'callback';
    }
}
