<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\MCP;

use ClaudeAgents\MCP\MCPServer;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end feature tests demonstrating complete MCP workflows.
 * 
 * @group integration
 * @group mcp
 * @group e2e
 * @group slow
 */
class EndToEndFeatureTest extends TestCase
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
            'max_execution_time' => 120,
        ]);
    }

    /**
     * Feature: Agent Discovery and Execution
     * 
     * Scenario: User wants to find and run an appropriate agent
     */
    public function testCompleteAgentDiscoveryAndExecutionWorkflow(): void
    {
        // Step 1: User searches for agents with reasoning capability
        $searchResponse = $this->callTool('search_agents', [
            'capabilities' => ['reasoning'],
        ]);
        
        $this->assertTrue($searchResponse['success']);
        $this->assertGreaterThan(0, $searchResponse['data']['count']);
        
        $agentName = $searchResponse['data']['agents'][0]['name'];

        // Step 2: User gets detailed information about the agent
        $detailsResponse = $this->callTool('get_agent_details', [
            'agent_name' => $agentName,
        ]);
        
        $this->assertTrue($detailsResponse['success']);
        $this->assertEquals($agentName, $detailsResponse['data']['name']);

        // Step 3: User validates configuration
        $validateResponse = $this->callTool('validate_agent_config', [
            'agent_name' => $agentName,
            'config' => [
                'max_tokens' => 2048,
            ],
        ]);
        
        $this->assertTrue($validateResponse['success']);
        $this->assertTrue($validateResponse['data']['valid']);

        // Step 4: User creates an agent instance
        $createResponse = $this->callTool('create_agent_instance', [
            'agent_name' => $agentName,
            'config' => [
                'max_tokens' => 2048,
            ],
        ]);
        
        $this->assertTrue($createResponse['success']);
        $sessionId = $createResponse['data']['session_id'];

        // Step 5: User runs the agent
        $runResponse = $this->callTool('run_agent', [
            'agent_name' => $agentName,
            'input' => 'What is 25 + 17?',
            'session_id' => $sessionId,
        ]);
        
        $this->assertTrue($runResponse['success']);
        $this->assertStringContainsString('42', $runResponse['data']['output']);

        // Step 6: User checks execution status
        $statusResponse = $this->callTool('get_execution_status', [
            'session_id' => $sessionId,
        ]);
        
        $this->assertTrue($statusResponse['success']);
        $this->assertEquals('completed', $statusResponse['data']['status']);
    }

    /**
     * Feature: Workflow Visualization
     * 
     * Scenario: User wants to understand agent workflows visually
     */
    public function testCompleteVisualizationWorkflow(): void
    {
        // Step 1: List all agent types
        $typesResponse = $this->callTool('list_agent_types', []);
        $this->assertTrue($typesResponse['success']);
        
        $firstType = $typesResponse['data']['types'][0];

        // Step 2: Find agents of that type
        $searchResponse = $this->callTool('search_agents', [
            'type' => $firstType,
        ]);
        
        $this->assertTrue($searchResponse['success']);
        $agentName = $searchResponse['data']['agents'][0]['name'];

        // Step 3: Get graph representation
        $graphResponse = $this->callTool('get_agent_graph', [
            'agent_name' => $agentName,
            'tools' => ['calculator'],
        ]);
        
        $this->assertTrue($graphResponse['success']);
        $this->assertArrayHasKey('vertices', $graphResponse['data']);

        // Step 4: Get complete visualization
        $vizResponse = $this->callTool('visualize_workflow', [
            'agent_name' => $agentName,
            'tools' => ['calculator'],
        ]);
        
        $this->assertTrue($vizResponse['success']);
        $this->assertArrayHasKey('ascii_diagram', $vizResponse['data']);

        // Step 5: Export configuration
        $exportResponse = $this->callTool('export_agent_config', [
            'agent_name' => $agentName,
        ]);
        
        $this->assertTrue($exportResponse['success']);
        $this->assertArrayHasKey('config', $exportResponse['data']);
    }

    /**
     * Feature: Multi-Agent Comparison
     * 
     * Scenario: User wants to compare different agents for a task
     */
    public function testMultiAgentComparisonWorkflow(): void
    {
        $agentsToCompare = ['ReactAgent', 'ChainOfThoughtAgent', 'PlanExecuteAgent'];
        $task = 'Explain how to make tea';
        $results = [];

        foreach ($agentsToCompare as $agentName) {
            // Get agent details
            $detailsResponse = $this->callTool('get_agent_details', [
                'agent_name' => $agentName,
            ]);
            
            $this->assertTrue($detailsResponse['success']);
            $results[$agentName]['details'] = $detailsResponse['data'];

            // Visualize workflow
            $vizResponse = $this->callTool('visualize_workflow', [
                'agent_name' => $agentName,
            ]);
            
            $this->assertTrue($vizResponse['success']);
            $results[$agentName]['visualization'] = $vizResponse['data'];

            // Run the agent
            $runResponse = $this->callTool('run_agent', [
                'agent_name' => $agentName,
                'input' => $task,
                'options' => ['max_tokens' => 1024],
            ]);
            
            $this->assertTrue($runResponse['success']);
            $results[$agentName]['execution'] = $runResponse['data'];
        }

        // Verify all agents completed
        $this->assertCount(3, $results);
        
        foreach ($results as $agentName => $data) {
            $this->assertArrayHasKey('details', $data);
            $this->assertArrayHasKey('visualization', $data);
            $this->assertArrayHasKey('execution', $data);
            $this->assertArrayHasKey('output', $data['execution']);
        }
    }

    /**
     * Feature: Configuration Management
     * 
     * Scenario: User manages agent configurations across sessions
     */
    public function testConfigurationManagementWorkflow(): void
    {
        $sessionId = 'config-test-' . uniqid();

        // Step 1: Create instance with config
        $createResponse = $this->callTool('create_agent_instance', [
            'agent_name' => 'ReactAgent',
            'config' => [
                'max_tokens' => 1024,
            ],
            'session_id' => $sessionId,
        ]);
        
        $this->assertTrue($createResponse['success']);

        // Step 2: Update configuration
        $updateResponse = $this->callTool('update_agent_config', [
            'session_id' => $sessionId,
            'config' => [
                'max_tokens' => 2048,
                'temperature' => 0.7,
            ],
        ]);
        
        $this->assertTrue($updateResponse['success']);
        $this->assertEquals(2048, $updateResponse['data']['config']['max_tokens']);

        // Step 3: Validate updated configuration
        $validateResponse = $this->callTool('validate_agent_config', [
            'agent_name' => 'ReactAgent',
            'config' => $updateResponse['data']['config'],
        ]);
        
        $this->assertTrue($validateResponse['success']);
        $this->assertTrue($validateResponse['data']['valid']);

        // Step 4: Run with updated config
        $runResponse = $this->callTool('run_agent', [
            'agent_name' => 'ReactAgent',
            'input' => 'Test with updated config',
            'session_id' => $sessionId,
        ]);
        
        $this->assertTrue($runResponse['success']);
    }

    /**
     * Feature: Tool Discovery and Usage
     * 
     * Scenario: User discovers and learns about available tools
     */
    public function testToolDiscoveryWorkflow(): void
    {
        // Step 1: List all available tools
        $listResponse = $this->callTool('list_tools', []);
        $this->assertTrue($listResponse['success']);
        $this->assertGreaterThan(0, $listResponse['data']['count']);

        // Step 2: Search for specific tools
        $searchResponse = $this->callTool('search_tools', [
            'query' => 'agent',
        ]);
        
        $this->assertTrue($searchResponse['success']);
        $this->assertGreaterThan(0, $searchResponse['data']['count']);

        // Step 3: Get details for each found tool
        foreach ($searchResponse['data']['tools'] as $tool) {
            $detailsResponse = $this->callTool('get_tool_details', [
                'tool_name' => $tool['name'],
            ]);
            
            $this->assertTrue($detailsResponse['success']);
            $this->assertArrayHasKey('schema', $detailsResponse['data']);
        }
    }

    /**
     * Helper method to call MCP tools
     */
    private function callTool(string $toolName, array $arguments): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => 'tools/call',
            'params' => [
                'name' => $toolName,
                'arguments' => $arguments,
            ],
        ];

        $response = $this->server->handleRequest($request);
        return json_decode($response['result']['content'][0]['text'], true);
    }

    /**
     * Feature: Error Recovery
     * 
     * Scenario: User handles errors gracefully
     */
    public function testErrorRecoveryWorkflow(): void
    {
        // Step 1: Try invalid agent
        $invalidResponse = $this->callTool('get_agent_details', [
            'agent_name' => 'InvalidAgent',
        ]);
        
        $this->assertFalse($invalidResponse['success']);
        $this->assertArrayHasKey('error', $invalidResponse);

        // Step 2: Recover by searching for valid agents
        $searchResponse = $this->callTool('search_agents', [
            'query' => 'react',
        ]);
        
        $this->assertTrue($searchResponse['success']);
        $validAgentName = $searchResponse['data']['agents'][0]['name'];

        // Step 3: Use valid agent
        $validResponse = $this->callTool('get_agent_details', [
            'agent_name' => $validAgentName,
        ]);
        
        $this->assertTrue($validResponse['success']);
    }
}
