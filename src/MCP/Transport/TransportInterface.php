<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP\Transport;

/**
 * Interface for MCP transport implementations.
 *
 * Transports handle the communication protocol between MCP clients and server.
 */
interface TransportInterface
{
    /**
     * Start the transport and begin listening for messages.
     */
    public function start(): void;

    /**
     * Send a message to the client.
     *
     * @param array<string, mixed> $message
     */
    public function send(array $message): void;

    /**
     * Receive a message from the client (blocking).
     *
     * @return array<string, mixed>|null
     */
    public function receive(): ?array;

    /**
     * Check if the transport is connected.
     */
    public function isConnected(): bool;

    /**
     * Shutdown the transport and cleanup resources.
     */
    public function shutdown(): void;

    /**
     * Get transport type identifier.
     */
    public function getType(): string;
}
