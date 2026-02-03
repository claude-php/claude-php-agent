<?php

declare(strict_types=1);

/**
 * MCP Server Example
 * 
 * Demonstrates how to use the MCP Server with both STDIO and SSE transports.
 */

require __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\MCP\MCPServer;
use ClaudeAgents\MCP\Config\MCPServerConfig;
use ClaudePhp\ClaudePhp;

// Load API key
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "Error: ANTHROPIC_API_KEY environment variable not set.\n";
    exit(1);
}

// Create Claude client
$client = new ClaudePhp(apiKey: $apiKey);

echo "MCP Server Examples\n";
echo "===================\n\n";

// Example 1: STDIO Transport (for Claude Desktop)
echo "Example 1: STDIO Transport\n";
echo "--------------------------\n";
echo "This transport is used with Claude Desktop and CLI clients.\n\n";

$stdioConfig = new MCPServerConfig(
    serverName: 'claude-php-agent-stdio',
    transport: 'stdio',
    enableVisualization: true,
);

echo "Configuration:\n";
echo "  Transport: {$stdioConfig->transport}\n";
echo "  Server: {$stdioConfig->serverName}\n";
echo "  Version: {$stdioConfig->serverVersion}\n\n";

echo "To use with Claude Desktop, add this to your config:\n";
echo json_encode([
    'mcpServers' => [
        'claude-php-agent' => [
            'command' => 'php',
            'args' => [__DIR__ . '/../bin/mcp-server'],
        ],
    ],
], JSON_PRETTY_PRINT) . "\n\n";

// Uncomment to start STDIO server (will block)
// $stdioServer = new MCPServer($client, $stdioConfig);
// $stdioServer->start();

// Example 2: SSE Transport (for web clients)
echo "Example 2: SSE/HTTP Transport\n";
echo "-----------------------------\n";
echo "This transport is used with web-based MCP clients.\n\n";

$sseConfig = new MCPServerConfig(
    serverName: 'claude-php-agent-sse',
    transport: 'sse',
    sseEndpoint: '/mcp',
    ssePort: 8080,
    enableVisualization: true,
);

echo "Configuration:\n";
echo "  Transport: {$sseConfig->transport}\n";
echo "  Port: {$sseConfig->ssePort}\n";
echo "  Endpoint: {$sseConfig->sseEndpoint}\n\n";

echo "To start SSE server, uncomment the code below:\n\n";

// Uncomment to start SSE server (will block)
// $sseServer = new MCPServer($client, $sseConfig);
// $sseServer->start();

// Example 3: Custom Configuration
echo "Example 3: Custom Configuration\n";
echo "-------------------------------\n\n";

$customConfig = MCPServerConfig::fromArray([
    'server_name' => 'my-custom-agent-server',
    'server_version' => '2.0.0',
    'description' => 'Custom MCP server with specific tools',
    'transport' => 'stdio',
    'session_timeout' => 7200, // 2 hours
    'enable_visualization' => true,
    'tools_enabled' => [
        'search_agents',
        'run_agent',
        'get_agent_details',
        'visualize_workflow',
    ],
    'max_execution_time' => 600, // 10 minutes
]);

echo "Custom configuration created:\n";
print_r($customConfig->toArray());
echo "\n";

// Example 4: Programmatic Server Interaction
echo "Example 4: Programmatic Interaction\n";
echo "-----------------------------------\n\n";

$server = new MCPServer($client, [
    'transport' => 'stdio',
    'enable_visualization' => true,
]);

echo "Server created successfully!\n";
echo "Registered tools:\n";

$tools = $server->getMCPTools();
foreach ($tools as $name => $tool) {
    echo "  - {$name}: {$tool->getDescription()}\n";
}
echo "\n";

echo "Agent Registry:\n";
$registry = $server->getAgentRegistry();
echo "  Total agents: " . $registry->count() . "\n";
echo "  Agent types: " . implode(', ', $registry->getTypes()) . "\n\n";

// Example 5: Testing MCP Request Handling
echo "Example 5: Testing Request Handling\n";
echo "-----------------------------------\n\n";

// Simulate an initialize request
$initRequest = [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'initialize',
    'params' => [
        'protocolVersion' => '2024-11-05',
        'clientInfo' => [
            'name' => 'test-client',
            'version' => '1.0.0',
        ],
    ],
];

$initResponse = $server->handleRequest($initRequest);
echo "Initialize Response:\n";
echo json_encode($initResponse, JSON_PRETTY_PRINT) . "\n\n";

// Simulate a list tools request
$listToolsRequest = [
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/list',
];

$listToolsResponse = $server->handleRequest($listToolsRequest);
echo "List Tools Response (first 3 tools):\n";
$tools = array_slice($listToolsResponse['result']['tools'] ?? [], 0, 3);
foreach ($tools as $tool) {
    echo "  - {$tool['name']}: {$tool['description']}\n";
}
echo "  ... and " . (count($listToolsResponse['result']['tools']) - 3) . " more\n\n";

// Simulate a tool call request
$toolCallRequest = [
    'jsonrpc' => '2.0',
    'id' => 3,
    'method' => 'tools/call',
    'params' => [
        'name' => 'search_agents',
        'arguments' => [
            'query' => 'react',
        ],
    ],
];

$toolCallResponse = $server->handleRequest($toolCallRequest);
echo "Tool Call Response (search_agents with query='react'):\n";
if (isset($toolCallResponse['result']['content'][0]['text'])) {
    $result = json_decode($toolCallResponse['result']['content'][0]['text'], true);
    echo "  Found " . ($result['data']['count'] ?? 0) . " agents\n";
}
echo "\n";

echo "Examples completed! To actually run the server, use:\n";
echo "  php bin/mcp-server\n\n";
