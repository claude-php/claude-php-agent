# MCP Server Module

The Model Context Protocol (MCP) Server module exposes the claude-php-agent framework's capabilities through the standardized MCP protocol, enabling seamless integration with MCP clients like Claude Desktop, IDEs, and other AI tools.

## Quick Start

```bash
# 1. Set your API key
export ANTHROPIC_API_KEY=your_api_key_here

# 2. Start the MCP server
php bin/mcp-server

# 3. Configure Claude Desktop (see documentation)
```

## Features

- **15 MCP Tools** across 5 categories
- **Dual Transport Support** - STDIO and SSE/HTTP
- **Agent Discovery** - Search and explore 16+ agent types
- **Real-time Execution** - Run agents directly through MCP
- **Workflow Visualization** - ASCII art diagrams and JSON graphs
- **Session Management** - Isolated per-client sessions with memory
- **Auto-Discovery** - Automatic agent and tool registration

## Architecture

```
src/MCP/
├── MCPServer.php              # Main server class
├── AgentRegistry.php          # Agent discovery and metadata
├── SessionManager.php         # Session isolation and memory
├── AbstractMCPTool.php        # Base class for MCP tools
├── Config/
│   └── MCPServerConfig.php    # Server configuration
├── Contracts/
│   └── MCPToolInterface.php   # Tool interface
├── Transport/
│   ├── TransportInterface.php # Transport abstraction
│   ├── StdioTransport.php     # STDIO for Claude Desktop
│   └── SSETransport.php       # SSE/HTTP for web clients
├── Tools/                     # 15 MCP tools
│   ├── SearchAgentsTool.php
│   ├── RunAgentTool.php
│   └── ...
└── Visualization/
    └── WorkflowVisualizer.php # ASCII art and graph generation
```

## Available Tools

### Agent Discovery (4 tools)
- `search_agents` - Search agents by name, type, or capabilities
- `list_agent_types` - Get all agent types
- `get_agent_details` - Get detailed agent information
- `count_agents` - Count agents by type

### Agent Execution (2 tools)
- `run_agent` - Execute an agent with parameters
- `get_execution_status` - Check execution status

### Tool Management (3 tools)
- `list_tools` - List all available tools
- `search_tools` - Search tools by query
- `get_tool_details` - Get tool schema and documentation

### Visualization (3 tools)
- `visualize_workflow` - Generate complete visualization
- `get_agent_graph` - Get graph representation
- `export_agent_config` - Export configuration as JSON

### Configuration (3 tools)
- `update_agent_config` - Update agent parameters
- `create_agent_instance` - Create configured instance
- `validate_agent_config` - Validate configuration

## Usage Examples

### Basic Server

```php
use ClaudeAgents\MCP\MCPServer;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$server = new MCPServer($client);
$server->start(); // Starts with STDIO transport
```

### Custom Configuration

```php
$config = MCPServerConfig::fromArray([
    'transport' => 'sse',
    'sse_port' => 8080,
    'tools_enabled' => ['search_agents', 'run_agent'],
    'session_timeout' => 7200,
]);

$server = new MCPServer($client, $config);
$server->start();
```

### Programmatic Interaction

```php
$request = [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/call',
    'params' => [
        'name' => 'search_agents',
        'arguments' => ['query' => 'react']
    ]
];

$response = $server->handleRequest($request);
```

## Claude Desktop Integration

1. **Edit Claude Desktop config:**
   ```json
   {
     "mcpServers": {
       "claude-php-agent": {
         "command": "php",
         "args": ["/path/to/claude-php-agent/bin/mcp-server"]
       }
     }
   }
   ```

2. **Restart Claude Desktop**

3. **Use tools in Claude:**
   - "List all available agents"
   - "Run a ReactAgent to solve this problem"
   - "Visualize the RAGAgent workflow"

## Testing

```bash
# Run MCP tests
./vendor/bin/phpunit tests/MCP

# Run example
php examples/mcp_server_example.php
```

## Documentation

- [Full Documentation](../../docs/mcp-server-integration.md)
- [MCP Protocol Specification](https://modelcontextprotocol.io)
- [Claude Desktop Guide](https://claude.ai/docs/mcp)

## License

MIT License - See [LICENSE](../../LICENSE)
