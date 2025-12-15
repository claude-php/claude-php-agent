<?php

declare(strict_types=1);

namespace ClaudeAgents\Streaming\Handlers;

use ClaudeAgents\Contracts\StreamHandlerInterface;
use ClaudeAgents\Streaming\StreamEvent;

/**
 * Stream handler that prints to console output.
 */
class ConsoleHandler implements StreamHandlerInterface
{
    /**
     * @param bool $newline Whether to add newlines after text
     * @param string $prefix Prefix for each output line
     */
    public function __construct(
        private readonly bool $newline = false,
        private readonly string $prefix = '',
    ) {
    }

    public function handle(StreamEvent $event): void
    {
        if ($event->isText()) {
            $output = $event->getText();
            if (! empty($this->prefix)) {
                $output = $this->prefix . $output;
            }
            echo $output;
            if ($this->newline && str_ends_with($output, '.')) {
                echo "\n";
            }
        }
    }

    public function getName(): string
    {
        return 'console';
    }
}
