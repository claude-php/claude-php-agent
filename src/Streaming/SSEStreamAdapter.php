<?php

declare(strict_types=1);

namespace ClaudeAgents\Streaming;

use ClaudeAgents\Progress\AgentUpdate;

/**
 * Adapter to convert AgentUpdate callbacks to Server-Sent Events (SSE) format.
 *
 * This bridges the existing callback system to SSE for real-time web streaming.
 */
class SSEStreamAdapter
{
    private bool $autoFlush;
    private bool $includeComments;

    /**
     * @var resource|null
     */
    private $outputStream;

    /**
     * @param array<string, mixed> $options Configuration options:
     *   - auto_flush: Automatically flush output after each event (default: true)
     *   - include_comments: Include timestamp comments in stream (default: false)
     *   - output_stream: Custom output stream resource (default: STDOUT)
     */
    public function __construct(array $options = [])
    {
        $this->autoFlush = $options['auto_flush'] ?? true;
        $this->includeComments = $options['include_comments'] ?? false;
        $this->outputStream = $options['output_stream'] ?? STDOUT;
    }

    /**
     * Create a callback for use with Agent::onUpdate().
     *
     * @return callable Callback function that accepts AgentUpdate
     */
    public function createCallback(): callable
    {
        return function (AgentUpdate $update): void {
            $this->sendEvent($update->getType(), $update->toArray());
        };
    }

    /**
     * Create a callback for CodeGenerationAgent-style updates.
     *
     * @return callable Callback function that accepts (string $type, array $data)
     */
    public function createCodeGenerationCallback(): callable
    {
        return function (string $type, array $data): void {
            $this->sendEvent($type, $data);
        };
    }

    /**
     * Send an SSE event.
     *
     * @param string $event Event name
     * @param array<string, mixed> $data Event data
     */
    public function sendEvent(string $event, array $data): void
    {
        if ($this->includeComments) {
            $this->sendComment('Event: ' . $event . ' at ' . date('H:i:s'));
        }

        $this->write("event: {$event}\n");
        $this->write('data: ' . json_encode($data) . "\n\n");

        if ($this->autoFlush) {
            $this->flush();
        }
    }

    /**
     * Send a comment (for debugging/keepalive).
     */
    public function sendComment(string $message): void
    {
        $this->write(": {$message}\n\n");

        if ($this->autoFlush) {
            $this->flush();
        }
    }

    /**
     * Send a ping/keepalive message.
     */
    public function sendPing(): void
    {
        $this->write(": ping\n\n");

        if ($this->autoFlush) {
            $this->flush();
        }
    }

    /**
     * Send custom data without event name (uses default 'message' event).
     *
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): void
    {
        $this->write('data: ' . json_encode($data) . "\n\n");

        if ($this->autoFlush) {
            $this->flush();
        }
    }

    /**
     * Set retry timeout for client reconnection.
     *
     * @param int $milliseconds Milliseconds to wait before retry
     */
    public function setRetry(int $milliseconds): void
    {
        $this->write("retry: {$milliseconds}\n\n");

        if ($this->autoFlush) {
            $this->flush();
        }
    }

    /**
     * Write to output stream.
     */
    private function write(string $data): void
    {
        fwrite($this->outputStream, $data);
    }

    /**
     * Flush output buffers.
     */
    private function flush(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();
    }

    /**
     * Create adapter with headers already set up.
     */
    public static function withHeaders(array $options = []): self
    {
        SSEServer::setupHeaders();
        return new self($options);
    }
}
