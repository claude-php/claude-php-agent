<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\MCP;

use ClaudeAgents\MCP\MCPServer;
use ClaudeAgents\MCP\Config\MCPServerConfig;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for MCP Server with real API calls.
 * 
 * @group integration
 * @group mcp
 */
class MCPServerIntegrationTest extends TestCase
{
    private ClaudePhp $client;
    private MCPServer $server;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        // Load .env file
        $envPath = __DIR__ . '/../../../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    putenv(trim($key) . '=' . trim($value));
                }
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        $apiKey = getenv('ANTHROPIC_API_KEY');
        
        if (!$apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set in environment or .env file');
        }
        
        $this->client = new ClaudePhp(apiKey: $apiKey);
        $this->server = new MCPServer($this->client, [
            'transport' => 'stdio',
            'enable_visualization' => true,
        ]);
    }

    public function testServerInitialization(): void
    {
        $this->assertInstanceOf(MCPServer::class, $this->server);
        
        $tools = $this->server->getMCPTools();
        $this->assertCount(15, $tools);
        
        $this->assertArrayHasKey('search_agents', $tools);
        $this->assertArrayHasKey('run_agent', $tools);
        $this->assertArrayHasKey('visualize_workflow', $tools);
    }

    public function testInitializeProtocol(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'clientInfo' => [
                    'name' => 'integration-test',
                    'version' => '1.0.0',
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('claude-php-agent', $response['result']['serverInfo']['name']);
        $this->assertArrayHasKey('capabilities', $response['result']);
    }

    public function testListToolsEndpoint(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ];

        $response = $this->server->handleRequest($request);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
        $this->assertCount(15, $response['result']['tools']);

        // Verify tool structure
        $firstTool = $response['result']['tools'][0];
        $this->assertArrayHasKey('name', $firstTool);
        $this->assertArrayHasKey('description', $firstTool);
        $this->assertArrayHasKey('inputSchema', $firstTool);
    }

    public function testSearchAgentsTool(): void
    {
        $request = [
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

        $response = $this->server->handleRequest($request);

        $this->assertArrayHasKey('result', $response);
        $content = json_decode($response['result']['content'][0]['text'], true);
        
        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('data', $content);
        $this->assertGreaterThan(0, $content['data']['count']);
        $this->assertIsArray($content['data']['agents']);
    }

    public function testListAgentTypes(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_agent_types',
                'arguments' => [],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('types', $content['data']);
        $this->assertContains('react', $content['data']['types']);
        $this->assertContains('rag', $content['data']['types']);
        $this->assertContains('chain-of-thought', $content['data']['types']);
    }

    public function testGetAgentDetails(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_agent_details',
                'arguments' => [
                    'agent_name' => 'ReactAgent',
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        $this->assertEquals('ReactAgent', $content['data']['name']);
        $this->assertEquals('react', $content['data']['type']);
        $this->assertArrayHasKey('capabilities', $content['data']);
        $this->assertArrayHasKey('description', $content['data']);
    }

    public function testCountAgents(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'count_agents',
                'arguments' => [],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        $this->assertGreaterThan(0, $content['data']['count']);
        $this->assertEquals('all', $content['data']['type']);
    }

    public function testVisualizeWorkflow(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => [
                'name' => 'visualize_workflow',
                'arguments' => [
                    'agent_name' => 'ReactAgent',
                    'tools' => ['calculator', 'web_search'],
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('ascii_diagram', $content['data']);
        $this->assertArrayHasKey('graph', $content['data']);
        $this->assertArrayHasKey('text_representation', $content['data']);
        
        // Verify ASCII diagram contains expected elements
        $ascii = $content['data']['ascii_diagram'];
        $this->assertStringContainsString('ReactAgent', $ascii);
        $this->assertStringContainsString('calculator', $ascii);
        $this->assertStringContainsString('web_search', $ascii);
    }

    public function testGetAgentGraph(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_agent_graph',
                'arguments' => [
                    'agent_name' => 'RAGAgent',
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('vertices', $content['data']);
        $this->assertArrayHasKey('edges', $content['data']);
        $this->assertArrayHasKey('metadata', $content['data']);
        $this->assertContains('input', $content['data']['vertices']);
        $this->assertContains('agent', $content['data']['vertices']);
        $this->assertContains('output', $content['data']['vertices']);
    }

    public function testValidateAgentConfig(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'tools/call',
            'params' => [
                'name' => 'validate_agent_config',
                'arguments' => [
                    'agent_name' => 'ReactAgent',
                    'config' => [
                        'max_tokens' => 2048,
                        'temperature' => 0.7,
                    ],
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        $this->assertTrue($content['data']['valid']);
        $this->assertEmpty($content['data']['errors']);
    }

    public function testCreateAgentInstance(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_agent_instance',
                'arguments' => [
                    'agent_name' => 'DialogAgent',
                    'config' => [
                        'max_tokens' => 1024,
                    ],
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('session_id', $content['data']);
        $this->assertEquals('DialogAgent', $content['data']['agent_name']);
        $this->assertEquals('created', $content['data']['status']);
    }

    public function testSessionPersistence(): void
    {
        $sessionId = 'test-session-' . uniqid();

        // Create instance
        $createRequest = [
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_agent_instance',
                'arguments' => [
                    'agent_name' => 'ReactAgent',
                    'session_id' => $sessionId,
                ],
            ],
        ];

        $response1 = $this->server->handleRequest($createRequest);
        $content1 = json_decode($response1['result']['content'][0]['text'], true);
        $this->assertTrue($content1['success']);

        // Update config
        $updateRequest = [
            'jsonrpc' => '2.0',
            'id' => 12,
            'method' => 'tools/call',
            'params' => [
                'name' => 'update_agent_config',
                'arguments' => [
                    'session_id' => $sessionId,
                    'config' => [
                        'temperature' => 0.5,
                    ],
                ],
            ],
        ];

        $response2 = $this->server->handleRequest($updateRequest);
        $content2 = json_decode($response2['result']['content'][0]['text'], true);
        $this->assertTrue($content2['success']);
        $this->assertEquals($sessionId, $content2['data']['session_id']);
        $this->assertArrayHasKey('temperature', $content2['data']['config']);
    }

    public function testErrorHandling(): void
    {
        // Test with non-existent agent
        $request = [
            'jsonrpc' => '2.0',
            'id' => 13,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_agent_details',
                'arguments' => [
                    'agent_name' => 'NonExistentAgent',
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertFalse($content['success']);
        $this->assertArrayHasKey('error', $content);
        $this->assertStringContainsString('not found', $content['error']);
    }

    public function testInvalidToolCall(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 14,
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

    public function testInvalidMethod(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 15,
            'method' => 'invalid_method',
        ];

        $response = $this->server->handleRequest($request);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Unknown method', $response['error']['message']);
    }
}
