#!/usr/bin/env php
<?php
/**
 * Factory Pattern Example
 *
 * Demonstrates the Factory Pattern for consistent agent creation with:
 * - Centralized agent creation
 * - Automatic logger injection
 * - Consistent configuration
 * - Easy testing and mocking
 *
 * Run: php examples/factory_pattern_example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

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
echo "║                         Factory Pattern Example                            ║\n";
echo "║              Consistent Agent Creation with Dependency Injection           ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Setup: Create Factory
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Step 1: Create AgentFactory\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$logger = new Logger('factory_demo');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$factory = new AgentFactory($client, $logger);

echo "✅ Created AgentFactory\n";
echo "   • Client: ClaudePhp instance\n";
echo "   • Logger: Monolog logger (auto-injected to all agents)\n\n";

// ============================================================================
// Example 1: Generic Agent Creation
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Generic Agent Creation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create using generic create() method
$agent1 = $factory->create('react', [
    'name' => 'generic_agent',
    'max_iterations' => 5,
]);

echo "✅ Created ReactAgent using generic create() method\n";
echo "   Type: 'react'\n";
echo "   Name: 'generic_agent'\n";
echo "   Max Iterations: 5\n";
echo "   Logger: Automatically injected ✓\n\n";

// ============================================================================
// Example 2: Specific Agent Type Methods
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Specific Agent Type Methods\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Creating different agent types:\n\n";

// ReactAgent
$reactAgent = $factory->createReactAgent(['name' => 'react_worker']);
echo "1. ✅ ReactAgent 'react_worker'\n";

// ChainOfThoughtAgent
$cotAgent = $factory->createChainOfThoughtAgent([
    'name' => 'cot_analyzer',
    'mode' => 'few_shot',
]);
echo "2. ✅ ChainOfThoughtAgent 'cot_analyzer' (mode: few_shot)\n";

// PlanExecuteAgent
$planAgent = $factory->createPlanExecuteAgent([
    'name' => 'plan_executor',
    'max_steps' => 10,
]);
echo "3. ✅ PlanExecuteAgent 'plan_executor' (max_steps: 10)\n";

echo "\n💡 All agents automatically get:\n";
echo "   • Client instance\n";
echo "   • Logger (no need to pass manually)\n";
echo "   • Consistent default configuration\n\n";

// ============================================================================
// Example 3: Consistency Across Team
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Consistency Across Development Team\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "❌ Without Factory (inconsistent):\n\n";
echo "```php\n";
echo "// Developer A\n";
echo "\$agent1 = new ReactAgent(\$client);\n\n";
echo "// Developer B\n";
echo "\$agent2 = new ReactAgent(\$client, ['name' => 'agent2']);\n\n";
echo "// Developer C\n";
echo "\$agent3 = new ReactAgent(\$client, ['name' => 'agent3'], \$logger);\n\n";
echo "// Problems:\n";
echo "//  - Inconsistent logger injection\n";
echo "//  - Different default configurations\n";
echo "//  - Hard to change defaults project-wide\n";
echo "```\n\n";

echo "✅ With Factory (consistent):\n\n";
echo "```php\n";
echo "\$factory = new AgentFactory(\$client, \$logger);\n\n";
echo "// All developers use factory\n";
echo "\$agent1 = \$factory->create('react');\n";
echo "\$agent2 = \$factory->create('react', ['name' => 'agent2']);\n";
echo "\$agent3 = \$factory->createReactAgent(['name' => 'agent3']);\n\n";
echo "// Benefits:\n";
echo "//  - Logger always injected\n";
echo "//  - Consistent configuration\n";
echo "//  - Change defaults in one place\n";
echo "```\n\n";

// ============================================================================
// Example 4: Multi-Agent System
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Multi-Agent System\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Creating a team of agents for different tasks:\n\n";

$agents = [
    'manager' => $factory->createHierarchicalAgent(['name' => 'team_manager']),
    'researcher' => $factory->createRAGAgent(['name' => 'researcher']),
    'analyst' => $factory->createChainOfThoughtAgent(['name' => 'analyst']),
    'executor' => $factory->createReactAgent(['name' => 'executor']),
];

foreach ($agents as $role => $agent) {
    echo "✅ Created {$role} agent\n";
}

echo "\n💡 All agents in the system:\n";
echo "   • Created with same factory\n";
echo "   • Share same logger\n";
echo "   • Have consistent configuration\n";
echo "   • Easy to manage as a team\n\n";

// ============================================================================
// Example 5: Testing with Factory
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Testing Benefits\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Factory makes testing easier:\n\n";

echo "```php\n";
echo "// In your tests\n";
echo "class AgentServiceTest extends TestCase\n";
echo "{\n";
echo "    public function testAgentExecution(): void\n";
echo "    {\n";
echo "        // Mock the factory\n";
echo "        \$mockFactory = \$this->createMock(AgentFactory::class);\n";
echo "        \$mockAgent = \$this->createMock(ReactAgent::class);\n";
echo "        \n";
echo "        \$mockFactory->method('create')\n";
echo "            ->willReturn(\$mockAgent);\n";
echo "        \n";
echo "        // Inject mocked factory\n";
echo "        \$service = new AgentService(\$mockFactory);\n";
echo "        \n";
echo "        // Test without real API calls\n";
echo "        \$result = \$service->processTask('test');\n";
echo "        \$this->assertTrue(\$result->isSuccess());\n";
echo "    }\n";
echo "}\n";
echo "```\n\n";

// ============================================================================
// Example 6: Available Factory Methods
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 6: Complete Factory API\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "All available factory methods:\n\n";

$methods = [
    'create(type, options)' => 'Generic creation for any agent type',
    'createReactAgent(options)' => 'ReAct pattern agent',
    'createChainOfThoughtAgent(options)' => 'Chain of Thought reasoning',
    'createTreeOfThoughtsAgent(options)' => 'Tree exploration agent',
    'createPlanExecuteAgent(options)' => 'Planning and execution',
    'createReflectionAgent(options)' => 'Self-improvement agent',
    'createRAGAgent(options)' => 'Retrieval-augmented generation',
    'createHierarchicalAgent(options)' => 'Manager-worker pattern',
    'createDialogAgent(options)' => 'Conversational agent',
    'createAutonomousAgent(options)' => 'Long-running autonomy',
];

foreach ($methods as $method => $description) {
    echo "• {$method}\n";
    echo "  → {$description}\n\n";
}

// ============================================================================
// Summary
// ============================================================================

echo str_repeat("═", 80) . "\n";
echo "📚 Factory Pattern Summary\n";
echo str_repeat("═", 80) . "\n\n";

echo "✅ Benefits:\n\n";
echo "1. Consistency:\n";
echo "   • All agents created with same configuration\n";
echo "   • No variation between developers\n";
echo "   • Easy to maintain standards\n\n";

echo "2. Dependency Injection:\n";
echo "   • Logger automatically provided\n";
echo "   • Client passed to all agents\n";
echo "   • No manual wiring required\n\n";

echo "3. Maintainability:\n";
echo "   • Change defaults in one place\n";
echo "   • No scattered construction logic\n";
echo "   • DRY principle applied\n\n";

echo "4. Testability:\n";
echo "   • Easy to mock\n";
echo "   • Inject test doubles\n";
echo "   • Unit test friendly\n\n";

echo "5. Discoverability:\n";
echo "   • One place to see all agent types\n";
echo "   • Clear API\n";
echo "   • Self-documenting\n\n";

echo "🎯 When to Use:\n\n";
echo "• Creating multiple agents in your application\n";
echo "• Production code requiring consistency\n";
echo "• Multi-agent systems\n";
echo "• When logger injection is needed\n";
echo "• Testing scenarios\n\n";

echo "📖 Learn More:\n";
echo "• Design Patterns Guide: docs/DesignPatterns.md\n";
echo "• Complete Demo: examples/design_patterns_demo.php\n";
echo "• Builder Pattern: examples/builder_pattern_example.php\n";
echo "• Best Practices: docs/BestPractices.md\n\n";

echo str_repeat("═", 80) . "\n";
echo "✨ Factory Pattern Example Complete!\n";
echo str_repeat("═", 80) . "\n";

