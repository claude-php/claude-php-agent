<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\MCP;

use ClaudeAgents\MCP\MCPServer;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for agent execution through MCP with real API calls.
 * 
 * @group integration
 * @group mcp
 * @group slow
 */
class AgentExecutionIntegrationTest extends TestCase
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
            'max_execution_time' => 60,
        ]);
    }

    public function testRunSimpleAgent(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'run_agent',
                'arguments' => [
                    'agent_name' => 'ReactAgent',
                    'input' => 'What is 15 + 27?',
                    'options' => [
                        'max_tokens' => 1024,
                    ],
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('output', $content['data']);
        $this->assertArrayHasKey('execution_time', $content['data']);
        $this->assertArrayHasKey('session_id', $content['data']);
        
        // Verify the answer contains the result
        $output = $content['data']['output'];
        $this->assertStringContainsString('42', $output);
    }

    public function testRunChainOfThoughtAgent(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'run_agent',
                'arguments' => [
                    'agent_name' => 'ChainOfThoughtAgent',
                    'input' => 'Explain step by step: What is 12 * 8?',
                    'options' => [
                        'max_tokens' => 1024,
                    ],
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('output', $content['data']);
        
        $output = $content['data']['output'];
        // Chain of thought should contain reasoning
        $this->assertGreaterThan(50, strlen($output));
        $this->assertStringContainsString('96', $output);
    }

    public function testExecutionStatusTracking(): void
    {
        // Run an agent
        $runRequest = [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'run_agent',
                'arguments' => [
                    'agent_name' => 'ReactAgent',
                    'input' => 'Hello, world!',
                ],
            ],
        ];

        $runResponse = $this->server->handleRequest($runRequest);
        $runContent = json_decode($runResponse['result']['content'][0]['text'], true);
        
        $this->assertTrue($runContent['success']);
        $sessionId = $runContent['data']['session_id'];

        // Check execution status
        $statusRequest = [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_execution_status',
                'arguments' => [
                    'session_id' => $sessionId,
                ],
            ],
        ];

        $statusResponse = $this->server->handleRequest($statusRequest);
        $statusContent = json_decode($statusResponse['result']['content'][0]['text'], true);

        $this->assertTrue($statusContent['success']);
        $this->assertEquals($sessionId, $statusContent['data']['session_id']);
        $this->assertEquals('completed', $statusContent['data']['status']);
        $this->assertArrayHasKey('execution', $statusContent['data']);
    }

    public function testMultipleAgentExecutions(): void
    {
        $agents = ['ReactAgent', 'ChainOfThoughtAgent', 'DialogAgent'];
        $results = [];

        foreach ($agents as $agentName) {
            $request = [
                'jsonrpc' => '2.0',
                'id' => uniqid(),
                'method' => 'tools/call',
                'params' => [
                    'name' => 'run_agent',
                    'arguments' => [
                        'agent_name' => $agentName,
                        'input' => 'Say hello!',
                        'options' => [
                            'max_tokens' => 512,
                        ],
                    ],
                ],
            ];

            $response = $this->server->handleRequest($request);
            $content = json_decode($response['result']['content'][0]['text'], true);
            
            $this->assertTrue($content['success'], "Failed for {$agentName}");
            $results[$agentName] = $content['data'];
        }

        // Verify all executed successfully
        $this->assertCount(3, $results);
        foreach ($results as $agentName => $result) {
            $this->assertArrayHasKey('output', $result);
            $this->assertArrayHasKey('execution_time', $result);
        }
    }

    public function testAgentWithCustomOptions(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'run_agent',
                'arguments' => [
                    'agent_name' => 'ReactAgent',
                    'input' => 'Respond briefly: What is PHP?',
                    'options' => [
                        'max_tokens' => 256,
                        'name' => 'brief_responder',
                    ],
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('output', $content['data']);
        
        // Brief response should be shorter
        $output = $content['data']['output'];
        $this->assertLessThan(500, strlen($output));
    }

    public function testInvalidAgentExecution(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'run_agent',
                'arguments' => [
                    'agent_name' => 'NonExistentAgent',
                    'input' => 'Test',
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertFalse($content['success']);
        $this->assertArrayHasKey('error', $content);
    }

    public function testMissingRequiredParameters(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => [
                'name' => 'run_agent',
                'arguments' => [
                    'agent_name' => 'ReactAgent',
                    // Missing 'input' parameter
                ],
            ],
        ];

        $response = $this->server->handleRequest($request);
        $content = json_decode($response['result']['content'][0]['text'], true);

        $this->assertFalse($content['success']);
        $this->assertStringContainsString('Missing required parameter', $content['error']);
    }
}
