<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\MCP\Visualization;

use ClaudeAgents\MCP\Visualization\WorkflowVisualizer;
use PHPUnit\Framework\TestCase;

class WorkflowVisualizerTest extends TestCase
{
    private WorkflowVisualizer $visualizer;
    private array $agentMetadata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visualizer = new WorkflowVisualizer();
        $this->agentMetadata = [
            'name' => 'TestAgent',
            'type' => 'react',
            'description' => 'Test agent for visualization',
            'capabilities' => ['reasoning', 'tool-use'],
        ];
    }

    public function testGenerateAsciiDiagram(): void
    {
        $diagram = $this->visualizer->generateAsciiDiagram($this->agentMetadata);
        
        $this->assertIsString($diagram);
        $this->assertStringContainsString('TestAgent', $diagram);
        $this->assertStringContainsString('User Input', $diagram);
        $this->assertStringContainsString('Output', $diagram);
    }

    public function testGenerateAsciiDiagramWithTools(): void
    {
        $tools = ['calculator', 'web_search'];
        $diagram = $this->visualizer->generateAsciiDiagram($this->agentMetadata, $tools);
        
        $this->assertStringContainsString('calculator', $diagram);
        $this->assertStringContainsString('web_search', $diagram);
    }

    public function testGenerateGraphRepresentation(): void
    {
        $graph = $this->visualizer->generateGraphRepresentation($this->agentMetadata);
        
        $this->assertIsArray($graph);
        $this->assertArrayHasKey('vertices', $graph);
        $this->assertArrayHasKey('edges', $graph);
        $this->assertArrayHasKey('metadata', $graph);
        
        $this->assertContains('input', $graph['vertices']);
        $this->assertContains('agent', $graph['vertices']);
        $this->assertContains('output', $graph['vertices']);
    }

    public function testGenerateGraphRepresentationWithTools(): void
    {
        $tools = ['tool1', 'tool2'];
        $graph = $this->visualizer->generateGraphRepresentation($this->agentMetadata, $tools);
        
        $this->assertGreaterThan(3, count($graph['vertices']));
        $this->assertEquals('TestAgent', $graph['metadata']['agent_name']);
    }

    public function testGenerateTextRepresentation(): void
    {
        $text = $this->visualizer->generateTextRepresentation($this->agentMetadata);
        
        $this->assertIsString($text);
        $this->assertStringContainsString('TestAgent', $text);
        $this->assertStringContainsString('Vertices', $text);
        $this->assertStringContainsString('Edges', $text);
    }

    public function testGenerateComplete(): void
    {
        $result = $this->visualizer->generateComplete($this->agentMetadata);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('ascii_diagram', $result);
        $this->assertArrayHasKey('graph', $result);
        $this->assertArrayHasKey('text_representation', $result);
        $this->assertArrayHasKey('agent_info', $result);
    }

    public function testGenerateSummary(): void
    {
        $summary = $this->visualizer->generateSummary($this->agentMetadata);
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('agent_name', $summary);
        $this->assertArrayHasKey('agent_type', $summary);
        $this->assertArrayHasKey('tool_count', $summary);
        $this->assertArrayHasKey('capabilities', $summary);
        
        $this->assertEquals('TestAgent', $summary['agent_name']);
        $this->assertEquals('react', $summary['agent_type']);
    }

    public function testGenerateSummaryWithTools(): void
    {
        $tools = ['tool1', 'tool2', 'tool3'];
        $summary = $this->visualizer->generateSummary($this->agentMetadata, $tools);
        
        $this->assertEquals(3, $summary['tool_count']);
        $this->assertEquals($tools, $summary['tools']);
    }
}
