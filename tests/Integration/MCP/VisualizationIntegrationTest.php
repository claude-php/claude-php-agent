<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\MCP;

use ClaudeAgents\MCP\MCPServer;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for workflow visualization features.
 * 
 * @group integration
 * @group mcp
 * @group visualization
 */
class VisualizationIntegrationTest extends TestCase
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

    public function testCompleteWorkflowVisualization(): void
    {
        $agents = ['ReactAgent', 'RAGAgent', 'ChainOfThoughtAgent', 'PlanExecuteAgent'];

        foreach ($agents as $agentName) {
            $request = [
                'jsonrpc' => '2.0',
                'id' => uniqid(),
                'method' => 'tools/call',
                'params' => [
                    'name' => 'visualize_workflow',
                    'arguments' => [
                        'agent_name' => $agentName,
                        'tools' => [],
                    ],
                ],
            ];

            $response = $this->server->handleRequest($request);
            $content = json_decode($response['result']['content'][0]['text'], true);

            $this->assertTrue($content['success'], "Visualization failed for {$agentName}");
            
            // Verify all required components
            $data = $content['data'];
            $this->assertArrayHasKey('ascii_diagram', $data);
            $this->assertArrayHasKey('graph', $data);
            $this->assertArrayHasKey('text_representation', $data);
            $this->assertArrayHasKey('agent_info', $data);

            // Verify ASCII diagram contains agent name
            $this->assertStringContainsString($agentName, $data['ascii_diagram']);

            // Verify graph structure
            $graph = $data['graph'];
            $this->assertArrayHasKey('vertices', $graph);
            $this->assertArrayHasKey('edges', $graph);
            $this->assertContains('input', $graph['vertices']);
            $this->assertContains('agent', $graph['vertices']);
            $this->assertContains('output', $graph['vertices']);

            // Verify agent info
            $this->assertEquals($agentName, $data['agent_info']['name']);
        }
    }

    public function testVisualizationWithTools(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'visualize_workflow',
                'arguments' => [
                    'agent_name' => 'ReactAgent',
                    'tools' => ['calculator', 'web_search', 'file_reader'],
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        
        $ascii = $content['data']['ascii_diagram'];
        $graph = $content['data']['graph'];

        // Verify tools appear in visualization
        $this->assertStringContainsString('calculator', $ascii);
        $this->assertStringContainsString('web_search', $ascii);
        $this->assertStringContainsString('file_reader', $ascii);

        // Verify graph includes tool vertices
        $this->assertGreaterThan(3, count($graph['vertices']));
        $this->assertGreaterThan(2, count($graph['edges']));
    }

    public function testGraphRepresentation(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_agent_graph',
                'arguments' => [
                    'agent_name' => 'HierarchicalAgent',
                    'tools' => ['tool1', 'tool2'],
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        
        $graph = $content['data'];
        
        // Verify structure
        $this->assertIsArray($graph['vertices']);
        $this->assertIsArray($graph['edges']);
        $this->assertArrayHasKey('metadata', $graph);

        // Verify metadata
        $metadata = $graph['metadata'];
        $this->assertEquals('HierarchicalAgent', $metadata['agent_name']);
        $this->assertEquals('hierarchical', $metadata['agent_type']);
        $this->assertGreaterThan(0, $metadata['vertex_count']);
        $this->assertGreaterThan(0, $metadata['edge_count']);
    }

    public function testExportAgentConfiguration(): void
    {
        $agents = ['ReactAgent', 'DialogAgent', 'AutonomousAgent'];

        foreach ($agents as $agentName) {
            $request = [
                'jsonrpc' => '2.0',
                'id' => uniqid(),
                'method' => 'tools/call',
                'params' => [
                    'name' => 'export_agent_config',
                    'arguments' => [
                        'agent_name' => $agentName,
                    ],
                ],
            ];

            $response = $this->server->handleRequest($request);
            $content = json_decode($response['result']['content'][0]['text'], true);

            $this->assertTrue($content['success'], "Export failed for {$agentName}");
            
            $data = $content['data'];
            $this->assertArrayHasKey('config', $data);
            $this->assertArrayHasKey('summary', $data);
            $this->assertArrayHasKey('export_timestamp', $data);

            // Verify config contains agent metadata
            $config = $data['config'];
            $this->assertEquals($agentName, $config['name']);
            $this->assertArrayHasKey('type', $config);
            $this->assertArrayHasKey('capabilities', $config);

            // Verify summary
            $summary = $data['summary'];
            $this->assertEquals($agentName, $summary['agent_name']);
            $this->assertArrayHasKey('tool_count', $summary);
            $this->assertArrayHasKey('capabilities', $summary);
        }
    }

    public function testASCIIDiagramQuality(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'visualize_workflow',
                'arguments' => [
                    'agent_name' => 'TreeOfThoughtsAgent',
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        
        $ascii = $content['data']['ascii_diagram'];

        // Verify ASCII diagram contains expected elements
        $this->assertStringContainsString('Agent Workflow:', $ascii);
        $this->assertStringContainsString('User Input', $ascii);
        $this->assertStringContainsString('Output', $ascii);
        $this->assertStringContainsString('TreeOfThoughtsAgent', $ascii);

        // Verify box drawing characters
        $this->assertStringContainsString('┌', $ascii);
        $this->assertStringContainsString('└', $ascii);
        $this->assertStringContainsString('│', $ascii);
        $this->assertStringContainsString('▼', $ascii);

        // Verify metadata section
        $this->assertStringContainsString('Metadata:', $ascii);
        $this->assertStringContainsString('Type:', $ascii);
        $this->assertStringContainsString('Capabilities:', $ascii);
    }

    public function testVisualizationForAllAgentTypes(): void
    {
        // Get all agent types
        $typesRequest = [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_agent_types',
                'arguments' => [],
            ],
        ];

        $typesResponse = $this->server->handleRequest($typesRequest);
        $typesContent = json_decode($typesResponse['result']['content'][0]['text'], true);
        $types = $typesContent['data']['types'];

        // Visualize one agent of each type
        foreach ($types as $type) {
            // Search for an agent of this type
            $searchRequest = [
                'jsonrpc' => '2.0',
                'id' => uniqid(),
                'method' => 'tools/call',
                'params' => [
                    'name' => 'search_agents',
                    'arguments' => [
                        'type' => $type,
                    ],
                ],
            ];

            $searchResponse = $this->server->handleRequest($searchRequest);
            $searchContent = json_decode($searchResponse['result']['content'][0]['text'], true);
            
            if (empty($searchContent['data']['agents'])) {
                continue;
            }

            $agentName = $searchContent['data']['agents'][0]['name'];

            // Visualize it
            $vizRequest = [
                'jsonrpc' => '2.0',
                'id' => uniqid(),
                'method' => 'tools/call',
                'params' => [
                    'name' => 'visualize_workflow',
                    'arguments' => [
                        'agent_name' => $agentName,
                    ],
                ],
            ];

            $vizResponse = $this->server->handleRequest($vizRequest);
            $vizContent = json_decode($vizResponse['result']['content'][0]['text'], true);

            $this->assertTrue($vizContent['success'], "Visualization failed for {$agentName} ({$type})");
        }
    }
}
