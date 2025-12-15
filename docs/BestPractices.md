# Agent Development Best Practices

Comprehensive guide to building production-ready AI agents with the Claude PHP Agent Framework.

## 📚 Table of Contents

1. [Agent Creation Patterns](#agent-creation-patterns)
2. [Configuration Patterns](#configuration-patterns)
3. [Event-Driven Monitoring](#event-driven-monitoring)
4. [Tool Design](#tool-design)
5. [ReAct Loop Patterns](#react-loop-patterns)
6. [Error Handling](#error-handling)
7. [Performance Optimization](#performance-optimization)
8. [Security](#security)
9. [Testing](#testing)
10. [Monitoring & Observability](#monitoring--observability)
11. [Cost Management](#cost-management)

---

## 🏭 Agent Creation Patterns

### Always Use Factory for Consistency

The Factory Pattern ensures all agents are created with consistent configuration and automatic dependency injection.

**❌ Bad:**
```php
// Inconsistent creation across your codebase
$agent1 = new ReactAgent($client);
$agent2 = new ReactAgent($client, ['name' => 'agent2']);
$agent3 = new ReactAgent($client, ['name' => 'agent3'], $logger);

// Problems:
// - Inconsistent logger injection
// - Each developer does it differently
// - Hard to change defaults
// - Difficult to test
```

**✅ Good:**
```php
use ClaudeAgents\Factory\AgentFactory;

$factory = new AgentFactory($client, $logger);

// Consistent creation, logger always injected
$agent1 = $factory->create('react');
$agent2 = $factory->create('react', ['name' => 'agent2']);
$agent3 = $factory->createReactAgent(['name' => 'agent3']);
```

**Why:** Factory Pattern provides:
- **Consistency**: All agents get same defaults
- **DRY**: No duplication of construction logic
- **Testability**: Easy to mock for testing
- **Maintainability**: Change defaults in one place

### Use Specific Factory Methods

```php
// Available factory methods
$reactAgent = $factory->createReactAgent(['name' => 'react1']);
$cotAgent = $factory->createChainOfThoughtAgent(['mode' => 'few_shot']);
$ragAgent = $factory->createRAGAgent(['vector_store' => $store]);
$hierarchicalAgent = $factory->createHierarchicalAgent(['workers' => 3]);
$planExecuteAgent = $factory->createPlanExecuteAgent(['max_steps' => 10]);
```

### Factory in Multi-Agent Systems

**❌ Bad:**
```php
// Creating multiple agents inconsistently
$manager = new HierarchicalAgent($client, $options);
$worker1 = new WorkerAgent($client, $otherOptions);  // Forgot logger!
$worker2 = new WorkerAgent($client, $otherOptions, $logger);
```

**✅ Good:**
```php
$factory = new AgentFactory($client, $logger);

// All workers get consistent configuration
$manager = $factory->createHierarchicalAgent(['name' => 'manager']);
$worker1 = $factory->createWorkerAgent(['name' => 'worker1']);
$worker2 = $factory->createWorkerAgent(['name' => 'worker2']);
```

---

## ⚙️ Configuration Patterns

### Use Builder for Complex Configurations

When your agent configuration has more than 3 parameters, use the Builder Pattern for type safety and readability.

**❌ Bad:**
```php
// Array configuration - no type safety
$config = [
    'model' => 'claude-opus-4',
    'max_tokens' => 4096,
    'max_iterations' => 10,
    'system' => 'You are helpful',
    'thinking' => ['type' => 'enabled', 'budget_tokens' => 10000],
    'temperature' => 0.7,
    'timeout' => 300,
    'custom' => ['caching' => true],
];

// Problems:
// - Typos not caught until runtime (e.g., 'max_token' vs 'max_tokens')
// - No IDE autocomplete
// - Easy to forget required fields
// - No validation
```

**✅ Good:**
```php
use ClaudeAgents\Config\AgentConfigBuilder;

$config = AgentConfigBuilder::create()
    ->withModel('claude-opus-4')
    ->withMaxTokens(4096)
    ->withMaxIterations(10)
    ->withSystemPrompt('You are helpful')
    ->withThinking(10000)
    ->withTemperature(0.7)
    ->withTimeout(300)
    ->withCustomOption('caching', true)
    ->build();
```

**Why:** Builder Pattern provides:
- **Type Safety**: Typos caught at compile time
- **IDE Support**: Full autocomplete
- **Readability**: Self-documenting code
- **Validation**: Early error detection

### Builder with Tools

```php
use ClaudeAgents\Tools\Tool;

$searchTool = Tool::create('search')
    ->description('Search for information')
    ->stringParam('query', 'Search query')
    ->handler(fn($input) => searchAPI($input['query']));

$config = AgentConfigBuilder::create()
    ->withModel('claude-opus-4')
    ->withMaxTokens(4096)
    ->addTool($searchTool)  // Add individual tool
    ->withTools([$tool1, $tool2])  // Or set all at once
    ->build();
```

### Reusable Configuration Templates

```php
// Create base configuration
$baseConfig = AgentConfigBuilder::create()
    ->withModel('claude-opus-4')
    ->withMaxTokens(4096)
    ->withTimeout(300);

// Extend for specific use cases
$productionConfig = clone $baseConfig;
$productionConfig
    ->withThinking(10000)
    ->withMaxIterations(15)
    ->withCustomOption('caching', true);

$testConfig = clone $baseConfig;
$testConfig
    ->withMaxIterations(5)
    ->withCustomOption('mock_mode', true);
```

### When to Use Builder vs Arrays

**Use Builder when:**
- Configuration has >3 parameters
- Type safety is important
- Code will be maintained by multiple developers
- IDE autocomplete would help
- Validation is needed

**Use Arrays when:**
- Configuration is very simple (<3 parameters)
- Rapid prototyping
- Dynamic configuration from external sources

---

## 📡 Event-Driven Monitoring

### Subscribe to Agent Lifecycle Events

The Observer Pattern (Event System) allows you to monitor agents without coupling your monitoring code to the agent implementation.

**❌ Bad:**
```php
// Monitoring tightly coupled to agent
class MonitoredAgent extends ReactAgent {
    public function run($task) {
        $this->logger->info("Agent starting...");
        $startTime = microtime(true);
        
        $result = parent::run($task);
        
        $duration = microtime(true) - $startTime;
        $this->logger->info("Agent completed", ['duration' => $duration]);
        
        return $result;
    }
}

// Problems:
// - Must modify agent for each monitor
// - Can't add monitoring without inheritance
// - Tight coupling
// - Hard to add multiple monitors
```

**✅ Good:**
```php
use ClaudeAgents\Events\EventDispatcher;
use ClaudeAgents\Events\{AgentStartedEvent, AgentCompletedEvent, AgentFailedEvent};

$dispatcher = new EventDispatcher();

// Set up event listeners (decoupled from agents)
$dispatcher->listen(AgentStartedEvent::class, function($event) use ($logger) {
    $logger->info("Agent started", [
        'agent' => $event->getAgentName(),
        'task' => $event->getTask(),
        'timestamp' => $event->getTimestamp(),
    ]);
});

$dispatcher->listen(AgentCompletedEvent::class, function($event) use ($logger) {
    $logger->info("Agent completed", [
        'agent' => $event->getAgentName(),
        'duration' => $event->getDuration(),
        'iterations' => $event->getIterations(),
    ]);
});

$dispatcher->listen(AgentFailedEvent::class, function($event) use ($logger) {
    $logger->error("Agent failed", [
        'agent' => $event->getAgentName(),
        'error' => $event->getError(),
        'duration' => $event->getDuration(),
    ]);
});

// Now all agents automatically emit events
// No need to modify agent code
```

**Why:** Event System provides:
- **Decoupling**: Agents don't know about monitors
- **Extensibility**: Add monitors without modifying agents
- **Multiple Listeners**: Many monitors per event
- **Runtime Configuration**: Add/remove listeners dynamically

### Build Custom Event Listeners

```php
class MetricsCollector {
    private array $metrics = [
        'total_runs' => 0,
        'successful_runs' => 0,
        'failed_runs' => 0,
        'total_duration' => 0.0,
        'total_iterations' => 0,
    ];
    
    public function onAgentCompleted(AgentCompletedEvent $event): void {
        $this->metrics['total_runs']++;
        $this->metrics['successful_runs']++;
        $this->metrics['total_duration'] += $event->getDuration();
        $this->metrics['total_iterations'] += $event->getIterations();
    }
    
    public function onAgentFailed(AgentFailedEvent $event): void {
        $this->metrics['total_runs']++;
        $this->metrics['failed_runs']++;
    }
    
    public function getMetrics(): array {
        return [
            ...$this->metrics,
            'success_rate' => $this->metrics['total_runs'] > 0
                ? $this->metrics['successful_runs'] / $this->metrics['total_runs']
                : 0,
            'avg_duration' => $this->metrics['successful_runs'] > 0
                ? $this->metrics['total_duration'] / $this->metrics['successful_runs']
                : 0,
        ];
    }
}

$metrics = new MetricsCollector();
$dispatcher->listen(AgentCompletedEvent::class, [$metrics, 'onAgentCompleted']);
$dispatcher->listen(AgentFailedEvent::class, [$metrics, 'onAgentFailed']);

// Run agents...

// Get metrics
$stats = $metrics->getMetrics();
echo "Success rate: " . ($stats['success_rate'] * 100) . "%\n";
echo "Average duration: " . $stats['avg_duration'] . "s\n";
```

### Event-Driven Alerting

```php
$dispatcher->listen(AgentFailedEvent::class, function($event) use ($alerting) {
    // Alert on critical failures
    if ($event->getException() instanceof CriticalError) {
        $alerting->sendPagerDuty([
            'severity' => 'critical',
            'message' => "Agent {$event->getAgentName()} failed critically",
            'error' => $event->getError(),
        ]);
    }
    
    // Alert on repeated failures
    static $failures = [];
    $agent = $event->getAgentName();
    $failures[$agent] = ($failures[$agent] ?? 0) + 1;
    
    if ($failures[$agent] >= 3) {
        $alerting->sendSlack("Agent {$agent} has failed 3 times in a row!");
    }
});
```

### Integration with Existing Observability

```php
// Send to DataDog
$dispatcher->listen(AgentCompletedEvent::class, function($event) use ($datadog) {
    $datadog->increment('agent.completed', 1, [
        'agent' => $event->getAgentName(),
    ]);
    
    $datadog->histogram('agent.duration', $event->getDuration(), [
        'agent' => $event->getAgentName(),
    ]);
});

// Send to Prometheus
$dispatcher->listen(AgentCompletedEvent::class, function($event) use ($prometheus) {
    $prometheus->getCounter('agent_runs_total', 'Total agent runs')
        ->inc(['agent' => $event->getAgentName()]);
    
    $prometheus->getHistogram('agent_duration_seconds', 'Agent duration')
        ->observe($event->getDuration(), ['agent' => $event->getAgentName()]);
});
```

---

## 🛠️ Tool Design

### Always Provide Clear Descriptions

**❌ Bad:**
```php
Tool::create('query')
    ->description('Query the database')
```

**✅ Good:**
```php
Tool::create('query_customer_database')
    ->description(
        'Query the customer database for user information. ' .
        'Returns customer records including name, email, and order history. ' .
        'Use this when you need to look up customer details or order information.'
    )
```

**Why:** Clear descriptions help Claude understand exactly when and how to use each tool.

### Use Descriptive Parameter Names

**❌ Bad:**
```php
->stringParam('q', 'The query')
->stringParam('f', 'Format')
```

**✅ Good:**
```php
->stringParam('search_query', 'The SQL query to execute (SELECT statements only)')
->stringParam('output_format', 'Result format: "json", "csv", or "table"')
```

### Validate All Tool Input

**❌ Bad:**
```php
->handler(function (array $input): string {
    return eval("return {$input['expression']};");
});
```

**✅ Good:**
```php
->handler(function (array $input): string {
    $expression = $input['expression'];
    
    // Validate input
    if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expression)) {
        return "Error: Invalid expression - only numbers and operators allowed";
    }
    
    // Additional safety checks
    if (strlen($expression) > 1000) {
        return "Error: Expression too long";
    }
    
    // Use safe evaluation (or proper parser library)
    try {
        $parser = new MathParser();
        $result = $parser->evaluate($expression);
        return (string)$result;
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
});
```

### Return Structured Error Messages

**❌ Bad:**
```php
return "Failed";
```

**✅ Good:**
```php
return json_encode([
    'success' => false,
    'error' => 'Database connection failed',
    'error_code' => 'DB_CONNECTION_ERROR',
    'suggestion' => 'Check database credentials and try again'
]);
```

### Keep Tools Focused

**❌ Bad: One tool does everything**
```php
Tool::create('database_operations')
    ->description('Read, write, update, or delete from database')
```

**✅ Good: Separate tools for different operations**
```php
Tool::create('query_database')
    ->description('Read data from database (SELECT only)');

Tool::create('update_database')
    ->description('Update existing records');

Tool::create('insert_database')
    ->description('Insert new records');
```

**Why:** Focused tools are easier to understand, test, and secure.

---

## 🔄 ReAct Loop Patterns

### Always Set Maximum Iterations

**❌ Bad:**
```php
while (true) {
    // Potential infinite loop!
}
```

**✅ Good:**
```php
$config = AgentConfig::fromArray([
    'max_iterations' => 10,  // Clear limit
]);
```

**Recommended Iteration Limits:**

| Task Complexity | Suggested Limit | Example |
|----------------|-----------------|---------|
| Simple         | 3-5             | Single calculation |
| Medium         | 5-10            | Multi-step calculation |
| Complex        | 10-15           | Research + analysis |
| Very Complex   | 15-25           | Multi-stage workflows |

### Preserve Full Conversation History

**❌ Bad:**
```php
$messages = [
    ['role' => 'user', 'content' => $toolResults]  // Lost context!
];
```

**✅ Good:**
```php
$messages[] = ['role' => 'assistant', 'content' => $response->content];
$messages[] = ['role' => 'user', 'content' => $toolResults];
// Full history maintained
```

**Why:** Agents need context from previous iterations to make informed decisions.

### Handle All Stop Reasons

**❌ Bad:**
```php
if ($response->stop_reason === 'end_turn') {
    break;
}
// What about tool_use, max_tokens, etc.?
```

**✅ Good:**
```php
switch ($response->stop_reason) {
    case 'end_turn':
        // Task complete
        break 2;
    
    case 'tool_use':
        // Execute tools
        break;
    
    case 'max_tokens':
        // Increase max_tokens or handle truncation
        $this->logger->warning('Response truncated - increase max_tokens');
        break 2;
    
    default:
        $this->logger->error("Unexpected stop_reason: {$response->stop_reason}");
        break 2;
}
```

### Use Helper Functions

**❌ Bad: Reinvent the wheel**
```php
// Manually extract tool uses every time
foreach ($response->content as $block) {
    if ($block['type'] === 'tool_use') {
        // ... complex extraction logic
    }
}
```

**✅ Good: Use provided helpers**
```php
use ClaudeAgents\Helpers\AgentHelpers;

$toolUses = AgentHelpers::extractToolUses($response);
$toolResult = AgentHelpers::formatToolResult($toolUseId, $result, $isError);
```

---

## 🚨 Error Handling

### Use Comprehensive Try-Catch Blocks

**❌ Bad:**
```php
$response = $client->messages()->create($params);
```

**✅ Good:**
```php
use ClaudeAgents\Helpers\ErrorHandler;
use ClaudePhp\Exceptions\RateLimitError;
use ClaudePhp\Exceptions\APIConnectionError;
use ClaudePhp\Exceptions\AuthenticationError;

$errorHandler = new ErrorHandler($logger, maxRetries: 3);

try {
    $response = $errorHandler->executeWithRetry(
        fn: fn() => $client->messages()->create($params),
        context: 'Agent iteration'
    );
} catch (RateLimitError $e) {
    // Handle rate limiting specifically
    $retryAfter = $e->response->getHeaderLine('retry-after');
    sleep($retryAfter);
    // Retry...
} catch (AuthenticationError $e) {
    // Don't retry auth errors
    $logger->critical('Authentication failed');
    throw $e;
} catch (APIConnectionError $e) {
    // Network issues - retry with backoff
    $logger->error('Connection failed', ['error' => $e->getMessage()]);
    // Handled by ErrorHandler retry logic
} catch (Exception $e) {
    $logger->error('Unexpected error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    throw $e;
}
```

### Implement Circuit Breakers for External Services

**✅ Good:**
```php
use ClaudeAgents\Helpers\CircuitBreaker;

$circuitBreaker = new CircuitBreaker(
    name: 'external_api',
    failureThreshold: 5,
    timeoutSeconds: 60,
    logger: $logger
);

try {
    $result = $circuitBreaker->call(function() {
        return $this->externalApiClient->request();
    });
} catch (CircuitBreakerOpenException $e) {
    // Circuit is open - service unavailable
    return "External service temporarily unavailable. Please try again later.";
}
```

### Report Tool Errors to Claude

**✅ Good:**
```php
$toolResults[] = [
    'type' => 'tool_result',
    'tool_use_id' => $toolUse['id'],
    'content' => "Error: Database connection failed. The customer lookup could not be completed.",
    'is_error' => true  // Important: tells Claude this is an error
];
```

**Why:** Claude can adapt its strategy when it knows a tool failed.

---

## ⚡ Performance Optimization

### Use Prompt Caching

**✅ Good:**
```php
$systemPrompt = [
    [
        'type' => 'text',
        'text' => $longSystemInstructions,
        'cache_control' => ['type' => 'ephemeral']  // Cache this
    ]
];
```

**Savings:** Up to 90% cost reduction on cached tokens.

### Trim Conversation History

**✅ Good:**
```php
use ClaudeAgents\Helpers\AgentHelpers;

// Keep only recent messages
if (count($messages) > 20) {
    $messages = AgentHelpers::manageConversationHistory($messages, maxMessages: 10);
}
```

### Optimize Token Usage

**✅ Best Practices:**

1. **Concise system prompts** - Be clear but brief
2. **Efficient tool descriptions** - Essential info only
3. **Lower max_tokens for simple tasks**
4. **Use streaming for long responses**

```php
$config = AgentConfig::fromArray([
    'max_tokens' => 512,  // Reduce for simple tasks
    'model' => 'claude-sonnet-4-20250514',  // Choose appropriate model
]);
```

### Batch Operations When Possible

**❌ Bad: Sequential operations**
```php
foreach ($items as $item) {
    $agent->run("Process {$item}");  // N API calls
}
```

**✅ Good: Batch when possible**
```php
$agent->run("Process these items: " . implode(', ', $items));  // 1 API call
```

---

## 🔒 Security

### Never Use eval() with User Input

**❌ Dangerous:**
```php
$result = eval("return {$userInput};");
```

**✅ Safe:**
```php
use MathParser\StdMathParser;

$parser = new StdMathParser();
$result = $parser->parse($userInput)->evaluate();
```

### Validate and Sanitize All Input

**✅ Good:**
```php
function validateSQLQuery(string $query): bool {
    // Only allow SELECT statements
    if (!preg_match('/^SELECT/i', $query)) {
        throw new SecurityException('Only SELECT queries allowed');
    }
    
    // Prevent dangerous keywords
    $dangerous = ['DROP', 'DELETE', 'UPDATE', 'INSERT', 'ALTER', 'EXEC'];
    foreach ($dangerous as $keyword) {
        if (stripos($query, $keyword) !== false) {
            throw new SecurityException("Keyword '{$keyword}' not allowed");
        }
    }
    
    return true;
}
```

### Use Environment Variables for Sensitive Data

**❌ Bad:**
```php
$apiKey = 'sk-ant-1234567890';  // Hardcoded!
```

**✅ Good:**
```php
$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? throw new Exception('API key not set');
```

### Implement Rate Limiting

**✅ Good:**
```php
use ClaudeAgents\Helpers\ErrorHandler;

$rateLimiter = ErrorHandler::createRateLimiter(minIntervalMs: 100);

// Before each request
$rateLimiter();
$response = $client->messages()->create($params);
```

---

## 🧪 Testing

### Test Tool Handlers Independently

**✅ Good:**
```php
class CalculatorToolTest extends TestCase
{
    public function testBasicCalculation(): void
    {
        $tool = new CalculatorTool();
        $result = $tool->execute(['expression' => '2 + 2']);
        
        $this->assertEquals('4', $result);
    }
    
    public function testInvalidExpression(): void
    {
        $tool = new CalculatorTool();
        $result = $tool->execute(['expression' => 'DELETE FROM users']);
        
        $this->assertStringContainsString('Error', $result);
    }
}
```

### Mock External Dependencies

**✅ Good:**
```php
class AgentIntegrationTest extends TestCase
{
    public function testAgentWithMockedAPI(): void
    {
        $mockClient = $this->createMock(ClaudePhp::class);
        $mockClient->method('messages')
            ->willReturn($this->createMockMessages());
        
        $agent = Agent::create($mockClient);
        $result = $agent->run('Test query');
        
        $this->assertNotEmpty($result);
    }
}
```

### Test Error Scenarios

**✅ Good:**
```php
public function testHandlesToolFailure(): void
{
    $agent = Agent::create($client)
        ->withTool($this->createFailingTool());
    
    $result = $agent->run('Use the failing tool');
    
    // Agent should handle the failure gracefully
    $this->assertNotEmpty($result);
    $this->assertStringNotContainsString('Fatal error', $result);
}
```

---

## 📊 Monitoring & Observability

### Use Structured Logging

**✅ Good:**
```php
use ClaudeAgents\Helpers\AgentLogger;

$agentLogger = new AgentLogger($logger);

$agentLogger->logIteration($iteration, $response, $agentName);
$agentLogger->logToolExecution($toolName, $input, $result, $success, $duration);
$agentLogger->logSessionSummary($success, $agentName);

$metrics = $agentLogger->getMetrics();
```

### Track Key Metrics

**Essential Metrics:**

1. **Success Rate** - % of tasks completed
2. **Average Iterations** - Iterations per task
3. **Token Usage** - Input/output tokens
4. **Tool Usage** - Which tools used most
5. **Error Rate** - % of failed requests
6. **Latency** - Time per request
7. **Cost** - Estimated API costs

### Add Callbacks for Debugging

**✅ Good:**
```php
$agent = Agent::create($client)
    ->onIteration(function ($iter, $resp, $ctx) {
        $this->logger->debug("Iteration {$iter}", [
            'stop_reason' => $resp->stop_reason,
            'tokens' => [
                'input' => $resp->usage->input_tokens,
                'output' => $resp->usage->output_tokens,
            ],
        ]);
    })
    ->onToolCall(function ($tool, $input, $result) {
        $this->logger->info("Tool executed", [
            'tool' => $tool->name(),
            'input_size' => strlen(json_encode($input)),
            'result_size' => strlen($result),
        ]);
    })
    ->onError(function ($error, $context) {
        $this->logger->error("Agent error", [
            'error' => $error->getMessage(),
            'context' => $context,
        ]);
    });
```

---

## 💰 Cost Management

### Estimate Costs Before Running

**✅ Good:**
```php
use ClaudeAgents\Helpers\AgentHelpers;

$estimatedTokens = AgentHelpers::estimateTokens($messages);
$toolTokens = 200 * count($tools);  // Rough estimate
$totalEstimate = $estimatedTokens + $toolTokens;

$estimatedCost = ($totalEstimate / 1_000_000) * 3.0;  // Sonnet input rate

if ($estimatedCost > 0.10) {
    $logger->warning("High estimated cost: \${$estimatedCost}");
}
```

### Set Budget Limits

**✅ Good:**
```php
class BudgetLimitExceededException extends Exception {}

class BudgetTracker
{
    private float $spent = 0.0;
    private float $limit;
    
    public function __construct(float $limit) {
        $this->limit = $limit;
    }
    
    public function record(int $inputTokens, int $outputTokens): void {
        $cost = ($inputTokens / 1_000_000) * 3.0 + 
                ($outputTokens / 1_000_000) * 15.0;
        
        $this->spent += $cost;
        
        if ($this->spent > $this->limit) {
            throw new BudgetLimitExceededException(
                "Budget limit of \${$this->limit} exceeded"
            );
        }
    }
}
```

### Use Appropriate Models

**Model Selection:**

- **Claude 3.5 Sonnet**: Best balance of capability and cost (default)
- **Claude 3 Haiku**: Fast and cheap for simple tasks
- **Claude 3 Opus**: Highest capability for complex tasks

```php
// For simple classification
$config = AgentConfig::fromArray([
    'model' => 'claude-3-haiku-20240307',  // Cheaper
]);

// For complex research
$config = AgentConfig::fromArray([
    'model' => 'claude-opus-4-20250514',  // More capable
]);
```

---

## 📋 Quick Reference Checklist

### Before Production Deployment

- [ ] All tool inputs validated
- [ ] Error handling implemented for all API calls
- [ ] Retry logic with exponential backoff
- [ ] Circuit breakers for external services
- [ ] Iteration limits set appropriately
- [ ] Logging configured and tested
- [ ] Token usage monitored
- [ ] Cost estimates calculated
- [ ] Rate limiting implemented
- [ ] Security review completed
- [ ] Tests written and passing
- [ ] Monitoring and alerts configured
- [ ] Documentation updated

---

## 📚 Additional Resources

- [Getting Started Tutorials](./tutorials/getting-started/)
- [Agent Selection Guide](./agent-selection-guide.md)
- [Tools Documentation](./Tools.md)
- [Example Code](../examples/)
- [API Reference](./contracts.md)

---

*Last Updated: December 2024*

