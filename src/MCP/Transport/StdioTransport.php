<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP\Transport;

use ClaudeAgents\MCP\Config\MCPServerConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

/**
 * STDIO transport for MCP server.
 *
 * Uses STDIN/STDOUT for JSON-RPC communication with MCP clients like Claude Desktop.
 */
class StdioTransport implements TransportInterface
{
    private LoggerInterface $logger;
    private bool $connected = false;
    private ?ReadableResourceStream $stdin = null;
    private ?WritableResourceStream $stdout = null;
    private string $buffer = '';

    public function __construct(
        private readonly MCPServerConfig $config,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function start(): void
    {
        $this->logger->info('Starting STDIO transport');
        
        $loop = Loop::get();
        
        // Create streams for STDIN and STDOUT
        $this->stdin = new ReadableResourceStream(STDIN, $loop);
        $this->stdout = new WritableResourceStream(STDOUT, $loop);
        
        $this->connected = true;
        
        // Handle incoming data
        $this->stdin->on('data', function ($data) {
            $this->handleData($data);
        });
        
        $this->stdin->on('close', function () {
            $this->logger->info('STDIN closed');
            $this->connected = false;
        });
        
        $this->stdin->on('error', function (\Exception $e) {
            $this->logger->error("STDIN error: {$e->getMessage()}");
            $this->connected = false;
        });
    }

    /**
     * Handle incoming data.
     */
    private function handleData(string $data): void
    {
        $this->buffer .= $data;
        
        // Process complete JSON-RPC messages
        while (($pos = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 1);
            
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            
            try {
                $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $this->logger->debug("Received request: " . json_encode($request));
                
                // Process request in the main server loop
                // The server will call receive() to get this request
            } catch (\JsonException $e) {
                $this->logger->error("Invalid JSON: {$e->getMessage()}");
            }
        }
    }

    public function send(array $message): void
    {
        if (!$this->connected || $this->stdout === null) {
            throw new \RuntimeException('Transport not connected');
        }
        
        $json = json_encode($message, JSON_THROW_ON_ERROR);
        $this->logger->debug("Sending response: {$json}");
        
        $this->stdout->write($json . "\n");
    }

    public function receive(): ?array
    {
        // For STDIO, we need to run the event loop
        // This is a blocking operation
        $loop = Loop::get();
        $loop->run();
        
        return null;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function shutdown(): void
    {
        $this->logger->info('Shutting down STDIO transport');
        
        if ($this->stdin !== null) {
            $this->stdin->close();
        }
        
        if ($this->stdout !== null) {
            $this->stdout->close();
        }
        
        $this->connected = false;
    }

    public function getType(): string
    {
        return 'stdio';
    }
}
