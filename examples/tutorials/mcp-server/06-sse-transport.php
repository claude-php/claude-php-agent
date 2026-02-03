<?php

/**
 * MCP Tutorial 6: SSE Transport
 * 
 * Run: php examples/tutorials/mcp-server/06-sse-transport.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\MCP\Config\MCPServerConfig;

echo "=== MCP Tutorial 6: SSE Transport ===\n\n";

// SSE configuration
$config = new MCPServerConfig(
    serverName: 'claude-php-agent-web',
    serverVersion: '1.0.0',
    transport: 'sse',
    ssePort: 8080,
    sseHost: '0.0.0.0',
    allowedOrigins: [
        'http://localhost:3000',
        'https://your-app.com',
    ],
);

echo "SSE Server Configuration:\n";
echo "- Transport: SSE\n";
echo "- Port: {$config->ssePort}\n";
echo "- Host: {$config->sseHost}\n";
echo "- Allowed Origins: " . implode(', ', $config->allowedOrigins) . "\n\n";

echo "To start SSE server:\n";
echo "  php bin/mcp-server --transport=sse --port=8080\n\n";

echo "Client connection:\n";
echo "  const es = new EventSource('http://localhost:8080/mcp');\n\n";

echo "✓ See docs/tutorials/MCPServer_Tutorial.md for full example\n";
