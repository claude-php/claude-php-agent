<?php

/**
 * Production Patterns Example
 * 
 * Demonstrates production-ready patterns including:
 * - Comprehensive error handling
 * - Circuit breaker pattern
 * - Structured logging
 * - Retry logic with exponential backoff
 * 
 * Run: php examples/production_patterns_example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Helpers\ErrorHandler;
use ClaudeAgents\Helpers\CircuitBreaker;
use ClaudeAgents\Helpers\AgentLogger;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    die("Error: ANTHROPIC_API_KEY not set\n");
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Production Patterns Example                                 ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Example 1: Structured Logging
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Structured Logging with AgentLogger\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create logger
$monologLogger = new Logger('agent');
$monologLogger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$agentLogger = new AgentLogger($monologLogger);

// Simulate some agent activity
echo "Simulating agent activity with logging...\n\n";

// Mock response object
$mockResponse = (object)[
    'stop_reason' => 'tool_use',
    'content' => [
        ['type' => 'tool_use', 'name' => 'calculate', 'id' => 'tool_1'],
        ['type' => 'tool_use', 'name' => 'search', 'id' => 'tool_2'],
    ],
    'usage' => (object)[
        'input_tokens' => 150,
        'output_tokens' => 75,
    ],
];

$agentLogger->logIteration(1, $mockResponse, 'TestAgent');

$agentLogger->logToolExecution(
    toolName: 'calculate',
    input: ['expression' => '2 + 2'],
    result: '4',
    success: true,
    duration: 0.05
);

$agentLogger->logToolExecution(
    toolName: 'database_query',
    input: ['query' => 'SELECT * FROM users'],
    result: 'Connection timeout',
    success: false,
    duration: 5.0
);

$agentLogger->logSessionSummary(success: true, agentName: 'TestAgent');

$metrics = $agentLogger->getMetrics();
echo "\n📊 Session Metrics:\n";
echo json_encode($metrics, JSON_PRETTY_PRINT) . "\n\n";

// Example 2: Error Handler with Retry Logic
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Error Handler with Retry Logic\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$errorHandler = new ErrorHandler(
    logger: $monologLogger,
    maxRetries: 3,
    initialDelayMs: 500
);

// Simulate a flaky operation
$attemptCount = 0;
try {
    $result = $errorHandler->executeWithRetry(
        fn: function() use (&$attemptCount) {
            $attemptCount++;
            echo "Attempt {$attemptCount}...\n";
            
            if ($attemptCount < 3) {
                // Simulate transient error
                throw new \ClaudePhp\Exceptions\APIConnectionError("Simulated connection error");
            }
            
            return "Operation succeeded!";
        },
        context: 'Flaky operation'
    );
    
    echo "✅ Result: {$result}\n\n";
} catch (Exception $e) {
    echo "❌ Final error: {$e->getMessage()}\n\n";
}

// Example 3: Safe Tool Execution
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Safe Tool Execution\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Successful tool
$result1 = $errorHandler->executeToolSafely(
    toolFn: fn($input) => "Result: " . ($input['value'] * 2),
    toolName: 'double_value',
    input: ['value' => 21]
);

echo "Tool 1 - Success:\n";
echo json_encode($result1, JSON_PRETTY_PRINT) . "\n\n";

// Failing tool
$result2 = $errorHandler->executeToolSafely(
    toolFn: fn($input) => throw new Exception("Database connection failed"),
    toolName: 'database_query',
    input: ['query' => 'SELECT * FROM users']
);

echo "Tool 2 - Error:\n";
echo json_encode($result2, JSON_PRETTY_PRINT) . "\n\n";

// Tool with fallback
$result3 = $errorHandler->executeToolWithFallback(
    toolFn: fn($input) => throw new Exception("External API unavailable"),
    toolName: 'weather_api',
    input: ['location' => 'San Francisco'],
    fallback: 'Weather service temporarily unavailable. Please try again later.'
);

echo "Tool 3 - With Fallback:\n";
echo $result3 . "\n\n";

// Example 4: Input Validation
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Tool Input Validation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$schema = [
    'required' => ['name', 'age'],
    'properties' => [
        'name' => ['type' => 'string'],
        'age' => ['type' => 'integer'],
        'email' => ['type' => 'string'],
    ],
];

// Valid input
$validInput = ['name' => 'Alice', 'age' => 30, 'email' => 'alice@example.com'];
$validation1 = $errorHandler->validateToolInput($validInput, $schema);

echo "Valid input:\n";
echo json_encode($validation1, JSON_PRETTY_PRINT) . "\n\n";

// Invalid input (missing required field)
$invalidInput = ['name' => 'Bob'];
$validation2 = $errorHandler->validateToolInput($invalidInput, $schema);

echo "Invalid input (missing field):\n";
echo json_encode($validation2, JSON_PRETTY_PRINT) . "\n\n";

// Invalid input (wrong type)
$invalidInput2 = ['name' => 'Charlie', 'age' => 'thirty'];
$validation3 = $errorHandler->validateToolInput($invalidInput2, $schema);

echo "Invalid input (wrong type):\n";
echo json_encode($validation3, JSON_PRETTY_PRINT) . "\n\n";

// Example 5: Circuit Breaker Pattern
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Circuit Breaker Pattern\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$circuitBreaker = new CircuitBreaker(
    name: 'external_api',
    failureThreshold: 3,
    timeoutSeconds: 5,
    successThreshold: 2,
    logger: $monologLogger
);

// Simulate multiple failures
echo "Simulating failures to open circuit...\n\n";

for ($i = 1; $i <= 5; $i++) {
    try {
        $circuitBreaker->call(function() use ($i) {
            echo "Attempt {$i}: ";
            
            if ($i <= 3) {
                throw new Exception("Service unavailable");
            }
            
            return "Success";
        });
        
        echo "✅ Success\n";
    } catch (\ClaudeAgents\Helpers\CircuitBreakerOpenException $e) {
        echo "🚫 Circuit OPEN - " . $e->getMessage() . "\n";
    } catch (Exception $e) {
        echo "❌ Failed - " . $e->getMessage() . "\n";
    }
}

echo "\n";
$stats = $circuitBreaker->getStats();
echo "Circuit Breaker Stats:\n";
echo json_encode($stats, JSON_PRETTY_PRINT) . "\n\n";

// Wait for timeout and test recovery
echo "Waiting for circuit to test recovery...\n";
sleep(6);

echo "Testing recovery...\n";
for ($i = 1; $i <= 3; $i++) {
    try {
        $circuitBreaker->call(function() use ($i) {
            echo "Recovery attempt {$i}: ";
            // Simulate success
            return "Success";
        });
        
        echo "✅ Success\n";
    } catch (Exception $e) {
        echo "❌ Failed - " . $e->getMessage() . "\n";
    }
}

echo "\n";
$stats = $circuitBreaker->getStats();
echo "Final Circuit Breaker Stats:\n";
echo json_encode($stats, JSON_PRETTY_PRINT) . "\n\n";

// Example 6: Rate Limiter
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 6: Rate Limiter\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$rateLimiter = ErrorHandler::createRateLimiter(minIntervalMs: 500);

echo "Making requests with rate limiting (500ms minimum interval)...\n";

for ($i = 1; $i <= 3; $i++) {
    $start = microtime(true);
    $rateLimiter();
    $elapsed = (microtime(true) - $start) * 1000;
    
    echo "Request {$i} - Delay: " . round($elapsed, 2) . "ms\n";
}

echo "\n";

// Example 7: Factory Pattern
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 7: Factory Pattern - Consistent Agent Creation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

use ClaudeAgents\Factory\AgentFactory;

$client = new ClaudePhp(apiKey: $apiKey);
$factory = new AgentFactory($client, $monologLogger);

echo "✅ Created AgentFactory with client and logger\n\n";

echo "Creating agents with factory:\n";

// All agents get consistent configuration and logger injection
$agent1 = $factory->create('react', ['name' => 'production_agent_1']);
echo "  ✓ Created agent 'production_agent_1'\n";

$agent2 = $factory->createReactAgent(['name' => 'production_agent_2', 'max_iterations' => 5]);
echo "  ✓ Created agent 'production_agent_2' with custom iterations\n\n";

echo "Benefits:\n";
echo "  • Consistent configuration: All agents created with same defaults\n";
echo "  • Automatic logger injection: No need to pass logger manually\n";
echo "  • Testability: Easy to mock factory in unit tests\n";
echo "  • Maintainability: Change defaults in one place\n\n";

echo "Factory Methods Available:\n";
echo "  • create(type, options) - Generic creation\n";
echo "  • createReactAgent(options) - Specific agent type\n";
echo "  • createChainOfThoughtAgent(options)\n";
echo "  • createPlanExecuteAgent(options)\n";
echo "  • createRAGAgent(options)\n";
echo "  • And more...\n\n";

// Example 8: Builder Pattern
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 8: Builder Pattern - Type-Safe Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

use ClaudeAgents\Config\AgentConfigBuilder;

echo "Building agent configuration with Builder pattern:\n\n";

$config = AgentConfigBuilder::create()
    ->withModel('claude-sonnet-4-20250514')
    ->withMaxTokens(2048)
    ->withMaxIterations(10)
    ->withSystemPrompt('You are a production-ready assistant')
    ->withTemperature(0.7)
    ->withTimeout(300)
    ->withCustomOption('caching', true)
    ->build();

echo "✅ Configuration built successfully\n\n";

echo "Configuration details:\n";
echo "  • Model: claude-sonnet-4-20250514\n";
echo "  • Max Tokens: 2048\n";
echo "  • Max Iterations: 10\n";
echo "  • Temperature: 0.7\n";
echo "  • Timeout: 300s\n";
echo "  • Custom: caching=true\n\n";

echo "Benefits:\n";
echo "  • Type Safety: Typos caught at compile time\n";
echo "  • IDE Support: Full autocomplete\n";
echo "  • Readability: Self-documenting code\n";
echo "  • Validation: Early error detection\n\n";

// Combine Factory + Builder
echo "Combining Factory + Builder:\n\n";

$productionConfig = AgentConfigBuilder::create()
    ->withMaxTokens(4096)
    ->withMaxIterations(15)
    ->toArray();  // Convert to array for factory

$productionAgent = $factory->create('react', $productionConfig);
echo "✅ Created agent using Factory + Builder pattern\n\n";

// Example 9: Event System
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 9: Event System - Decoupled Monitoring\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

use ClaudeAgents\Events\EventDispatcher;
use ClaudeAgents\Events\{AgentStartedEvent, AgentCompletedEvent, AgentFailedEvent};

$dispatcher = new EventDispatcher();

echo "Setting up event listeners:\n\n";

// Metrics collector
$eventMetrics = [
    'started' => 0,
    'completed' => 0,
    'failed' => 0,
    'total_duration' => 0.0,
];

// Subscribe to events
$dispatcher->listen(AgentStartedEvent::class, function($event) use (&$eventMetrics) {
    $eventMetrics['started']++;
    echo "🚀 Event: Agent '{$event->getAgentName()}' started\n";
    echo "   Task: {$event->getTask()}\n";
});

$dispatcher->listen(AgentCompletedEvent::class, function($event) use (&$eventMetrics) {
    $eventMetrics['completed']++;
    $eventMetrics['total_duration'] += $event->getDuration();
    echo "✅ Event: Agent '{$event->getAgentName()}' completed\n";
    echo "   Duration: " . round($event->getDuration(), 2) . "s\n";
    echo "   Iterations: {$event->getIterations()}\n";
});

$dispatcher->listen(AgentFailedEvent::class, function($event) use (&$eventMetrics) {
    $eventMetrics['failed']++;
    echo "❌ Event: Agent '{$event->getAgentName()}' failed\n";
    echo "   Error: {$event->getError()}\n";
});

echo "✅ Configured 3 event listeners\n\n";

// Simulate agent lifecycle
echo "Simulating agent lifecycle:\n\n";

$dispatcher->dispatch(new AgentStartedEvent('monitoring_agent', 'Monitor system'));
sleep(1);
$dispatcher->dispatch(new AgentCompletedEvent('monitoring_agent', duration: 1.0, iterations: 3, result: 'Success'));

echo "\n📊 Event Metrics Collected:\n";
echo "  • Started: {$eventMetrics['started']}\n";
echo "  • Completed: {$eventMetrics['completed']}\n";
echo "  • Failed: {$eventMetrics['failed']}\n";
echo "  • Total Duration: " . round($eventMetrics['total_duration'], 2) . "s\n\n";

echo "Benefits:\n";
echo "  • Decoupling: Agents don't know about monitors\n";
echo "  • Extensibility: Add listeners without modifying agents\n";
echo "  • Multiple Listeners: Many observers per event\n";
echo "  • Runtime Configuration: Add/remove dynamically\n\n";

echo "Use Cases:\n";
echo "  • Performance monitoring\n";
echo "  • Alerting and notifications\n";
echo "  • Metrics collection\n";
echo "  • Audit logging\n";
echo "  • Real-time dashboards\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Production Patterns Example Complete\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📚 Patterns Demonstrated:\n";
echo "  1. Structured Logging (AgentLogger)\n";
echo "  2. Error Handling with Retry (ErrorHandler)\n";
echo "  3. Safe Tool Execution\n";
echo "  4. Input Validation\n";
echo "  5. Circuit Breaker Pattern\n";
echo "  6. Rate Limiting\n";
echo "  7. Factory Pattern (AgentFactory) ⭐ NEW\n";
echo "  8. Builder Pattern (AgentConfigBuilder) ⭐ NEW\n";
echo "  9. Event System (EventDispatcher) ⭐ NEW\n\n";

echo "📖 Learn More:\n";
echo "  • Design Patterns Guide: docs/DesignPatterns.md\n";
echo "  • Comprehensive Demo: examples/design_patterns_demo.php\n";
echo "  • Best Practices: docs/BestPractices.md\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

