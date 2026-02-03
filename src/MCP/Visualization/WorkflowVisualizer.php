<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP\Visualization;

use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Tools\ToolRegistry;

/**
 * Generates visual representations of agent workflows.
 *
 * Creates ASCII art diagrams, JSON graph structures, and text descriptions.
 */
class WorkflowVisualizer
{
    /**
     * Generate ASCII art diagram for an agent workflow.
     *
     * @param array<string, mixed> $agentMetadata
     * @param array<string> $tools
     */
    public function generateAsciiDiagram(array $agentMetadata, array $tools = []): string
    {
        $name = $agentMetadata['name'] ?? 'Agent';
        $type = $agentMetadata['type'] ?? 'unknown';
        $description = $agentMetadata['description'] ?? '';
        
        $output = "";
        $output .= "Agent Workflow: {$name}\n";
        $output .= str_repeat("=", strlen("Agent Workflow: {$name}")) . "\n\n";
        
        if ($description) {
            $output .= wordwrap($description, 60) . "\n\n";
        }
        
        // Draw the workflow
        $output .= "┌─────────────────┐\n";
        $output .= "│   User Input    │\n";
        $output .= "└────────┬────────┘\n";
        $output .= "         │\n";
        $output .= "         ▼\n";
        
        // Add tools if present
        if (!empty($tools)) {
            foreach ($tools as $tool) {
                $output .= "┌─────────────────┐\n";
                $output .= "│ " . str_pad($tool, 15, ' ', STR_PAD_BOTH) . " │ (Tool)\n";
                $output .= "└────────┬────────┘\n";
                $output .= "         │\n";
                $output .= "         ▼\n";
            }
        }
        
        // Agent processing
        $output .= "┌─────────────────┐\n";
        $output .= "│ " . str_pad($name, 15, ' ', STR_PAD_BOTH) . " │ ({$type})\n";
        $output .= "└────────┬────────┘\n";
        $output .= "         │\n";
        
        // Add reasoning steps for certain agent types
        if (in_array($type, ['react', 'chain-of-thought', 'tree-of-thoughts', 'plan-execute'], true)) {
            $output .= "         ▼\n";
            $output .= "┌─────────────────┐\n";
            $output .= "│    Reasoning    │\n";
            $output .= "└────────┬────────┘\n";
            $output .= "         │\n";
        }
        
        // Add memory for dialog agents
        if (in_array($type, ['dialog', 'autonomous', 'learning'], true)) {
            $output .= "         ▼\n";
            $output .= "┌─────────────────┐\n";
            $output .= "│     Memory      │\n";
            $output .= "└────────┬────────┘\n";
            $output .= "         │\n";
        }
        
        $output .= "         ▼\n";
        $output .= "┌─────────────────┐\n";
        $output .= "│     Output      │\n";
        $output .= "└─────────────────┘\n\n";
        
        // Add metadata
        $output .= "Metadata:\n";
        $output .= "─────────\n";
        $output .= "Type: {$type}\n";
        $output .= "Tools: " . (empty($tools) ? "None" : count($tools) . " (" . implode(", ", $tools) . ")") . "\n";
        
        if (isset($agentMetadata['capabilities'])) {
            $output .= "Capabilities: " . implode(", ", $agentMetadata['capabilities']) . "\n";
        }
        
        return $output;
    }

    /**
     * Generate JSON graph representation.
     *
     * @param array<string, mixed> $agentMetadata
     * @param array<string> $tools
     * @return array<string, mixed>
     */
    public function generateGraphRepresentation(array $agentMetadata, array $tools = []): array
    {
        $vertices = ['input'];
        $edges = [];
        
        // Add tool vertices
        foreach ($tools as $i => $tool) {
            $toolVertex = "tool_{$i}";
            $vertices[] = $toolVertex;
            
            if ($i === 0) {
                $edges[] = ['from' => 'input', 'to' => $toolVertex];
            } else {
                $edges[] = ['from' => "tool_" . ($i - 1), 'to' => $toolVertex];
            }
        }
        
        // Add agent vertex
        $vertices[] = 'agent';
        if (!empty($tools)) {
            $edges[] = ['from' => 'tool_' . (count($tools) - 1), 'to' => 'agent'];
        } else {
            $edges[] = ['from' => 'input', 'to' => 'agent'];
        }
        
        // Add output vertex
        $vertices[] = 'output';
        $edges[] = ['from' => 'agent', 'to' => 'output'];
        
        return [
            'vertices' => $vertices,
            'edges' => $edges,
            'metadata' => [
                'agent_name' => $agentMetadata['name'] ?? 'Unknown',
                'agent_type' => $agentMetadata['type'] ?? 'unknown',
                'vertex_count' => count($vertices),
                'edge_count' => count($edges),
            ],
        ];
    }

    /**
     * Generate text representation of workflow.
     *
     * @param array<string, mixed> $agentMetadata
     * @param array<string> $tools
     */
    public function generateTextRepresentation(array $agentMetadata, array $tools = []): string
    {
        $name = $agentMetadata['name'] ?? 'Agent';
        $type = $agentMetadata['type'] ?? 'unknown';
        $description = $agentMetadata['description'] ?? '';
        
        $output = "Graph Representation\n";
        $output .= "────────────────────\n\n";
        
        $output .= "Agent: {$name} ({$type})\n";
        if ($description) {
            $output .= "Description: {$description}\n";
        }
        $output .= "\n";
        
        $output .= "Vertices (" . (2 + count($tools)) . "):\n";
        $output .= "  - input\n";
        foreach ($tools as $tool) {
            $output .= "  - {$tool} (tool)\n";
        }
        $output .= "  - {$name} (agent)\n";
        $output .= "  - output\n\n";
        
        $output .= "Edges (" . (1 + count($tools)) . "):\n";
        if (empty($tools)) {
            $output .= "  input → {$name} → output\n";
        } else {
            $output .= "  input";
            foreach ($tools as $tool) {
                $output .= " → {$tool}";
            }
            $output .= " → {$name} → output\n";
        }
        
        return $output;
    }

    /**
     * Generate complete visualization with all formats.
     *
     * @param array<string, mixed> $agentMetadata
     * @param array<string> $tools
     * @return array<string, mixed>
     */
    public function generateComplete(array $agentMetadata, array $tools = []): array
    {
        return [
            'ascii_diagram' => $this->generateAsciiDiagram($agentMetadata, $tools),
            'graph' => $this->generateGraphRepresentation($agentMetadata, $tools),
            'text_representation' => $this->generateTextRepresentation($agentMetadata, $tools),
            'agent_info' => [
                'name' => $agentMetadata['name'] ?? 'Unknown',
                'type' => $agentMetadata['type'] ?? 'unknown',
                'description' => $agentMetadata['description'] ?? '',
                'capabilities' => $agentMetadata['capabilities'] ?? [],
            ],
        ];
    }

    /**
     * Generate workflow summary.
     *
     * @param array<string, mixed> $agentMetadata
     * @param array<string> $tools
     * @return array<string, mixed>
     */
    public function generateSummary(array $agentMetadata, array $tools = []): array
    {
        return [
            'agent_name' => $agentMetadata['name'] ?? 'Unknown',
            'agent_type' => $agentMetadata['type'] ?? 'unknown',
            'description' => $agentMetadata['description'] ?? '',
            'tool_count' => count($tools),
            'tools' => $tools,
            'capabilities' => $agentMetadata['capabilities'] ?? [],
            'component_count' => 2 + count($tools), // input + tools + agent + output
        ];
    }
}
