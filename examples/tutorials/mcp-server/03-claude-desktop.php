<?php

/**
 * MCP Tutorial 3: Claude Desktop Setup
 * 
 * This file shows the configuration needed for Claude Desktop
 */

declare(strict_types=1);

echo "=== MCP Tutorial 3: Claude Desktop Setup ===\n\n";

echo "1. Find your Claude Desktop config file:\n\n";

if (PHP_OS_FAMILY === 'Darwin') {
    echo "   macOS: ~/Library/Application Support/Claude/claude_desktop_config.json\n";
} elseif (PHP_OS_FAMILY === 'Windows') {
    echo "   Windows: %APPDATA%\\Claude\\claude_desktop_config.json\n";
} else {
    echo "   Linux: ~/.config/Claude/claude_desktop_config.json\n";
}

echo "\n2. Add this configuration:\n\n";

$configPath = __DIR__ . '/../../../bin/mcp-server';
$config = [
    'mcpServers' => [
        'claude-php-agent' => [
            'command' => 'php',
            'args' => [$configPath],
            'env' => [
                'ANTHROPIC_API_KEY' => 'your-api-key-here',
            ],
        ],
    ],
];

echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "3. Restart Claude Desktop\n\n";

echo "✓ See docs/tutorials/MCPServer_Tutorial.md for full instructions\n";
