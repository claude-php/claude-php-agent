<?php

/**
 * MCP Tutorial 2: Configuration
 * 
 * Run: php examples/tutorials/mcp-server/02-configuration.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\MCP\Config\MCPServerConfig;

echo "=== MCP Tutorial 2: Configuration ===\n\n";

// Basic configuration
$config = new MCPServerConfig(
    serverName: 'my-agent-server',
    serverVersion: '1.0.0',
    transport: 'stdio',
    sessionTimeout: 3600,
    enableVisualization: true,
);

echo "Server Configuration:\n";
echo "- Name: {$config->serverName}\n";
echo "- Version: {$config->serverVersion}\n";
echo "- Transport: {$config->transport}\n";
echo "- Session Timeout: {$config->sessionTimeout}s\n";
echo "- Visualization: " . ($config->enableVisualization ? 'Enabled' : 'Disabled') . "\n";

echo "\n✓ Example complete!\n";
