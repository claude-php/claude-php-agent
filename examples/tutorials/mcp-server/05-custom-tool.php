<?php

/**
 * MCP Tutorial 5: Custom MCP Tool
 * 
 * Run: php examples/tutorials/mcp-server/05-custom-tool.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

echo "=== MCP Tutorial 5: Custom MCP Tool ===\n\n";

echo "Example custom MCP tool structure:\n\n";

$exampleCode = <<<'PHP'
use ClaudeAgents\MCP\Tools\MCPTool;

class CustomAnalyticsTool extends MCPTool
{
    protected string $name = 'get_analytics';
    protected string $description = 'Get agent analytics';
    
    public function execute(array $params): array
    {
        return [
            'total_runs' => 150,
            'success_rate' => 95.5,
            'avg_duration_ms' => 1250.5,
        ];
    }
    
    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['hour', 'day', 'week'],
                ],
            ],
        ];
    }
}
PHP;

echo $exampleCode . "\n\n";

echo "Register in server:\n";
echo "  \$server->registerTool(new CustomAnalyticsTool());\n\n";

echo "✓ See src/MCP/Tools/ for more examples\n";
