# MCP Server Integration

The MCP (Model Context Protocol) Server Integration exposes the claude-php-agent framework's capabilities through the Model Context Protocol, enabling integration with MCP clients like Claude Desktop, IDEs, and other AI tools.

## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Available Tools](#available-tools)
- [Transport Protocols](#transport-protocols)
- [Claude Desktop Integration](#claude-desktop-integration)
- [Usage Examples](#usage-examples)
- [Architecture](#architecture)
- [Security](#security)
- [Troubleshooting](#troubleshooting)

## Overview

The MCP Server provides 15 tools organized into 5 categories:

- **Agent Discovery**: Search, list, and explore available agents
- **Agent Execution**: Run agents and check execution status
- **Tool Management**: Discover and query agent tools
- **Visualization**: Generate workflow diagrams and graphs
- **Configuration**: Manage agent configurations and instances

### Key Features

- 🚀 **15 MCP Tools** - Comprehensive agent management
- 🔄 **Dual Transport** - STDIO for Claude Desktop, SSE for web
- 🎨 **Workflow Visualization** - ASCII art + JSON graphs
- 💾 **Session Management** - Isolated per-client sessions
- 🔧 **Auto-Discovery** - Automatic agent and tool registration
- 📊 **Real-time Execution** - Run agents directly through MCP

## Installation

The MCP server is included in `claude-php/agent`. Ensure dependencies are installed:

```bash
composer install
```

Required dependencies:
- `php-mcp/server`: ^1.0
- `php-mcp/schema`: ^1.0
- `react/event-loop`: ^1.5
- `react/stream`: ^1.4

## Quick Start

### 1. Set API Key

```bash
export ANTHROPIC_API_KEY=your_api_key_here
```

### 2. Start the Server

```bash
php bin/mcp-server
```

The server starts with STDIO transport by default, ready for Claude Desktop.

### 3. Test the Server

Run the example:

```bash
php examples/mcp_server_example.php
```

## Configuration

### Basic Configuration

```php
use ClaudeAgents\MCP\Config\MCPServerConfig;

$config = new MCPServerConfig(
    serverName: 'claude-php-agent',
    serverVersion: '1.0.0',
    transport: 'stdio', // or 'sse'
    sessionTimeout: 3600,
    enableVisualization: true,
);
```

### From Array

```php
$config = MCPServerConfig::fromArray([
    'server_name' => 'my-agent-server',
    'transport' => 'sse',
    'sse_port' => 8080,
    'tools_enabled' => ['search_agents', 'run_agent'],
    'max_execution_time' => 300,
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `server_name` | string | `'claude-php-agent'` | Server name for identification |
| `server_version` | string | `'1.0.0'` | Server version |
| `description` | string | MCP server description | Human-readable description |
| `transport` | string | `'stdio'` | Transport: `'stdio'` or `'sse'` |
| `sse_endpoint` | string | `'/mcp'` | HTTP endpoint for SSE |
| `sse_port` | int | `8080` | Port for SSE server |
| `session_timeout` | int | `3600` | Session timeout in seconds |
| `enable_visualization` | bool | `true` | Enable visualization tools |
| `tools_enabled` | array | `[]` | Enabled tools (empty = all) |
| `max_execution_time` | int | `300` | Max agent execution time |

## Available Tools

### Agent Discovery

#### search_agents

Search for agents by name, type, or capabilities.

```json
{
  "query": "react",
  "type": "react",
  "capabilities": ["reasoning", "tool-use"]
}
```

#### list_agent_types

Get all available agent types.

```json
{}
```

Returns: `["react", "rag", "chain-of-thought", ...]`

#### get_agent_details

Get detailed information about a specific agent.

```json
{
  "agent_name": "ReactAgent"
}
```

#### count_agents

Count agents, optionally filtered by type.

```json
{
  "type": "rag"
}
```

### Agent Execution

#### run_agent

Execute an agent with specified parameters.

```json
{
  "agent_name": "ReactAgent",
  "input": "Calculate the sum of 15 and 27",
  "options": {
    "max_tokens": 2048
  },
  "session_id": "optional-session-id"
}
```

Returns execution result with output, timing, and session ID.

#### get_execution_status

Check status of a previous execution.

```json
{
  "session_id": "session_12345"
}
```

### Tool Management

#### list_tools

List all available tools.

```json
{}
```

#### search_tools

Search tools by name or description.

```json
{
  "query": "calculator"
}
```

#### get_tool_details

Get detailed tool information including schema.

```json
{
  "tool_name": "calculator"
}
```

### Visualization

#### visualize_workflow

Generate complete workflow visualization.

```json
{
  "agent_name": "RAGAgent",
  "tools": ["vector_search", "document_retrieval"]
}
```

Returns ASCII diagram, JSON graph, and text representation.

#### get_agent_graph

Get graph representation with vertices and edges.

```json
{
  "agent_name": "ReactAgent",
  "tools": []
}
```

#### export_agent_config

Export agent configuration as JSON.

```json
{
  "agent_name": "ChainOfThoughtAgent"
}
```

### Configuration

#### update_agent_config

Update agent configuration in a session.

```json
{
  "session_id": "session_12345",
  "config": {
    "max_tokens": 4096,
    "temperature": 0.7
  }
}
```

#### create_agent_instance

Create a configured agent instance.

```json
{
  "agent_name": "DialogAgent",
  "config": {
    "memory": true
  }
}
```

#### validate_agent_config

Validate configuration before applying.

```json
{
  "agent_name": "ReactAgent",
  "config": {
    "max_tokens": 2048
  }
}
```

## Transport Protocols

### STDIO Transport

Used with Claude Desktop and CLI clients.

**Start Server:**
```bash
php bin/mcp-server
```

**Communication:**
- STDIN: Receives JSON-RPC requests
- STDOUT: Sends JSON-RPC responses
- STDERR: Logging output

**Use Case:** Claude Desktop, terminal-based MCP clients

### SSE Transport

Used with web-based MCP clients.

**Start Server:**
```bash
php bin/mcp-server --transport=sse --port=8080
```

**Communication:**
- HTTP POST: Receives requests
- Server-Sent Events: Sends responses
- Keeps connection alive with pings

**Use Case:** Web applications, HTTP-based clients

## Claude Desktop Integration

### 1. Configure Claude Desktop

Edit your Claude Desktop configuration file:

**macOS/Linux:**
```bash
~/.config/claude/claude_desktop_config.json
```

**Windows:**
```
%APPDATA%\Claude\claude_desktop_config.json
```

### 2. Add MCP Server

```json
{
  "mcpServers": {
    "claude-php-agent": {
      "command": "php",
      "args": ["/absolute/path/to/claude-php-agent/bin/mcp-server"]
    }
  }
}
```

### 3. Restart Claude Desktop

The server will appear in the MCP tools menu.

### 4. Use Tools

Ask Claude to:
- "List all available agents"
- "Search for RAG agents"
- "Run a ReactAgent to solve this problem"
- "Visualize the workflow for ChainOfThoughtAgent"

## Usage Examples

### Example 1: Discover Agents

```php
$server = new MCPServer($client);

$request = [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/call',
    'params' => [
        'name' => 'search_agents',
        'arguments' => [
            'capabilities' => ['reasoning', 'tool-use']
        ]
    ]
];

$response = $server->handleRequest($request);
```

### Example 2: Execute Agent

```php
$request = [
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/call',
    'params' => [
        'name' => 'run_agent',
        'arguments' => [
            'agent_name' => 'ReactAgent',
            'input' => 'What is 15 + 27?',
        ]
    ]
];

$response = $server->handleRequest($request);
```

### Example 3: Visualize Workflow

```php
$request = [
    'jsonrpc' => '2.0',
    'id' => 3,
    'method' => 'tools/call',
    'params' => [
        'name' => 'visualize_workflow',
        'arguments' => [
            'agent_name' => 'RAGAgent',
            'tools' => ['vector_search']
        ]
    ]
];

$response = $server->handleRequest($request);
// Returns ASCII diagram, JSON graph, text representation
```

### Example 4: Custom Server

```php
use ClaudeAgents\MCP\MCPServer;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$server = new MCPServer($client, [
    'transport' => 'stdio',
    'enable_visualization' => true,
    'tools_enabled' => [
        'search_agents',
        'run_agent',
        'visualize_workflow',
    ],
]);

$server->start();
```

## Architecture

### Components

```
MCPServer
├── AgentRegistry (Agent discovery)
├── ToolRegistry (Tool management)
├── SessionManager (Session isolation)
├── Transport (STDIO or SSE)
└── MCP Tools (15 tools)
    ├── Agent Discovery (4 tools)
    ├── Agent Execution (2 tools)
    ├── Tool Management (3 tools)
    ├── Visualization (3 tools)
    └── Configuration (3 tools)
```

### Data Flow

1. **Client** sends MCP request via transport
2. **Transport** receives and parses request
3. **MCPServer** routes to appropriate tool
4. **Tool** executes using AgentRegistry/ToolRegistry
5. **Result** formatted as MCP response
6. **Transport** sends response to client

### Session Management

- Per-client session isolation
- Automatic session cleanup
- Memory persistence across calls
- Configurable timeout (default: 1 hour)

## Security

### Best Practices

1. **API Key Protection**
   - Never commit API keys to version control
   - Use environment variables
   - Rotate keys regularly

2. **Input Validation**
   - All tool inputs are validated
   - Schema enforcement
   - Parameter type checking

3. **Execution Limits**
   - Configurable max execution time
   - Session timeouts
   - Resource monitoring

4. **Network Security**
   - SSE transport: Use HTTPS in production
   - STDIO transport: Local access only
   - No direct file system access through tools

5. **Audit Logging**
   - Enable logging for production
   - Monitor agent executions
   - Track session activity

## Troubleshooting

### Server Won't Start

**Problem:** "Composer autoloader not found"
```bash
# Solution:
composer install
```

**Problem:** "ANTHROPIC_API_KEY environment variable not set"
```bash
# Solution:
export ANTHROPIC_API_KEY=your_key_here
```

### Claude Desktop Can't Connect

**Problem:** Server not appearing in tools menu

1. Check config path is correct (absolute path)
2. Verify PHP is in PATH
3. Restart Claude Desktop
4. Check server starts manually: `php bin/mcp-server`

### Tool Execution Fails

**Problem:** "Agent not found"
- Verify agent name is correct (case-sensitive)
- Use `list_agent_types` to see available agents

**Problem:** "Execution timeout"
- Increase `max_execution_time` in config
- Check agent has valid API key

### SSE Transport Issues

**Problem:** Connection keeps dropping
- Check firewall settings
- Increase session timeout
- Verify port is not in use

### Debug Mode

Enable debug logging:

```bash
php bin/mcp-server --debug
```

Or in code:

```php
$config = new MCPServerConfig(
    enableLogging: true,
    logPath: '/path/to/mcp-server.log',
);
```

## Advanced Topics

### Custom Tools

Extend AbstractMCPTool to create custom tools:

```php
use ClaudeAgents\MCP\AbstractMCPTool;

class MyCustomTool extends AbstractMCPTool
{
    public function getName(): string
    {
        return 'my_custom_tool';
    }

    public function getDescription(): string
    {
        return 'My custom MCP tool';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'param' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $params): array
    {
        $this->validateParams($params);
        // Your logic here
        return $this->success(['result' => 'done']);
    }
}
```

### Multiple Servers

Run multiple MCP servers on different ports:

```bash
# Server 1: STDIO
php bin/mcp-server

# Server 2: SSE on port 8080
php bin/mcp-server --transport=sse --port=8080

# Server 3: SSE on port 8081
php bin/mcp-server --transport=sse --port=8081
```

### Process Management

Use a process manager for production:

**systemd:**
```ini
[Unit]
Description=MCP Server for Claude PHP Agent
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/claude-php-agent
Environment="ANTHROPIC_API_KEY=your_key"
ExecStart=/usr/bin/php bin/mcp-server
Restart=always

[Install]
WantedBy=multi-user.target
```

**Supervisor:**
```ini
[program:mcp-server]
command=php /path/to/claude-php-agent/bin/mcp-server
directory=/path/to/claude-php-agent
environment=ANTHROPIC_API_KEY="your_key"
autostart=true
autorestart=true
user=www-data
```

## API Reference

See the [API Documentation](./api-reference.md) for detailed information about all classes and methods.

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](../CONTRIBUTING.md) for guidelines.

## License

MIT License. See [LICENSE](../LICENSE) for details.
