# MCP Server Integration Tutorial: Connect to Claude Desktop

## Introduction

This tutorial will guide you through the Model Context Protocol (MCP) Server integration, which exposes your agent framework's capabilities to MCP clients like Claude Desktop, IDEs, and other AI tools.

By the end of this tutorial, you'll be able to:

- Start and configure the MCP server
- Integrate with Claude Desktop
- Use MCP tools to manage agents
- Create custom MCP tools
- Handle SSE transport for web applications
- Deploy MCP server in production

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of protocols and client-server architecture
- Claude Desktop app (for integration)

## Table of Contents

1. [Understanding MCP](#understanding-mcp)
2. [Setup and Installation](#setup-and-installation)
3. [Tutorial 1: Quick Start](#tutorial-1-quick-start)
4. [Tutorial 2: Claude Desktop Setup](#tutorial-2-claude-desktop-setup)
5. [Tutorial 3: Agent Discovery](#tutorial-3-agent-discovery)
6. [Tutorial 4: Agent Execution](#tutorial-4-agent-execution)
7. [Tutorial 5: Custom MCP Tools](#tutorial-5-custom-mcp-tools)
8. [Tutorial 6: Web Integration (SSE)](#tutorial-6-web-integration-sse)
9. [Tutorial 7: Production Deployment](#tutorial-7-production-deployment)
10. [Common Patterns](#common-patterns)
11. [Troubleshooting](#troubleshooting)
12. [Next Steps](#next-steps)

## Understanding MCP

The Model Context Protocol (MCP) is a standardized protocol for AI tools to communicate with external services and capabilities.

### What is MCP?

MCP allows:
- **Tool Discovery** - Clients discover available tools
- **Tool Execution** - Clients invoke tools with parameters
- **Resource Access** - Clients access data and services
- **Prompt Templates** - Share reusable prompts

### Architecture

```
┌──────────────────┐         ┌──────────────────┐
│  Claude Desktop  │◄───────►│   MCP Server     │
│   (MCP Client)   │  STDIO  │  (Your Agents)   │
└──────────────────┘         └──────────────────┘

┌──────────────────┐         ┌──────────────────┐
│   Web Browser    │◄───────►│   MCP Server     │
│   (MCP Client)   │   SSE   │  (Your Agents)   │
└──────────────────┘         └──────────────────┘
```

### Available MCP Tools

The server provides 15 tools in 5 categories:

**Agent Discovery:**
- `search_agents` - Search available agents
- `list_agents` - List all agents
- `get_agent_details` - Get agent information

**Agent Execution:**
- `run_agent` - Execute an agent
- `get_execution_status` - Check execution status

**Tool Management:**
- `list_agent_tools` - List agent tools
- `get_tool_details` - Get tool information

**Visualization:**
- `get_agent_graph` - Get workflow diagram

**Configuration:**
- `create_agent_instance` - Create agent instances
- `export_agent_config` - Export configurations

## Setup and Installation

The MCP server is included in `claude-php/agent` v0.7.0+.

### Install Dependencies

```bash
composer install
```

### Set API Key

```bash
export ANTHROPIC_API_KEY=your_api_key_here
```

Or create `.env`:

```env
ANTHROPIC_API_KEY=your_api_key_here
```

## Tutorial 1: Quick Start

Launch your first MCP server.

### Step 1: Start the Server

```bash
php bin/mcp-server
```

**Output:**
```
Starting MCP Server...
Server: claude-php-agent v1.0.0
Transport: stdio
Registered 15 tools
Server ready!
```

### Step 2: Test the Server

In another terminal:

```bash
php examples/mcp_server_example.php
```

### Step 3: Understand the Configuration

```php
<?php
// The server uses this default config

use ClaudeAgents\MCP\Config\MCPServerConfig;

$config = new MCPServerConfig(
    serverName: 'claude-php-agent',
    serverVersion: '1.0.0',
    transport: 'stdio',              // or 'sse'
    sessionTimeout: 3600,             // 1 hour
    enableVisualization: true,
    toolsEnabled: [],                 // Empty = all enabled
    maxExecutionTime: 300,            // 5 minutes
);
```

## Tutorial 2: Claude Desktop Setup

Connect your MCP server to Claude Desktop.

### Step 1: Find Claude Desktop Config

The configuration file location depends on your OS:

**macOS:**
```
~/Library/Application Support/Claude/claude_desktop_config.json
```

**Windows:**
```
%APPDATA%\Claude\claude_desktop_config.json
```

**Linux:**
```
~/.config/Claude/claude_desktop_config.json
```

### Step 2: Configure Claude Desktop

Edit `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "claude-php-agent": {
      "command": "php",
      "args": [
        "/absolute/path/to/claude-php-agent/bin/mcp-server"
      ],
      "env": {
        "ANTHROPIC_API_KEY": "your-api-key-here"
      }
    }
  }
}
```

**Important:** Use absolute paths!

### Step 3: Restart Claude Desktop

1. Quit Claude Desktop completely
2. Relaunch Claude Desktop
3. Check the hammer icon (🔨) for available tools

### Step 4: Test in Claude Desktop

In Claude Desktop, try:

```
Can you list the available agents?
```

Claude will use the `list_agents` MCP tool to query your server.

## Tutorial 3: Agent Discovery

Use MCP tools to discover agents.

### Step 1: Search for Agents

In Claude Desktop:

```
Search for agents that can help with code generation
```

Behind the scenes, this calls:
```json
{
  "tool": "search_agents",
  "parameters": {
    "query": "code generation"
  }
}
```

### Step 2: List All Agents

```
Show me all available agents
```

Uses:
```json
{
  "tool": "list_agents",
  "parameters": {}
}
```

### Step 3: Get Agent Details

```
Give me details about the ReactAgent
```

Uses:
```json
{
  "tool": "get_agent_details",
  "parameters": {
    "agent_name": "ReactAgent"
  }
}
```

## Tutorial 4: Agent Execution

Run agents through MCP.

### Step 1: Execute an Agent

In Claude Desktop:

```
Use the ReactAgent to calculate 25 * 17 + 100
```

Claude will:
1. Use `create_agent_instance` to set up the agent
2. Use `run_agent` to execute with your query
3. Return the result

### Step 2: Check Execution Status

For long-running agents:

```
What's the status of my last agent execution?
```

Uses:
```json
{
  "tool": "get_execution_status",
  "parameters": {
    "execution_id": "exec_12345"
  }
}
```

### Step 3: Monitor Progress

```php
// In your agent code, emit updates
$agent->onUpdate(function (AgentUpdate $update) {
    // These are sent to the MCP client
    echo "Progress: {$update->getType()}\n";
});
```

## Tutorial 5: Custom MCP Tools

Create your own MCP tools.

### Step 1: Define a Custom Tool

```php
<?php

namespace ClaudeAgents\MCP\Tools;

use PhpMcp\Server\Tool;
use PhpMcp\Types\ToolCall;

class CustomAnalyticsTool extends Tool
{
    protected string $name = 'get_analytics';
    protected string $description = 'Get agent analytics and metrics';
    
    public function execute(ToolCall $call): array
    {
        $params = $call->getParams();
        $period = $params['period'] ?? 'day';
        
        // Get analytics from your system
        $analytics = $this->getAnalytics($period);
        
        return [
            'period' => $period,
            'total_runs' => $analytics['total_runs'],
            'success_rate' => $analytics['success_rate'],
            'avg_duration_ms' => $analytics['avg_duration'],
        ];
    }
    
    private function getAnalytics(string $period): array
    {
        // Your analytics logic
        return [
            'total_runs' => 150,
            'success_rate' => 95.5,
            'avg_duration' => 1250.5,
        ];
    }
    
    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['hour', 'day', 'week', 'month'],
                    'description' => 'Time period for analytics',
                ],
            ],
        ];
    }
}
```

### Step 2: Register Custom Tool

```php
use ClaudeAgents\MCP\MCPServer;

$server = new MCPServer($config);

// Register custom tool
$server->registerTool(new CustomAnalyticsTool());

$server->start();
```

### Step 3: Use Custom Tool

In Claude Desktop:

```
Show me agent analytics for the past week
```

## Tutorial 6: Web Integration (SSE)

Use SSE transport for web applications.

### Step 1: Configure SSE Server

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\MCP\MCPServer;
use ClaudeAgents\MCP\Config\MCPServerConfig;

$config = new MCPServerConfig(
    serverName: 'claude-php-agent-web',
    serverVersion: '1.0.0',
    transport: 'sse',           // Use SSE instead of STDIO
    ssePort: 8080,              // Port for SSE server
    sseHost: '0.0.0.0',         // Listen on all interfaces
    allowedOrigins: [
        'http://localhost:3000',
        'https://your-app.com',
    ],
);

$server = new MCPServer($config);
$server->start();
```

### Step 2: Connect from Web App

```javascript
// Frontend JavaScript
const eventSource = new EventSource('http://localhost:8080/mcp');

eventSource.addEventListener('tool_result', (event) => {
    const data = JSON.parse(event.data);
    console.log('Tool result:', data);
});

// Send tool call
fetch('http://localhost:8080/mcp/call', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        tool: 'list_agents',
        params: {}
    })
});
```

### Step 3: CORS Configuration

```php
$config = new MCPServerConfig(
    transport: 'sse',
    ssePort: 8080,
    allowedOrigins: ['*'], // Allow all (development only!)
    corsHeaders: [
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type',
    ],
);
```

## Tutorial 7: Production Deployment

Deploy MCP server in production.

### Step 1: Process Manager (Supervisor)

Create `supervisor.conf`:

```ini
[program:mcp-server]
command=php /var/www/agent/bin/mcp-server
directory=/var/www/agent
autostart=true
autorestart=true
user=www-data
environment=ANTHROPIC_API_KEY="your-key-here"
stdout_logfile=/var/log/mcp-server.log
stderr_logfile=/var/log/mcp-server-error.log
```

Start with supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mcp-server
```

### Step 2: Docker Deployment

Create `Dockerfile`:

```dockerfile
FROM php:8.3-cli

WORKDIR /app

# Install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy application
COPY . .

# Set environment
ENV ANTHROPIC_API_KEY=""

# Run MCP server
CMD ["php", "bin/mcp-server"]
```

Build and run:

```bash
docker build -t mcp-server .
docker run -e ANTHROPIC_API_KEY=your-key mcp-server
```

### Step 3: Health Monitoring

```php
// Add health check endpoint (for SSE transport)
$server->registerTool(new class extends Tool {
    protected string $name = 'health_check';
    protected string $description = 'Check server health';
    
    public function execute(ToolCall $call): array
    {
        return [
            'status' => 'healthy',
            'uptime' => $this->getUptime(),
            'memory_mb' => memory_get_usage(true) / 1024 / 1024,
            'tools_count' => count($this->server->getTools()),
        ];
    }
    
    private function getUptime(): int
    {
        // Track server start time
        static $startTime = null;
        if ($startTime === null) {
            $startTime = time();
        }
        return time() - $startTime;
    }
});
```

## Common Patterns

### Pattern 1: Claude Desktop Quick Actions

Configure Claude Desktop for common tasks:

```json
{
  "mcpServers": {
    "agent-search": {
      "command": "php",
      "args": ["/path/to/bin/mcp-server"],
      "description": "Search and execute AI agents"
    }
  }
}
```

Then in Claude Desktop:

```
Find an agent to help me analyze customer feedback
```

### Pattern 2: Multi-Server Setup

Run multiple MCP servers for different purposes:

```json
{
  "mcpServers": {
    "production-agents": {
      "command": "php",
      "args": ["/path/to/prod/bin/mcp-server"],
      "env": {"ENV": "production"}
    },
    "dev-agents": {
      "command": "php",
      "args": ["/path/to/dev/bin/mcp-server"],
      "env": {"ENV": "development"}
    }
  }
}
```

### Pattern 3: Agent Library Server

```php
// Create a specialized MCP server for your agent library
class AgentLibraryServer extends MCPServer
{
    public function __construct()
    {
        parent::__construct(new MCPServerConfig(
            serverName: 'my-agent-library',
            serverVersion: '1.0.0',
        ));
        
        // Register only specific tools
        $this->enableTools([
            'search_agents',
            'list_agents',
            'get_agent_details',
            'run_agent',
        ]);
    }
}
```

## Troubleshooting

### Problem: Claude Desktop not showing tools

**Causes:**
1. Server not running
2. Configuration path incorrect
3. Server crashed on startup

**Solutions:**

```bash
# Check if server is running
ps aux | grep mcp-server

# Test server manually
php bin/mcp-server

# Check Claude Desktop logs (macOS)
tail -f ~/Library/Logs/Claude/mcp*.log

# Verify config path
cat ~/Library/Application\ Support/Claude/claude_desktop_config.json
```

### Problem: "Permission denied" error

**Cause:** Insufficient permissions for socket or port.

**Solution:**

```bash
# Make script executable
chmod +x bin/mcp-server

# For SSE on port 80, use sudo or higher port
php bin/mcp-server --port=8080
```

### Problem: Server crashes on startup

**Cause:** Missing dependencies or API key.

**Solution:**

```bash
# Check dependencies
composer install

# Verify API key
echo $ANTHROPIC_API_KEY

# Run with debug mode
php bin/mcp-server --debug
```

### Problem: "Tool not found" error

**Cause:** Tool disabled or not registered.

**Solution:**

```php
// Check enabled tools
$config = new MCPServerConfig(
    toolsEnabled: [], // Empty = all enabled
    // Or specify: ['search_agents', 'run_agent']
);

// Verify tool registration
$tools = $server->getTools();
foreach ($tools as $tool) {
    echo "- {$tool->getName()}\n";
}
```

## Next Steps

### Related Tutorials

- **[Production Patterns Tutorial](./ProductionPatterns_Tutorial.md)** - Deploy MCP in production
- **[Code Generation Tutorial](./CodeGeneration_Tutorial.md)** - Use MCP with code generation
- **[Services System Tutorial](./ServicesSystem_Tutorial.md)** - Expose services via MCP

### Further Reading

- [MCP Server Integration Documentation](../mcp-server-integration.md)
- [MCP Protocol Specification](https://modelcontextprotocol.io/)
- [Tool System Guide](../Tools.md)

### Example Code

All examples from this tutorial are available in:
- `examples/tutorials/mcp-server/`
- `examples/mcp_server_example.php`

### What You've Learned

✓ Start and configure MCP server
✓ Integrate with Claude Desktop
✓ Discover and use agents via MCP
✓ Execute agents through protocol
✓ Create custom MCP tools
✓ Handle SSE for web integration
✓ Deploy MCP server in production
✓ Monitor and troubleshoot MCP servers

**Ready for more?** Continue with the [Code Generation Tutorial](./CodeGeneration_Tutorial.md) to generate code with AI!

---

*Tutorial Version: 1.0*
*Framework Version: v0.7.0+*
*Last Updated: February 2026*
