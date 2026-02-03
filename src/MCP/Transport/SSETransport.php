<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP\Transport;

use ClaudeAgents\MCP\Config\MCPServerConfig;
use ClaudeAgents\Streaming\SSEServer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * SSE (Server-Sent Events) transport for MCP server.
 *
 * Uses HTTP with SSE for communication with web-based MCP clients.
 */
class SSETransport implements TransportInterface
{
    private LoggerInterface $logger;
    private bool $connected = false;
    
    /**
     * @var array<string, mixed>|null
     */
    private ?array $pendingRequest = null;

    public function __construct(
        private readonly MCPServerConfig $config,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function start(): void
    {
        $this->logger->info("Starting SSE transport on port {$this->config->ssePort}");
        
        // Setup SSE headers
        SSEServer::setupHeaders();
        
        $this->connected = true;
        
        // Send initial connection message
        SSEServer::sendEvent('connected', [
            'server' => $this->config->serverName,
            'version' => $this->config->serverVersion,
            'description' => $this->config->description,
        ]);
    }

    public function send(array $message): void
    {
        if (!$this->connected) {
            throw new \RuntimeException('Transport not connected');
        }
        
        $this->logger->debug("Sending SSE message: " . json_encode($message));
        
        // Send as SSE event
        SSEServer::sendEvent('mcp_response', $message);
        SSEServer::flush();
    }

    public function receive(): ?array
    {
        // For SSE, requests come via HTTP POST
        // Check if there's a pending request
        if ($this->pendingRequest !== null) {
            $request = $this->pendingRequest;
            $this->pendingRequest = null;
            return $request;
        }
        
        // Check for POST data
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = file_get_contents('php://input');
            
            if ($input === false || $input === '') {
                return null;
            }
            
            try {
                $request = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
                $this->logger->debug("Received request: " . json_encode($request));
                return $request;
            } catch (\JsonException $e) {
                $this->logger->error("Invalid JSON: {$e->getMessage()}");
                return null;
            }
        }
        
        // Keep connection alive
        SSEServer::sendPing();
        usleep(100000); // 100ms
        
        return null;
    }

    public function isConnected(): bool
    {
        // Check if client is still connected
        if (connection_aborted()) {
            $this->connected = false;
        }
        
        return $this->connected;
    }

    public function shutdown(): void
    {
        $this->logger->info('Shutting down SSE transport');
        
        if ($this->connected) {
            SSEServer::sendEvent('disconnected', [
                'message' => 'Server shutting down',
            ]);
        }
        
        $this->connected = false;
    }

    public function getType(): string
    {
        return 'sse';
    }
}
