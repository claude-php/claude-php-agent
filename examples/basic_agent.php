#!/usr/bin/env php
<?php
/**
 * Basic Agent Example
 *
 * Demonstrates the simplest way to create an agent with tools.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Load environment
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? throw new RuntimeException('ANTHROPIC_API_KEY not set');
$client = new ClaudePhp(apiKey: $apiKey);

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                      Basic Agent Example                                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create a calculator tool
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations. Supports +, -, *, /, parentheses.')
    ->stringParam('expression', 'The mathematical expression to evaluate')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        
        // Validate expression (only allow safe characters)
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return "Error: Invalid expression characters";
        }
        
        try {
            $result = eval("return {$expr};");
            return (string) $result;
        } catch (Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    });

// Create the agent
$agent = Agent::create($client)
    ->withTool($calculator)
    ->withSystemPrompt('You are a helpful assistant that can perform calculations.')
    ->maxIterations(5);

// Run a simple task
echo "Task: What is (25 * 17) + (100 / 4)?\n\n";

$result = $agent->run('What is (25 * 17) + (100 / 4)?');

if ($result->isSuccess()) {
    echo "✅ Answer: {$result->getAnswer()}\n\n";
    echo "Completed in {$result->getIterations()} iterations\n";
    
    $usage = $result->getTokenUsage();
    echo "Tokens: {$usage['input']} input, {$usage['output']} output\n";
    
    // Show tool calls
    $toolCalls = $result->getToolCalls();
    if (!empty($toolCalls)) {
        echo "\nTool calls made:\n";
        foreach ($toolCalls as $call) {
            echo "  - {$call['tool']}: {$call['result']}\n";
        }
    }
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n";

// ============================================================================
// Advanced: Using Design Patterns (Factory & Builder)
// ============================================================================

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "💡 Advanced: Production-Ready Patterns\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "The above example shows the simple way. For production code, consider\n";
echo "using design patterns for better maintainability:\n\n";

echo "1️⃣  Factory Pattern - Consistent agent creation:\n\n";
echo "```php\n";
echo "use ClaudeAgents\\Factory\\AgentFactory;\n";
echo "use Monolog\\Logger;\n\n";
echo "\$logger = new Logger('agents');\n";
echo "\$factory = new AgentFactory(\$client, \$logger);\n\n";
echo "// All agents get consistent configuration and logger\n";
echo "\$agent = \$factory->create('react', [\n";
echo "    'name' => 'calculator_agent',\n";
echo "    'max_iterations' => 5,\n";
echo "]);\n";
echo "\$agent->withTool(\$calculator);\n";
echo "```\n\n";

echo "Benefits:\n";
echo "  ✓ Consistent configuration across all agents\n";
echo "  ✓ Automatic logger injection\n";
echo "  ✓ Easy to test (mock the factory)\n";
echo "  ✓ Single place to change defaults\n\n";

echo "2️⃣  Builder Pattern - Type-safe configuration:\n\n";
echo "```php\n";
echo "use ClaudeAgents\\Config\\AgentConfigBuilder;\n\n";
echo "\$config = AgentConfigBuilder::create()\n";
echo "    ->withModel('claude-sonnet-4-20250514')\n";
echo "    ->withMaxTokens(2048)\n";
echo "    ->withMaxIterations(5)\n";
echo "    ->withSystemPrompt('You are a helpful assistant')\n";
echo "    ->addTool(\$calculator)\n";
echo "    ->build();\n\n";
echo "\$agent = \$factory->create('react', \$config);\n";
echo "```\n\n";

echo "Benefits:\n";
echo "  ✓ Type-safe (typos caught at compile time)\n";
echo "  ✓ IDE autocomplete support\n";
echo "  ✓ Self-documenting code\n";
echo "  ✓ Easy to add optional parameters\n\n";

echo "3️⃣  Combined approach:\n\n";
echo "```php\n";
echo "use ClaudeAgents\\Factory\\AgentFactory;\n";
echo "use ClaudeAgents\\Config\\AgentConfigBuilder;\n\n";
echo "\$factory = new AgentFactory(\$client, \$logger);\n\n";
echo "\$config = AgentConfigBuilder::create()\n";
echo "    ->withMaxTokens(2048)\n";
echo "    ->withMaxIterations(5)\n";
echo "    ->addTool(\$calculator)\n";
echo "    ->toArray();\n\n";
echo "\$agent = \$factory->create('react', \$config);\n";
echo "```\n\n";

echo "📚 Learn more:\n";
echo "  • Design Patterns Guide: docs/DesignPatterns.md\n";
echo "  • Comprehensive Demo: examples/design_patterns_demo.php\n";
echo "  • Factory Example: examples/factory_pattern_example.php\n";
echo "  • Builder Example: examples/builder_pattern_example.php\n\n";

echo str_repeat("═", 80) . "\n";

