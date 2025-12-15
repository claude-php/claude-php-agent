# Tutorial 4: Production-Ready Patterns

**Time: 60 minutes** | **Difficulty: Intermediate**

You've built agents that work in ideal conditions. Now let's make them production-ready with comprehensive error handling, logging, monitoring, and resilience patterns.

## 🎯 Learning Objectives

By the end of this tutorial, you'll be able to:

- Implement comprehensive error handling
- Add retry logic with exponential backoff
- Use circuit breakers for resilience
- Implement structured logging and monitoring
- Handle tool execution failures gracefully
- Build production-grade agents
- Monitor costs and performance

## 🏗️ What We're Building

We'll enhance your multi-tool agent with:

1. **Error Handling** - Catch and handle all failure modes
2. **Retry Logic** - Automatically retry transient failures
3. **Circuit Breakers** - Prevent cascading failures
4. **Structured Logging** - Track agent behavior and metrics
5. **Cost Monitoring** - Track token usage and estimate costs
6. **Graceful Degradation** - Continue working when tools fail

## 📋 Prerequisites

Make sure you have:

- Completed [Tutorial 3: Multi-Tool Agent](./03-Multi-Tool.md)
- Understanding of multi-tool agents
- Claude PHP Agent Framework installed
- Basic knowledge of production systems

## 🚨 Production Failure Modes

### Common Failures

1. **API Errors**
   - Rate limiting (429)
   - Temporary outages (503)
   - Authentication issues (401)
   - Timeout errors
   - Network failures

2. **Tool Execution Errors**
   - Invalid input
   - External API failures
   - Calculation errors
   - Resource unavailable
   - Timeout

3. **Agent Errors**
   - Infinite loops
   - Context window overflow
   - Malformed responses
   - Unexpected stop reasons

4. **State Management**
   - Lost conversation history
   - Token limit exceeded
   - Memory issues

## 🛡️ Step 1: Comprehensive Error Handling

### Using ErrorHandler

```php
<?php

use ClaudeAgents\Helpers\ErrorHandler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create logger
$logger = new Logger('production_agent');
$logger->pushHandler(new StreamHandler('logs/agent.log', Logger::DEBUG));
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Create error handler with retry logic
$errorHandler = new ErrorHandler(
    logger: $logger,
    maxRetries: 3,
    initialDelayMs: 1000
);

// Use it to call the API with automatic retry
try {
    $response = $errorHandler->executeWithRetry(
        fn: fn() => $client->messages()->create($params),
        context: 'Agent API call'
    );
} catch (Exception $e) {
    $logger->error('Failed after all retries', [
        'error' => $e->getMessage(),
        'max_retries' => 3,
    ]);
    // Handle final failure
}
```

### Safe Tool Execution

```php
<?php

// Execute tool with error handling
$result = $errorHandler->executeToolSafely(
    toolFn: fn($input) => executeTool($toolName, $input),
    toolName: $toolName,
    input: $input
);

if ($result['success']) {
    // Tool succeeded
    $toolOutput = $result['content'];
} else {
    // Tool failed - error is already logged
    // Return error to Claude
    $toolOutput = $result['content'];  // Contains error message
    $isError = true;
}
```

### Tool with Fallback

```php
<?php

// Execute with fallback value
$result = $errorHandler->executeToolWithFallback(
    toolFn: fn($input) => $weatherAPI->getCurrentWeather($input['location']),
    toolName: 'get_weather',
    input: ['location' => 'Boston'],
    fallback: 'Weather service temporarily unavailable. Please try again later.'
);

// $result always has a value (result or fallback)
```

## 🔄 Step 2: Circuit Breaker Pattern

### Implementing Circuit Breakers

```php
<?php

use ClaudeAgents\Helpers\CircuitBreaker;

// Create circuit breaker for external weather API
$weatherCircuit = new CircuitBreaker(
    name: 'weather_api',
    failureThreshold: 5,      // Open after 5 failures
    timeoutSeconds: 60,       // Wait 60s before testing
    successThreshold: 2,      // Need 2 successes to close
    logger: $logger
);

// Use it to protect calls
try {
    $weather = $weatherCircuit->call(function() use ($weatherAPI, $location) {
        return $weatherAPI->getCurrentWeather($location);
    });
} catch (CircuitBreakerOpenException $e) {
    // Circuit is open - service unavailable
    $logger->warning('Weather API circuit is open', [
        'stats' => $weatherCircuit->getStats()
    ]);
    
    return "Weather service temporarily unavailable. Please try again in a minute.";
}
```

### Multiple Circuit Breakers

```php
<?php

// Create circuit breakers for each external dependency
$circuits = [
    'weather_api' => new CircuitBreaker('weather_api', 5, 60, 2, $logger),
    'database' => new CircuitBreaker('database', 3, 30, 2, $logger),
    'geocoding' => new CircuitBreaker('geocoding', 5, 45, 2, $logger),
];

// Use appropriate circuit breaker for each tool
function executeTool(string $name, array $input, array $circuits) {
    return match($name) {
        'get_weather' => $circuits['weather_api']->call(
            fn() => fetchWeather($input['location'])
        ),
        'geocode' => $circuits['geocoding']->call(
            fn() => geocodeLocation($input['address'])
        ),
        'query_database' => $circuits['database']->call(
            fn() => queryDB($input['query'])
        ),
        default => executeLocalTool($name, $input)
    };
}
```

## 📊 Step 3: Structured Logging & Monitoring

### Using AgentLogger

```php
<?php

use ClaudeAgents\Helpers\AgentLogger;

// Create agent logger
$agentLogger = new AgentLogger($logger);

// Log each iteration
$agentLogger->logIteration($iteration, $response, 'ProductionAgent');

// Log tool execution with timing
$startTime = microtime(true);
$result = executeTool($toolName, $input);
$duration = microtime(true) - $startTime;

$agentLogger->logToolExecution(
    toolName: $toolName,
    input: $input,
    result: $result,
    success: $result['success'] ?? true,
    duration: $duration
);

// Log session summary at the end
$agentLogger->logSessionSummary(success: true, agentName: 'ProductionAgent');

// Get metrics
$metrics = $agentLogger->getMetrics();
echo "Session Stats:\n";
echo "- Iterations: {$metrics['total_iterations']}\n";
echo "- Tool Calls: {$metrics['total_tool_calls']}\n";
echo "- Total Tokens: " . ($metrics['total_input_tokens'] + $metrics['total_output_tokens']) . "\n";
echo "- Estimated Cost: \${$metrics['estimated_cost_usd']}\n";
echo "- Duration: {$metrics['duration']}s\n";
```

### Custom Metrics

```php
<?php

class ProductionMetrics
{
    private array $metrics = [];
    
    public function recordToolCall(string $tool, float $duration, bool $success): void
    {
        if (!isset($this->metrics[$tool])) {
            $this->metrics[$tool] = [
                'calls' => 0,
                'successes' => 0,
                'failures' => 0,
                'total_duration' => 0,
            ];
        }
        
        $this->metrics[$tool]['calls']++;
        $this->metrics[$tool][$success ? 'successes' : 'failures']++;
        $this->metrics[$tool]['total_duration'] += $duration;
    }
    
    public function getSummary(): array
    {
        $summary = [];
        foreach ($this->metrics as $tool => $stats) {
            $summary[$tool] = [
                'calls' => $stats['calls'],
                'success_rate' => $stats['calls'] > 0 
                    ? round($stats['successes'] / $stats['calls'] * 100, 2) 
                    : 0,
                'avg_duration' => $stats['calls'] > 0
                    ? round($stats['total_duration'] / $stats['calls'], 3)
                    : 0,
            ];
        }
        return $summary;
    }
}
```

## 🎯 Step 4: Complete Production Agent

Here's a complete production-ready agent with all patterns:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Helpers\{AgentHelpers, ErrorHandler, CircuitBreaker, AgentLogger};
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\{StreamHandler, RotatingFileHandler};

// ============================================================================
// 1. SETUP LOGGING
// ============================================================================

$logger = new Logger('production_agent');
$logger->pushHandler(new RotatingFileHandler('logs/agent.log', 0, Logger::DEBUG));
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$agentLogger = new AgentLogger($logger);

// ============================================================================
// 2. CREATE ERROR HANDLER
// ============================================================================

$errorHandler = new ErrorHandler($logger, maxRetries: 3, initialDelayMs: 1000);

// ============================================================================
// 3. CREATE CIRCUIT BREAKERS
// ============================================================================

$circuits = [
    'weather' => new CircuitBreaker('weather_api', 5, 60, 2, $logger),
    'database' => new CircuitBreaker('database', 3, 30, 2, $logger),
];

// ============================================================================
// 4. DEFINE TOOLS WITH ERROR HANDLING
// ============================================================================

function executeTool(
    string $name,
    array $input,
    ErrorHandler $errorHandler,
    array $circuits,
    Logger $logger
): array {
    return match($name) {
        'calculate' => $errorHandler->executeToolSafely(
            toolFn: function($input) {
                $expr = $input['expression'];
                if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
                    throw new Exception("Invalid expression");
                }
                return (string)eval("return {$expr};");
            },
            toolName: $name,
            input: $input
        ),
        
        'get_weather' => $errorHandler->executeToolSafely(
            toolFn: function($input) use ($circuits) {
                return $circuits['weather']->call(function() use ($input) {
                    // Simulate API call
                    if (rand(0, 10) > 8) {
                        throw new Exception("Weather API unavailable");
                    }
                    
                    return json_encode([
                        'location' => $input['location'],
                        'temperature' => rand(50, 85) . '°F',
                        'condition' => 'Sunny',
                    ]);
                });
            },
            toolName: $name,
            input: $input
        ),
        
        default => [
            'success' => false,
            'content' => "Unknown tool: {$name}",
            'is_error' => true,
        ]
    };
}

// ============================================================================
// 5. RUN PRODUCTION AGENT
// ============================================================================

$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);

$tools = [
    AgentHelpers::createTool(
        'calculate',
        'Perform mathematical calculations',
        ['expression' => ['type' => 'string']],
        ['expression']
    ),
    AgentHelpers::createTool(
        'get_weather',
        'Get current weather for a location',
        ['location' => ['type' => 'string']],
        ['location']
    ),
];

// Tool executor with all error handling
$toolExecutor = function(string $name, array $input) use (
    $errorHandler, $circuits, $logger, $agentLogger
) {
    $startTime = microtime(true);
    $result = executeTool($name, $input, $errorHandler, $circuits, $logger);
    $duration = microtime(true) - $startTime;
    
    // Log tool execution
    $agentLogger->logToolExecution(
        toolName: $name,
        input: $input,
        result: $result['content'],
        success: $result['success'],
        duration: $duration
    );
    
    return $result['content'];
};

// Run agent with comprehensive error handling
try {
    $logger->info('Starting production agent');
    
    $result = $errorHandler->executeWithRetry(
        fn: function() use ($client, $tools, $toolExecutor, $logger, $agentLogger) {
            return AgentHelpers::runAgentLoop(
                client: $client,
                messages: [['role' => 'user', 'content' => 'What is 25 * 17, and what is the weather in Boston?']],
                tools: $tools,
                toolExecutor: $toolExecutor,
                config: [
                    'max_iterations' => 10,
                    'logger' => $logger,
                ]
            );
        },
        context: 'Agent execution'
    );
    
    // Log session summary
    $agentLogger->logSessionSummary(success: $result['success'], agentName: 'ProductionAgent');
    
    // Display results
    if ($result['success']) {
        $answer = AgentHelpers::extractTextContent($result['response']);
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "Answer: {$answer}\n";
        echo str_repeat("=", 70) . "\n";
        
        // Display metrics
        $metrics = $agentLogger->getMetrics();
        echo "\nMetrics:\n";
        echo "- Iterations: {$metrics['total_iterations']}\n";
        echo "- Tool Calls: {$metrics['total_tool_calls']}\n";
        echo "- Tokens: {$metrics['total_input_tokens']} + {$metrics['total_output_tokens']}\n";
        echo "- Cost: \${$metrics['estimated_cost_usd']}\n";
        echo "- Duration: {$metrics['duration']}s\n";
        
        // Display circuit breaker stats
        echo "\nCircuit Breaker Stats:\n";
        foreach ($circuits as $name => $circuit) {
            $stats = $circuit->getStats();
            echo "- {$name}: {$stats['state']} (failures: {$stats['failure_count']})\n";
        }
    } else {
        echo "Error: {$result['error']}\n";
    }
    
} catch (Exception $e) {
    $logger->error('Production agent failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    echo "Fatal error: {$e->getMessage()}\n";
}
```

## ✅ Production Checklist

Before deploying your agent:

- [ ] All errors caught and handled
- [ ] Retry logic implemented for transient failures
- [ ] Circuit breakers for external dependencies
- [ ] Structured logging configured
- [ ] Iteration limits set appropriately
- [ ] Token usage monitored
- [ ] Cost tracking implemented
- [ ] Tool input validation
- [ ] Timeout configuration
- [ ] Rate limiting (if needed)
- [ ] Graceful degradation strategies
- [ ] Error scenarios tested
- [ ] Monitoring and alerts configured
- [ ] Documentation for operators

## 📊 Monitoring in Production

### Key Metrics to Track

1. **Success Rate**
   - % of sessions completed successfully
   - Target: >95%

2. **Average Iterations**
   - Iterations per task
   - Track trends over time

3. **Tool Usage**
   - Which tools used most
   - Success rate per tool

4. **Token Usage**
   - Input/output tokens per session
   - Cost per session

5. **Error Rates**
   - API errors
   - Tool errors
   - Circuit breaker trips

6. **Performance**
   - Session duration
   - Tool execution time
   - API response time

### Setting Up Alerts

```php
<?php

// Alert on high error rate
if ($metrics['total_errors'] / $metrics['total_iterations'] > 0.1) {
    $logger->alert('High error rate detected', [
        'error_rate' => $metrics['total_errors'] / $metrics['total_iterations'],
        'threshold' => 0.1,
    ]);
    // Send alert to monitoring system
}

// Alert on circuit breaker open
foreach ($circuits as $name => $circuit) {
    if ($circuit->getState() === 'open') {
        $logger->alert('Circuit breaker opened', [
            'circuit' => $name,
            'stats' => $circuit->getStats(),
        ]);
        // Send alert
    }
}

// Alert on high costs
if ($metrics['estimated_cost_usd'] > 1.0) {
    $logger->warning('High cost session', [
        'cost' => $metrics['estimated_cost_usd'],
        'threshold' => 1.0,
    ]);
}
```

## 💰 Cost Management

### Budget Tracking

```php
<?php

class BudgetTracker
{
    private float $dailySpent = 0;
    private float $dailyLimit;
    
    public function __construct(float $dailyLimit)
    {
        $this->dailyLimit = $dailyLimit;
    }
    
    public function checkBudget(float $cost): bool
    {
        return ($this->dailySpent + $cost) <= $this->dailyLimit;
    }
    
    public function recordCost(float $cost): void
    {
        $this->dailySpent += $cost;
    }
    
    public function getRemainingBudget(): float
    {
        return max(0, $this->dailyLimit - $this->dailySpent);
    }
}

// Usage
$budget = new BudgetTracker(dailyLimit: 10.0);  // $10/day

$estimatedCost = AgentHelpers::estimateTokens($messages) / 1_000_000 * 3.0;

if (!$budget->checkBudget($estimatedCost)) {
    throw new Exception("Daily budget exceeded");
}

// Run agent...

$actualCost = $metrics['estimated_cost_usd'];
$budget->recordCost($actualCost);
```

## ✅ Checkpoint

Before moving on, make sure you understand:

- [ ] Common production failure modes
- [ ] How to use ErrorHandler for retry logic
- [ ] When and how to use circuit breakers
- [ ] Structured logging with AgentLogger
- [ ] Cost monitoring and budgets
- [ ] Key metrics to track
- [ ] Production deployment checklist

## 🚀 Congratulations!

You've completed the Getting Started tutorial series! You now know how to:

✅ Build basic agents with tools  
✅ Implement ReAct loops for multi-step tasks  
✅ Create multi-tool agents with intelligent selection  
✅ Deploy production-ready agents with error handling, logging, and monitoring

### Next Steps

- **[Design Patterns Tutorial](./06-Design-Patterns.md)** - Learn Factory, Builder, and Observer patterns
- **[Best Practices Guide](../../BestPractices.md)** - Deep dive into production patterns
- **[Specialized Agents](../)** - Learn about RAG, hierarchical, and autonomous agents
- **[Advanced Patterns](../../)** - Explore tree of thoughts, debate systems, and more
- **[Build Your Own](../../../examples/)** - Study complete examples

## 💡 Key Takeaways

1. **Always handle errors** - Production systems fail
2. **Use circuit breakers** - Prevent cascading failures
3. **Log everything** - You can't fix what you can't see
4. **Monitor metrics** - Track success, cost, performance
5. **Set budgets** - Control costs proactively
6. **Test failure scenarios** - Don't wait for production
7. **Start simple** - Add complexity as needed

## 📚 Further Reading

- [Design Patterns Guide](../../DesignPatterns.md) - Factory, Builder, Observer, and more
- [Best Practices Guide](../../BestPractices.md)
- [Error Handling Documentation](../../ErrorHandling.md)
- [Production Patterns Example](../../../examples/production_patterns_example.php)
- [Observability Guide](../../Observability.md)

---

## 🎨 Bonus: Design Patterns for Production Code

The patterns you've learned (error handling, circuit breakers, logging) are foundational. For truly production-ready code, also consider these **design patterns**:

### Factory Pattern: Consistent Agent Creation

Instead of creating agents directly, use `AgentFactory` for centralized creation:

```php
use ClaudeAgents\Factory\AgentFactory;

$factory = new AgentFactory($client, $logger, $eventDispatcher);

// All agents get consistent configuration
$agent = $factory->createReactAgent([
    'name' => 'production_agent',
    'max_iterations' => 15,
]);
```

**Benefits:** Consistency, testability, reduced coupling

### Builder Pattern: Type-Safe Configuration

Use `AgentConfigBuilder` for validated, fluent configuration:

```php
use ClaudeAgents\Builder\AgentConfigBuilder;

$config = AgentConfigBuilder::create()
    ->name('customer_support')
    ->model('claude-sonnet-4-20250514')
    ->maxIterations(10)
    ->temperature(0.7)
    ->build();

$agent = $factory->create('dialog', $config->toArray());
```

**Benefits:** Type safety, IDE autocomplete, validation

### Observer Pattern: Event-Driven Monitoring

Use `EventDispatcher` for decoupled monitoring:

```php
use ClaudeAgents\Events\EventDispatcher;
use ClaudeAgents\Events\{AgentStartedEvent, AgentCompletedEvent};

$dispatcher = new EventDispatcher();

$dispatcher->listen(AgentCompletedEvent::class, function($event) use ($metrics) {
    $metrics->record($event->getDuration());
});

$factory = new AgentFactory($client, $logger, $dispatcher);
```

**Benefits:** Decoupling, extensibility, real-time monitoring

### Combined: Production-Ready Setup

```php
// 1. Event dispatcher for monitoring
$dispatcher = new EventDispatcher();
$dispatcher->listen(AgentCompletedEvent::class, [$metrics, 'record']);
$dispatcher->listen(AgentFailedEvent::class, [$alerts, 'send']);

// 2. Factory with dispatcher
$factory = new AgentFactory($client, $logger, $dispatcher);

// 3. Builder for configuration
$config = AgentConfigBuilder::create()
    ->name('production_agent')
    ->model('claude-opus-4-20250514')
    ->maxIterations(20)
    ->temperature(0.7)
    ->build();

// 4. Create with error handling
$errorHandler = ErrorHandler::create()->maxRetries(3);
$agent = $factory->createReactAgent($config->toArray());

// Result: Production-ready with all patterns!
```

**Learn More:**
- [Design Patterns Tutorial](./06-Design-Patterns.md)
- [Design Patterns Guide](../../DesignPatterns.md)
- [Complete Demo](../../../examples/design_patterns_demo.php)

---

**You're now ready to build production AI agents!** 🚀

