<?php

declare(strict_types=1);

namespace ClaudeAgents\Streaming;

/**
 * Helper for Server-Sent Events (SSE) server setup and management.
 *
 * Provides utilities for setting up SSE endpoints and managing connections.
 */
class SSEServer
{
    /**
     * Setup HTTP headers for SSE streaming.
     *
     * Call this before sending any output.
     */
    public static function setupHeaders(): void
    {
        // Prevent PHP from buffering output
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', 'false');

        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        // CORS headers (configure as needed)
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            header('Access-Control-Allow-Credentials: true');
        }
    }

    /**
     * Send a ping/keepalive message.
     *
     * Keeps the connection alive and prevents timeout.
     */
    public static function sendPing(): void
    {
        echo ": ping\n\n";
        self::flush();
    }

    /**
     * Send a comment message.
     *
     * Comments are prefixed with : and ignored by clients.
     */
    public static function sendComment(string $message): void
    {
        echo ": {$message}\n\n";
        self::flush();
    }

    /**
     * Send retry timeout to client.
     *
     * @param int $milliseconds Time in milliseconds before client should retry
     */
    public static function sendRetry(int $milliseconds): void
    {
        echo "retry: {$milliseconds}\n\n";
        self::flush();
    }

    /**
     * Send an event.
     *
     * @param string $event Event name
     * @param array<string, mixed> $data Event data
     */
    public static function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        self::flush();
    }

    /**
     * Send data (uses default 'message' event).
     *
     * @param array<string, mixed> $data Event data
     */
    public static function sendData(array $data): void
    {
        echo 'data: ' . json_encode($data) . "\n\n";
        self::flush();
    }

    /**
     * Flush output buffers to client.
     */
    public static function flush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }

    /**
     * Check if client is still connected.
     *
     * @return bool True if connected, false if disconnected
     */
    public static function isConnected(): bool
    {
        return connection_status() === CONNECTION_NORMAL;
    }

    /**
     * Run an SSE event loop with periodic keepalive.
     *
     * @param callable $callback Function to call each iteration: fn(): bool (return false to stop)
     * @param int $keepaliveSeconds Seconds between keepalive pings (default: 15)
     * @param int $maxDuration Maximum duration in seconds (default: 300 = 5 minutes)
     */
    public static function eventLoop(
        callable $callback,
        int $keepaliveSeconds = 15,
        int $maxDuration = 300
    ): void {
        self::setupHeaders();

        $startTime = time();
        $lastKeepalive = time();

        while (self::isConnected()) {
            // Check max duration
            if (time() - $startTime > $maxDuration) {
                self::sendComment('Max duration reached, closing connection');
                break;
            }

            // Send keepalive
            if (time() - $lastKeepalive >= $keepaliveSeconds) {
                self::sendPing();
                $lastKeepalive = time();
            }

            // Run callback
            try {
                $continue = $callback();
                if ($continue === false) {
                    break;
                }
            } catch (\Throwable $e) {
                self::sendEvent('error', [
                    'message' => $e->getMessage(),
                    'type' => get_class($e),
                ]);
                break;
            }

            // Small sleep to prevent tight loop
            usleep(100000); // 0.1 seconds
        }
    }

    /**
     * Create a simple SSE endpoint that streams messages from a queue.
     *
     * @param callable $messageGenerator Generator function: fn(): ?array
     * @param int $pollInterval Milliseconds between polls (default: 100)
     */
    public static function streamFromGenerator(
        callable $messageGenerator,
        int $pollInterval = 100
    ): void {
        self::setupHeaders();

        while (self::isConnected()) {
            $message = $messageGenerator();

            if ($message === null) {
                usleep($pollInterval * 1000);
                continue;
            }

            if ($message === false) {
                break;
            }

            if (is_array($message)) {
                $event = $message['event'] ?? 'message';
                $data = $message['data'] ?? $message;
                self::sendEvent($event, $data);
            }
        }
    }
}
