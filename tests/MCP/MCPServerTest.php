<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\MCP;

use ClaudeAgents\MCP\MCPServer;
use ClaudeAgents\MCP\Config\MCPServerConfig;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;
use Mockery;

class MCPServerTest extends TestCase
{
    private ClaudePhp $client;
    private MCPServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(ClaudePhp::class);
        $this->server = new MCPServer($this->client, [
            'transport' => 'stdio',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testServerInitialization(): void
    {
        $this->assertInstanceOf(MCPServer::class, $this->server);
    }

    public function testHandleInitializeRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('serverInfo', $response['result']);
        $this->assertEquals('claude-php-agent', $response['result']['serverInfo']['name']);
    }

    public function testHandleListToolsRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
        $this->assertIsArray($response['result']['tools']);
        $this->assertGreaterThan(0, count($response['result']['tools']));
    }

    public function testHandleToolCallRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'count_agents',
                'arguments' => [],
            ],
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('result', $response);
    }

    public function testHandleUnknownMethod(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'unknown_method',
        ];

        $response = $this->server->handleRequest($request);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Unknown method', $response['error']['message']);
    }

    public function testHandleUnknownTool(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'nonexistent_tool',
                'arguments' => [],
            ],
        ];

        $response = $this->server->handleRequest($request);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Unknown tool', $response['error']['message']);
    }

    public function testGetRegistries(): void
    {
        $this->assertNotNull($this->server->getAgentRegistry());
        $this->assertNotNull($this->server->getToolRegistry());
        $this->assertNotNull($this->server->getSessionManager());
    }

    public function testGetMCPTools(): void
    {
        $tools = $this->server->getMCPTools();
        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);
        
        // Check for key tools
        $this->assertArrayHasKey('search_agents', $tools);
        $this->assertArrayHasKey('run_agent', $tools);
        $this->assertArrayHasKey('list_tools', $tools);
    }
}
