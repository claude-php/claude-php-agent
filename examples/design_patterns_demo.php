#!/usr/bin/env php
<?php
/**
 * Design Patterns Demo
 *
 * Comprehensive demonstration of all 6 design patterns in the Claude PHP Agent Framework:
 * 1. Factory Pattern (AgentFactory) - Consistent agent creation
 * 2. Builder Pattern (AgentConfigBuilder) - Fluent configuration
 * 3. Observer Pattern (EventDispatcher) - Lifecycle monitoring
 * 4. Strategy Pattern (ResponseParserChain) - Response parsing
 * 5. Template Pattern (PromptBuilder) - Prompt construction
 * 6. Error Handler Pattern - Resilient execution
 *
 * Run: php examples/design_patterns_demo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Factory\AgentFactory;
use ClaudeAgents\Config\AgentConfigBuilder;
use ClaudeAgents\Events\EventDispatcher;
use ClaudeAgents\Events\{AgentStartedEvent, AgentCompletedEvent, AgentFailedEvent};
use ClaudeAgents\Parsers\ResponseParserChain;
use ClaudeAgents\Parsers\{JsonResponseParser, MarkdownParser};
use ClaudeAgents\Prompts\PromptBuilder;
use ClaudeAgents\Exceptions\ErrorHandler;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment variables
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
echo "║              Design Patterns in Claude PHP Agent Framework                ║\n";
echo "║                    Comprehensive Pattern Demonstration                     ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Example 1: Factory Pattern
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Factory Pattern - Consistent Agent Creation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Setup logger
$logger = new Logger('patterns_demo');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Create factory - centralizes agent creation with consistent configuration
$factory = new AgentFactory($client, $logger);

echo "✅ Created AgentFactory with client and logger\n\n";

// Create agents using factory - all get consistent configuration
echo "Creating agents with factory:\n";

$agent1 = $factory->create('react', ['name' => 'agent1']);
echo "  ✓ Created ReactAgent 'agent1' using generic create()\n";

$agent2 = $factory->createReactAgent(['name' => 'agent2', 'max_iterations' => 5]);
echo "  ✓ Created ReactAgent 'agent2' using specific method\n";

// Benefits demonstrated:
echo "\n📊 Factory Pattern Benefits:\n";
echo "  • Consistency: All agents created with same defaults\n";
echo "  • DRY: No duplication of construction logic\n";
echo "  • Logger injection: Automatically provided to all agents\n";
echo "  • Testability: Easy to mock factory in tests\n\n";

// ============================================================================
// Example 2: Builder Pattern
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Builder Pattern - Fluent Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Build complex configuration using fluent API
$config = AgentConfigBuilder::create()
    ->withModel('claude-sonnet-4-20250514')
    ->withMaxTokens(2048)
    ->withMaxIterations(5)
    ->withSystemPrompt('You are a helpful assistant demonstrating design patterns')
    ->withTemperature(0.7)
    ->build();

echo "✅ Built agent configuration using Builder pattern\n\n";

echo "Configuration details:\n";
echo "  • Model: claude-sonnet-4-20250514\n";
echo "  • Max Tokens: 2048\n";
echo "  • Max Iterations: 5\n";
echo "  • Temperature: 0.7\n\n";

echo "📊 Builder Pattern Benefits:\n";
echo "  • Type Safety: Typos caught at compile time\n";
echo "  • Readability: Self-documenting code\n";
echo "  • IDE Support: Full autocomplete\n";
echo "  • Flexibility: Easy to add optional parameters\n\n";

// ============================================================================
// Example 3: Observer Pattern (Event System)
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Observer Pattern - Event-Driven Monitoring\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create event dispatcher
$dispatcher = new EventDispatcher();

// Metrics collector
$metrics = [
    'total' => 0,
    'successful' => 0,
    'failed' => 0,
    'total_duration' => 0.0,
];

// Subscribe to events
$dispatcher->listen(AgentStartedEvent::class, function($event) {
    echo "🚀 Agent '{$event->getAgentName()}' started\n";
    echo "   Task: {$event->getTask()}\n";
});

$dispatcher->listen(AgentCompletedEvent::class, function($event) use (&$metrics) {
    $metrics['total']++;
    $metrics['successful']++;
    $metrics['total_duration'] += $event->getDuration();
    
    echo "✅ Agent '{$event->getAgentName()}' completed\n";
    echo "   Duration: " . round($event->getDuration(), 2) . "s\n";
    echo "   Iterations: {$event->getIterations()}\n";
});

$dispatcher->listen(AgentFailedEvent::class, function($event) use (&$metrics) {
    $metrics['total']++;
    $metrics['failed']++;
    
    echo "❌ Agent '{$event->getAgentName()}' failed\n";
    echo "   Error: {$event->getError()}\n";
});

echo "✅ Event dispatcher configured with 3 listeners\n\n";

// Simulate events
echo "Simulating agent lifecycle events:\n\n";

$dispatcher->dispatch(new AgentStartedEvent('demo_agent', 'Demonstrate patterns'));
sleep(1);
$dispatcher->dispatch(new AgentCompletedEvent('demo_agent', duration: 1.0, iterations: 3, result: 'Success'));

echo "\n📊 Observer Pattern Benefits:\n";
echo "  • Decoupling: Agents don't know about observers\n";
echo "  • Extensibility: Add listeners without modifying agents\n";
echo "  • Multiple Listeners: Many observers per event\n";
echo "  • Runtime Configuration: Add/remove dynamically\n\n";

echo "📈 Collected Metrics:\n";
echo "  • Total runs: {$metrics['total']}\n";
echo "  • Successful: {$metrics['successful']}\n";
echo "  • Failed: {$metrics['failed']}\n";
echo "  • Avg duration: " . round($metrics['total_duration'] / max($metrics['successful'], 1), 2) . "s\n\n";

// ============================================================================
// Example 4: Strategy Pattern (Response Parser Chain)
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Strategy Pattern - Flexible Response Parsing\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create parser chain - tries each parser until one succeeds
$parserChain = new ResponseParserChain([
    new JsonResponseParser(),
    new MarkdownParser(),
]);

echo "✅ Created ResponseParserChain with JSON and Markdown parsers\n\n";

// Test with JSON response
$jsonResponse = '```json
{
    "status": "success",
    "data": {
        "patterns": ["Factory", "Builder", "Observer"]
    }
}
```';

echo "Parsing JSON response:\n";
$parsed1 = $parserChain->parse($jsonResponse);
echo "  ✓ Parsed successfully using JsonResponseParser\n";
echo "  Result: " . json_encode($parsed1, JSON_PRETTY_PRINT) . "\n\n";

// Test with Markdown response
$markdownResponse = '# Results

The framework uses these patterns:
- **Factory**: Consistent creation
- **Builder**: Fluent configuration
- **Observer**: Event monitoring';

echo "Parsing Markdown response:\n";
$parsed2 = $parserChain->parse($markdownResponse);
echo "  ✓ Parsed successfully using MarkdownParser\n";
echo "  Headings found: " . count($parsed2['headings']) . "\n";
echo "  Lists found: " . count($parsed2['lists']) . "\n\n";

echo "📊 Strategy Pattern Benefits:\n";
echo "  • Flexibility: Multiple parsing strategies\n";
echo "  • Automatic: Chain tries parsers until success\n";
echo "  • Extensible: Add parsers without modifying existing code\n";
echo "  • Clean: Each parser has single responsibility\n\n";

// ============================================================================
// Example 5: Template Pattern (Prompt Builder)
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Template Pattern - Structured Prompt Construction\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Build structured prompt using template pattern
$prompt = PromptBuilder::create()
    ->addContext(
        'You are a software architecture expert explaining design patterns. ' .
        'Your goal is to help developers understand when and how to use each pattern.'
    )
    ->addTask('Explain the Factory Pattern in one sentence')
    ->addExample(
        'Input: Explain Builder Pattern',
        'Output: The Builder Pattern provides a fluent API for constructing complex objects with many optional parameters.'
    )
    ->addConstraint('Be concise and clear')
    ->addConstraint('Focus on the core benefit')
    ->addInstructions('Format your response as a single sentence')
    ->build();

echo "✅ Built structured prompt using PromptBuilder\n\n";

echo "Prompt structure:\n";
echo "  • Context: Set the role and goal\n";
echo "  • Task: Define what to do\n";
echo "  • Example: Show expected format\n";
echo "  • Constraints: Set requirements\n";
echo "  • Instructions: Specify output format\n\n";

echo "Generated prompt preview:\n";
echo str_repeat("-", 76) . "\n";
echo substr($prompt, 0, 200) . "...\n";
echo str_repeat("-", 76) . "\n\n";

echo "📊 Template Pattern Benefits:\n";
echo "  • Readability: Clear prompt structure\n";
echo "  • Reusability: Build prompts programmatically\n";
echo "  • Consistency: Standard prompt format\n";
echo "  • Maintainability: Easy to modify structure\n\n";

// ============================================================================
// Example 6: Error Handler Pattern
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 6: Error Handler Pattern - Resilient Execution\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create error handler with retry logic
$errorHandler = new ErrorHandler($logger, maxRetries: 3, initialDelayMs: 100);

echo "✅ Created ErrorHandler with retry logic\n";
echo "   Max retries: 3\n";
echo "   Initial delay: 100ms\n";
echo "   Backoff: Exponential\n\n";

// Simulate operation that might fail
$attemptCount = 0;
echo "Simulating flaky operation:\n";

try {
    $result = $errorHandler->executeWithRetry(
        fn: function() use (&$attemptCount) {
            $attemptCount++;
            echo "  Attempt {$attemptCount}...\n";
            
            // Succeed on 3rd attempt
            if ($attemptCount < 3) {
                throw new RuntimeException("Simulated transient error");
            }
            
            return "Operation succeeded!";
        },
        context: 'Demo operation'
    );
    
    echo "\n✅ Operation completed: {$result}\n\n";
} catch (Exception $e) {
    echo "\n❌ All retries exhausted: {$e->getMessage()}\n\n";
}

echo "📊 Error Handler Pattern Benefits:\n";
echo "  • Resilience: Automatic retry on transient failures\n";
echo "  • Consistency: Same error handling everywhere\n";
echo "  • Observability: Callbacks for monitoring\n";
echo "  • Smart: Exponential backoff prevents overwhelming\n\n";

// ============================================================================
// Example 7: All Patterns Together
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 7: Combined Patterns - Production-Ready Setup\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Combining all patterns for production-ready agent:\n\n";

// 1. Factory for agent creation
echo "1. Using Factory for consistent agent creation\n";
$productionFactory = new AgentFactory($client, $logger);

// 2. Builder for configuration
echo "2. Using Builder for type-safe configuration\n";
$productionConfig = AgentConfigBuilder::create()
    ->withModel('claude-sonnet-4-20250514')
    ->withMaxTokens(1024)
    ->withMaxIterations(3)
    ->withTemperature(0.7)
    ->toArray();

// 3. Events for monitoring
echo "3. Using Events for decoupled monitoring\n";
$productionDispatcher = new EventDispatcher();
$productionDispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "   ✓ Agent completed in " . round($event->getDuration(), 2) . "s\n";
});

// 4. Parser chain for robust parsing
echo "4. Using ParserChain for flexible parsing\n";
$productionParser = new ResponseParserChain([
    new JsonResponseParser(),
    new MarkdownParser(),
]);

// 5. Prompt builder for structured prompts
echo "5. Using PromptBuilder for clear prompts\n";
$productionPrompt = PromptBuilder::create()
    ->addContext('You are a helpful assistant')
    ->addTask('Say hello and list 2 design patterns')
    ->addConstraint('Be brief')
    ->build();

// 6. Error handler for resilience
echo "6. Using ErrorHandler for resilient execution\n";
$productionErrorHandler = new ErrorHandler($logger, maxRetries: 2);

echo "\n✅ All patterns configured!\n\n";

echo "This production setup provides:\n";
echo "  • Consistent agent creation (Factory)\n";
echo "  • Type-safe configuration (Builder)\n";
echo "  • Decoupled monitoring (Observer)\n";
echo "  • Flexible parsing (Strategy)\n";
echo "  • Structured prompts (Template)\n";
echo "  • Resilient execution (ErrorHandler)\n\n";

// Create and run agent with all patterns
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'Math expression to evaluate')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return "Error: Invalid expression";
        }
        try {
            $result = eval("return {$expr};");
            return (string) $result;
        } catch (Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    });

$fullStackConfig = AgentConfigBuilder::create()
    ->withModel('claude-sonnet-4-20250514')
    ->withMaxTokens(512)
    ->withMaxIterations(3)
    ->addTool($calculator)
    ->toArray();

$fullStackAgent = $productionFactory->create('react', $fullStackConfig);

echo "Running agent with full pattern stack:\n\n";

$productionDispatcher->dispatch(new AgentStartedEvent('full_stack_agent', 'Calculate 15 * 23'));

$startTime = microtime(true);
$agentResult = $fullStackAgent->run('What is 15 multiplied by 23? Use the calculate tool.');
$duration = microtime(true) - $startTime;

if ($agentResult->isSuccess()) {
    echo "✅ Agent execution successful!\n";
    echo "   Answer: " . substr($agentResult->getAnswer(), 0, 100) . "...\n";
    echo "   Iterations: {$agentResult->getIterations()}\n";
    
    $productionDispatcher->dispatch(new AgentCompletedEvent(
        'full_stack_agent',
        duration: $duration,
        iterations: $agentResult->getIterations(),
        result: $agentResult->getAnswer()
    ));
} else {
    echo "❌ Agent execution failed\n";
    echo "   Error: {$agentResult->getError()}\n";
}

// ============================================================================
// Summary
// ============================================================================
echo "\n" . str_repeat("═", 80) . "\n";
echo "📚 Design Patterns Summary\n";
echo str_repeat("═", 80) . "\n\n";

echo "The Claude PHP Agent Framework uses 6 production-ready design patterns:\n\n";

echo "1. 🏭 Factory Pattern (AgentFactory)\n";
echo "   → Consistent agent creation with dependency injection\n";
echo "   → Use: Always in production code\n\n";

echo "2. ⚙️  Builder Pattern (AgentConfigBuilder)\n";
echo "   → Type-safe, fluent configuration API\n";
echo "   → Use: Complex configurations (>3 parameters)\n\n";

echo "3. 📡 Observer Pattern (EventDispatcher)\n";
echo "   → Decoupled lifecycle event monitoring\n";
echo "   → Use: Monitoring, logging, analytics\n\n";

echo "4. 🔄 Strategy Pattern (ResponseParserChain)\n";
echo "   → Pluggable response parsing with fallthrough\n";
echo "   → Use: Multiple response formats\n\n";

echo "5. 📝 Template Pattern (PromptBuilder)\n";
echo "   → Structured prompt construction\n";
echo "   → Use: Complex, multi-section prompts\n\n";

echo "6. 🛡️  Error Handler Pattern\n";
echo "   → Unified error handling with retry\n";
echo "   → Use: All external API calls\n\n";

echo str_repeat("─", 80) . "\n\n";

echo "💡 Key Takeaways:\n\n";
echo "  • Use Factory for all agent creation in production\n";
echo "  • Use Builder when configuration has >3 parameters\n";
echo "  • Use Events for monitoring and observability\n";
echo "  • Use ParserChain for robust response handling\n";
echo "  • Use PromptBuilder for complex prompts\n";
echo "  • Use ErrorHandler for all external calls\n\n";

echo "📖 Learn More:\n";
echo "  • Design Patterns Guide: docs/DesignPatterns.md\n";
echo "  • Best Practices: docs/BestPractices.md\n";
echo "  • Individual Examples: examples/factory_pattern_example.php, etc.\n\n";

echo str_repeat("═", 80) . "\n";
echo "✨ Design Patterns Demo Complete!\n";
echo str_repeat("═", 80) . "\n";

