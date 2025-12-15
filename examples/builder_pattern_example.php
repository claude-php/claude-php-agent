#!/usr/bin/env php
<?php
/**
 * Builder Pattern Example
 *
 * Demonstrates the Builder Pattern for type-safe configuration with:
 * - Fluent API for readability
 * - Type safety (compile-time checks)
 * - IDE autocomplete support
 * - Self-documenting code
 *
 * Run: php examples/builder_pattern_example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Config\AgentConfigBuilder;
use ClaudeAgents\Factory\AgentFactory;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

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
echo "║                        Builder Pattern Example                             ║\n";
echo "║              Type-Safe Configuration with Fluent API                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Example 1: Array Configuration (Without Builder)
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "❌ Without Builder: Array Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Traditional array configuration:\n\n";
echo "```php\n";
echo "\$config = [\n";
echo "    'model' => 'claude-opus-4',\n";
echo "    'max_tokens' => 4096,\n";
echo "    'max_iterations' => 10,\n";
echo "    'system' => 'You are helpful',\n";
echo "    'thinking' => ['type' => 'enabled', 'budget_tokens' => 10000],\n";
echo "    'temperature' => 0.7,\n";
echo "];\n";
echo "```\n\n";

echo "Problems:\n";
echo "  • No type safety - typos not caught (e.g., 'max_token' vs 'max_tokens')\n";
echo "  • No IDE autocomplete\n";
echo "  • Easy to forget required fields\n";
echo "  • No validation until runtime\n";
echo "  • Not self-documenting\n\n";

// ============================================================================
// Example 2: Basic Builder Usage
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Example 1: Basic Builder Usage\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$basicConfig = AgentConfigBuilder::create()
    ->withModel('claude-sonnet-4-20250514')
    ->withMaxTokens(2048)
    ->withMaxIterations(5)
    ->build();

echo "✅ Built basic configuration:\n\n";
echo "```php\n";
echo "\$config = AgentConfigBuilder::create()\n";
echo "    ->withModel('claude-sonnet-4-20250514')\n";
echo "    ->withMaxTokens(2048)\n";
echo "    ->withMaxIterations(5)\n";
echo "    ->build();\n";
echo "```\n\n";

echo "Benefits:\n";
echo "  ✓ Type-safe: withMaxTokens() expects int\n";
echo "  ✓ IDE autocomplete: Shows all available methods\n";
echo "  ✓ Self-documenting: Clear what each parameter does\n";
echo "  ✓ Chainable: Fluent interface\n\n";

// ============================================================================
// Example 3: Complete Configuration
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Complete Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$calculator = Tool::create('calculate')
    ->description('Perform calculations')
    ->stringParam('expression', 'Math expression')
    ->handler(fn($input) => eval("return {$input['expression']};"));

$completeConfig = AgentConfigBuilder::create()
    // Model settings
    ->withModel('claude-opus-4-20250514')
    ->withMaxTokens(4096)
    ->withTemperature(0.7)
    ->withTopP(0.9)
    
    // Agent settings
    ->withName('complete_agent')
    ->withSystemPrompt('You are a comprehensive assistant with all features enabled')
    ->withMaxIterations(15)
    ->withTimeout(300)
    
    // Extended thinking
    ->withThinking(10000)
    
    // Tools
    ->addTool($calculator)
    
    // Custom options
    ->withCustomOption('caching', true)
    ->withCustomOption('streaming', false)
    
    ->build();

echo "✅ Built complete configuration with all features:\n\n";

echo "Configuration includes:\n";
echo "  • Model: claude-opus-4-20250514\n";
echo "  • Max Tokens: 4096\n";
echo "  • Temperature: 0.7\n";
echo "  • Top P: 0.9\n";
echo "  • Name: complete_agent\n";
echo "  • System Prompt: Custom\n";
echo "  • Max Iterations: 15\n";
echo "  • Timeout: 300s\n";
echo "  • Extended Thinking: 10000 tokens\n";
echo "  • Tools: 1 tool added\n";
echo "  • Custom Options: caching, streaming\n\n";

// ============================================================================
// Example 4: Builder Methods Reference
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Available Builder Methods\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Model Configuration:\n";
echo "  • withModel(string \$model)\n";
echo "  • withMaxTokens(int \$maxTokens)\n";
echo "  • withTemperature(float \$temperature)\n";
echo "  • withTopP(float \$topP)\n";
echo "  • withTopK(int \$topK)\n\n";

echo "Agent Configuration:\n";
echo "  • withName(string \$name)\n";
echo "  • withSystemPrompt(string \$prompt)\n";
echo "  • withMaxIterations(int \$iterations)\n";
echo "  • withTimeout(int \$seconds)\n\n";

echo "Extended Thinking:\n";
echo "  • withThinking(int \$budgetTokens)  // Shorthand\n";
echo "  • withThinkingConfig(array \$config)  // Full config\n\n";

echo "Tools:\n";
echo "  • withTools(array \$tools)  // Set all tools\n";
echo "  • addTool(Tool \$tool)  // Add single tool\n\n";

echo "Custom Options:\n";
echo "  • withCustomOption(string \$key, mixed \$value)\n";
echo "  • withCustomOptions(array \$options)\n\n";

echo "Build:\n";
echo "  • build(): AgentConfig  // Returns object\n";
echo "  • toArray(): array  // Returns array\n\n";

// ============================================================================
// Example 5: Reusable Configuration Templates
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Reusable Configuration Templates\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Base configuration
$baseConfig = AgentConfigBuilder::create()
    ->withModel('claude-sonnet-4-20250514')
    ->withMaxTokens(2048)
    ->withTimeout(300);

echo "Created base configuration template\n\n";

// Production variant
$productionConfig = clone $baseConfig;
$productionConfig
    ->withThinking(10000)
    ->withMaxIterations(15)
    ->withCustomOption('caching', true);

echo "✅ Production config (base + thinking + caching)\n";

// Development variant
$devConfig = clone $baseConfig;
$devConfig
    ->withMaxIterations(3)
    ->withCustomOption('debug', true);

echo "✅ Development config (base + limited iterations + debug)\n";

// Testing variant
$testConfig = clone $baseConfig;
$testConfig
    ->withMaxTokens(512)
    ->withMaxIterations(2)
    ->withCustomOption('mock_mode', true);

echo "✅ Test config (base + reduced tokens + mock mode)\n\n";

echo "💡 Clone base config for different environments:\n";
echo "  • Production: Full features, caching enabled\n";
echo "  • Development: Debug mode, fewer iterations\n";
echo "  • Testing: Mock mode, minimal tokens\n\n";

// ============================================================================
// Example 6: Integration with Factory
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Builder + Factory Integration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$logger = new Logger('builder_demo');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$factory = new AgentFactory($client, $logger);

// Build config with Builder
$agentConfig = AgentConfigBuilder::create()
    ->withModel('claude-sonnet-4-20250514')
    ->withMaxTokens(2048)
    ->withMaxIterations(5)
    ->toArray();  // Convert to array for factory

// Create agent with Factory
$agent = $factory->create('react', $agentConfig);

echo "✅ Combined Builder + Factory:\n\n";
echo "```php\n";
echo "\$factory = new AgentFactory(\$client, \$logger);\n\n";
echo "\$config = AgentConfigBuilder::create()\n";
echo "    ->withMaxTokens(2048)\n";
echo "    ->withMaxIterations(5)\n";
echo "    ->toArray();\n\n";
echo "\$agent = \$factory->create('react', \$config);\n";
echo "```\n\n";

echo "💡 Best of both patterns:\n";
echo "  • Builder: Type-safe configuration\n";
echo "  • Factory: Consistent agent creation\n";
echo "  • Result: Production-ready code\n\n";

// ============================================================================
// Example 7: Type Safety Demonstration
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 6: Type Safety Benefits\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Type safety prevents common errors:\n\n";

echo "❌ Array config (runtime error):\n";
echo "```php\n";
echo "\$config = [\n";
echo "    'max_token' => '2048',  // Typo! Runtime error\n";
echo "    'temperature' => 'high',  // Wrong type! Runtime error\n";
echo "];\n";
echo "```\n\n";

echo "✅ Builder (compile-time safety):\n";
echo "```php\n";
echo "\$config = AgentConfigBuilder::create()\n";
echo "    ->withMaxTokens(2048)  // Correct method, int required\n";
echo "    ->withTemperature(0.7);  // float required\n";
echo "// Typos caught by IDE/PHP\n";
echo "// Type errors caught before running\n";
echo "```\n\n";

// ============================================================================
// Summary
// ============================================================================

echo str_repeat("═", 80) . "\n";
echo "📚 Builder Pattern Summary\n";
echo str_repeat("═", 80) . "\n\n";

echo "✅ Benefits:\n\n";

echo "1. Type Safety:\n";
echo "   • Compile-time checking\n";
echo "   • Method signatures enforce types\n";
echo "   • IDE catches typos immediately\n\n";

echo "2. Readability:\n";
echo "   • Self-documenting code\n";
echo "   • Clear parameter names\n";
echo "   • Fluent, chainable interface\n\n";

echo "3. IDE Support:\n";
echo "   • Full autocomplete\n";
echo "   • Inline documentation\n";
echo "   • Type hints\n\n";

echo "4. Maintainability:\n";
echo "   • Easy to add new options\n";
echo "   • Backward compatible\n";
echo "   • Clear deprecation path\n\n";

echo "5. Validation:\n";
echo "   • Early error detection\n";
echo "   • Type constraints\n";
echo "   • Required parameters\n\n";

echo "🎯 When to Use:\n\n";
echo "• Complex configurations (>3 parameters)\n";
echo "• Type safety is important\n";
echo "• Multiple developers on team\n";
echo "• Production code\n";
echo "• When IDE support matters\n\n";

echo "📖 Comparison:\n\n";
echo "Use Builder when:\n";
echo "  ✓ Configuration has many parameters\n";
echo "  ✓ Type safety is critical\n";
echo "  ✓ Team collaboration\n";
echo "  ✓ Long-term maintenance\n\n";

echo "Use Arrays when:\n";
echo "  • Simple configuration (<3 params)\n";
echo "  • Rapid prototyping\n";
echo "  • Dynamic configuration from external sources\n\n";

echo "📚 Learn More:\n";
echo "• Design Patterns Guide: docs/DesignPatterns.md\n";
echo "• Complete Demo: examples/design_patterns_demo.php\n";
echo "• Factory Pattern: examples/factory_pattern_example.php\n";
echo "• Best Practices: docs/BestPractices.md\n\n";

echo str_repeat("═", 80) . "\n";
echo "✨ Builder Pattern Example Complete!\n";
echo str_repeat("═", 80) . "\n";

