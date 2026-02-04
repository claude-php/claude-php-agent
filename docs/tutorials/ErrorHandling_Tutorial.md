# Error Handling Service Tutorial

This comprehensive tutorial guides you through using the Error Handling Service to create robust, user-friendly applications with the Claude PHP Agent Framework.

## Table of Contents

1. [Tutorial 1: Basic Error Handling](#tutorial-1-basic-error-handling)
2. [Tutorial 2: Custom Error Patterns](#tutorial-2-custom-error-patterns)
3. [Tutorial 3: Integration with Agents](#tutorial-3-integration-with-agents)
4. [Tutorial 4: Logging and Debugging](#tutorial-4-logging-and-debugging)
5. [Tutorial 5: Production Patterns](#tutorial-5-production-patterns)
6. [Tutorial 6: Testing Strategies](#tutorial-6-testing-strategies)

---

## Tutorial 1: Basic Error Handling

**Estimated Time**: 15 minutes  
**Prerequisites**: Basic PHP knowledge, Claude API key

### Learning Objectives

- Get the ErrorHandlingService from ServiceManager
- Convert technical errors to user-friendly messages
- Handle common API errors gracefully

### Step 1: Setup

First, ensure you have the framework installed and your API key configured:

```bash
composer require claude-php/agent
```

Create a `.env` file:
```
ANTHROPIC_API_KEY=your_api_key_here
```

### Step 2: Basic Error Conversion

Create `basic-error-handling.php`:

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;

// Get the error handling service
$errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);

// Test with an invalid API key to trigger authentication error
$client = new ClaudePhp(apiKey: 'invalid_key_12345');

try {
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4',
        'max_tokens' => 100,
        'messages' => [
            ['role' => 'user', 'content' => 'Hello!'],
        ],
    ]);

    echo "Success: " . $response->content[0]->text . PHP_EOL;

} catch (\Throwable $e) {
    // Technical error (for developers)
    echo "Technical Error: " . get_class($e) . PHP_EOL;
    echo "  Message: " . $e->getMessage() . PHP_EOL;
    
    echo PHP_EOL;
    
    // User-friendly error (for end users)
    $userMessage = $errorService->convertToUserFriendly($e);
    echo "User-Friendly Message: " . $userMessage . PHP_EOL;
}
```

**Expected Output**:
```
Technical Error: ClaudePhp\Exceptions\AuthenticationError
  Message: Invalid API key

User-Friendly Message: Authentication failed. Please check your API key.
```

### Step 3: Handle Multiple Error Types

Extend the example to handle various errors:

```php
<?php

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;

$errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);

function testErrorHandling(ClaudePhp $client, string $description): void
{
    global $errorService;
    
    echo "\nTesting: {$description}\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        $response = $client->messages()->create([
            'model' => 'claude-sonnet-4',
            'max_tokens' => 100,
            'messages' => [
                ['role' => 'user', 'content' => 'Test'],
            ],
        ]);
        
        echo "✅ Success!\n";
        
    } catch (\Throwable $e) {
        echo "❌ Error Type: " . basename(str_replace('\\', '/', get_class($e))) . "\n";
        echo "📝 User Message: " . $errorService->convertToUserFriendly($e) . "\n";
    }
}

// Test 1: Invalid API Key
$invalidClient = new ClaudePhp(apiKey: 'invalid_key');
testErrorHandling($invalidClient, 'Invalid API Key');

// Test 2: Valid API Key (if available)
if ($apiKey = getenv('ANTHROPIC_API_KEY')) {
    $validClient = new ClaudePhp(apiKey: $apiKey);
    testErrorHandling($validClient, 'Valid API Key');
}
```

### Step 4: Get Detailed Error Information

Use `getErrorDetails()` for debugging:

```php
<?php

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;

$errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
$client = new ClaudePhp(apiKey: 'invalid_key');

try {
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4',
        'max_tokens' => 100,
        'messages' => [
            ['role' => 'user', 'content' => 'Test'],
        ],
    ]);
} catch (\Throwable $e) {
    // Get detailed information
    $details = $errorService->getErrorDetails($e);
    
    echo "Detailed Error Information:\n";
    echo json_encode($details, JSON_PRETTY_PRINT) . "\n";
    
    // Access specific fields
    echo "\nKey Information:\n";
    echo "- Exception: {$details['exception_class']}\n";
    echo "- User Message: {$details['user_friendly_message']}\n";
    
    if (isset($details['status_code'])) {
        echo "- HTTP Status: {$details['status_code']}\n";
    }
    
    if (isset($details['suggested_action'])) {
        echo "- Suggested Action: {$details['suggested_action']}\n";
    }
}
```

### Summary

You've learned how to:
- ✅ Get the ErrorHandlingService from ServiceManager
- ✅ Convert technical errors to user-friendly messages
- ✅ Extract detailed error information for debugging
- ✅ Handle multiple error types with a single service

**Next**: Learn how to create custom error patterns for your application-specific errors.

---

## Tutorial 2: Custom Error Patterns

**Estimated Time**: 20 minutes  
**Prerequisites**: Completed Tutorial 1

### Learning Objectives

- Create custom error patterns
- Override default error messages
- Use message pattern matching
- Configure patterns via Settings service

### Step 1: Create a Custom Pattern

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use Psr\Log\NullLogger;

// Create service with custom patterns
$service = new ErrorHandlingService(
    logger: new NullLogger(),
    customPatterns: [
        'database_error' => [
            'exception_class' => \PDOException::class,
            'user_message' => 'Database connection failed. Please try again.',
            'suggested_action' => 'Check database connection settings.',
        ],
        'file_not_found' => [
            'exception_class' => \RuntimeException::class,
            'message_pattern' => '/file.*not.*found/i',
            'user_message' => 'Required file not found. Please contact support.',
            'suggested_action' => 'Verify file permissions and paths.',
        ],
    ]
);

$service->initialize();

// Test custom pattern
try {
    throw new \PDOException('Connection refused');
} catch (\Throwable $e) {
    echo $service->convertToUserFriendly($e) . PHP_EOL;
    // Output: "Database connection failed. Please try again."
}
```

### Step 2: Override Default Patterns

Override default messages to match your application's terminology:

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudePhp\Exceptions\RateLimitError;

$service = new ErrorHandlingService(
    customPatterns: [
        // Override default rate limit message
        'rate_limit' => [
            'exception_class' => RateLimitError::class,
            'user_message' => 'You\'re sending requests too quickly. Please slow down.',
            'suggested_action' => 'Wait 2 minutes before trying again.',
        ],
        // Override default authentication message
        'authentication' => [
            'exception_class' => \ClaudePhp\Exceptions\AuthenticationError::class,
            'user_message' => 'Your session has expired. Please log in again.',
            'suggested_action' => 'Return to the login page and sign in.',
        ],
    ]
);

$service->initialize();

// Test with mock error
$mockRequest = $this->createMock(\Psr\Http\Message\RequestInterface::class);
$mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

$error = new RateLimitError(
    status_code: 429,
    message: 'Rate limit exceeded',
    request: $mockRequest,
    response: $mockResponse
);

echo $service->convertToUserFriendly($error) . PHP_EOL;
// Output: "You're sending requests too quickly. Please slow down."
```

### Step 3: Pattern Matching by Message Content

Use regex patterns to match error messages:

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;

$service = new ErrorHandlingService(
    customPatterns: [
        'quota_exceeded' => [
            'message_pattern' => '/quota.*exceeded|billing.*limit/i',
            'user_message' => 'API quota exceeded. Please upgrade your plan.',
            'suggested_action' => 'Visit the billing page to upgrade.',
        ],
        'content_filtered' => [
            'message_pattern' => '/content.*policy|inappropriate.*content/i',
            'user_message' => 'Your request was blocked by our content policy.',
            'suggested_action' => 'Review the content policy and modify your request.',
        ],
        'model_not_available' => [
            'message_pattern' => '/model.*not.*available|model.*deprecated/i',
            'user_message' => 'The requested AI model is not available.',
            'suggested_action' => 'Try using a different model version.',
        ],
    ]
);

$service->initialize();

// Test pattern matching
try {
    throw new \RuntimeException('API quota exceeded for this month');
} catch (\Throwable $e) {
    echo $service->convertToUserFriendly($e) . PHP_EOL;
    // Output: "API quota exceeded. Please upgrade your plan."
}
```

### Step 4: Dynamic Pattern Addition

Add patterns at runtime:

```php
<?php

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

$service = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);

// Add a new pattern dynamically
$service->addErrorPattern('maintenance_mode', [
    'exception_class' => \RuntimeException::class,
    'message_pattern' => '/maintenance|under.*maintenance/i',
    'user_message' => 'System is currently under maintenance.',
    'suggested_action' => 'Please try again in 30 minutes.',
]);

// Test the new pattern
try {
    throw new \RuntimeException('Service under maintenance');
} catch (\Throwable $e) {
    echo $service->convertToUserFriendly($e) . PHP_EOL;
    // Output: "System is currently under maintenance."
}
```

### Step 5: Configure via Settings Service

Use the Settings service for centralized configuration:

```php
<?php

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

// Configure settings
$settings = ServiceManager::getInstance()->get(ServiceType::SETTINGS);

$settings->set('error_handling.custom_patterns', [
    'application_error' => [
        'exception_class' => \LogicException::class,
        'user_message' => 'An application error occurred.',
        'suggested_action' => 'Please contact support with error code.',
    ],
]);

// Get error service (will use settings configuration)
$errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);

// Test
try {
    throw new \LogicException('Invalid state');
} catch (\Throwable $e) {
    echo $errorService->convertToUserFriendly($e) . PHP_EOL;
    // Output: "An application error occurred."
}
```

### Summary

You've learned how to:
- ✅ Create custom error patterns for your application
- ✅ Override default error messages
- ✅ Use regex patterns to match error messages
- ✅ Add patterns dynamically at runtime
- ✅ Configure patterns via Settings service

**Next**: Learn how to integrate error handling with agents.

---

## Tutorial 3: Integration with Agents

**Estimated Time**: 25 minutes  
**Prerequisites**: Completed Tutorials 1-2

### Learning Objectives

- Integrate error handling with Agent class
- Handle errors in tool execution
- Create error-resilient agent workflows
- Implement graceful degradation

### Step 1: Basic Agent Error Handling

```php
<?php

use ClaudeAgents\Agent;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Exceptions\AgentException;
use ClaudePhp\ClaudePhp;

class RobustAgent
{
    private Agent $agent;
    private $errorService;

    public function __construct(string $apiKey)
    {
        $client = new ClaudePhp(apiKey: $apiKey);
        $this->agent = Agent::create($client);
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
    }

    public function run(string $task): string
    {
        try {
            $result = $this->agent->run($task);
            return $result->getAnswer();
            
        } catch (\Throwable $e) {
            // Log detailed error for developers
            $details = $this->errorService->getErrorDetails($e);
            error_log('Agent execution failed: ' . json_encode($details));
            
            // Return user-friendly message
            throw new AgentException(
                $this->errorService->convertToUserFriendly($e),
                0,
                $e
            );
        }
    }
}

// Usage
$agent = new RobustAgent(getenv('ANTHROPIC_API_KEY'));

try {
    $response = $agent->run('What is 2+2?');
    echo "Response: {$response}\n";
} catch (AgentException $e) {
    echo "User Message: {$e->getMessage()}\n";
}
```

### Step 2: Tool-Level Error Handling

Handle errors at the tool level:

```php
<?php

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Agent;
use ClaudePhp\ClaudePhp;

$errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);

// Create a tool with built-in error handling
$weatherTool = Tool::create('get_weather')
    ->description('Get weather information for a city')
    ->parameter('city', 'string', 'City name')
    ->required('city')
    ->handler(function (array $input) use ($errorService) {
        // Execute with safe error handling
        $result = $errorService->executeToolSafely(
            function ($in) {
                // Simulate API call that might fail
                if ($in['city'] === 'InvalidCity') {
                    throw new \RuntimeException('City not found');
                }
                
                return "Weather in {$in['city']}: Sunny, 72°F";
            },
            'get_weather',
            $input
        );
        
        // Return either success or formatted error
        return $result['content'];
    });

// Use the tool with an agent
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$agent = Agent::create($client)->withTool($weatherTool);

$response = $agent->run('What is the weather in London?');
echo $response->getAnswer() . "\n";
```

### Step 3: Retry with Agents

Implement retry logic for agent operations:

```php
<?php

use ClaudeAgents\Agent;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;

class RetryableAgent
{
    private $errorService;
    private Agent $agent;

    public function __construct(string $apiKey)
    {
        $client = new ClaudePhp(apiKey: $apiKey);
        $this->agent = Agent::create($client);
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
    }

    public function runWithRetry(string $task, int $maxAttempts = 3): string
    {
        $result = $this->errorService->executeWithRetry(
            fn() => $this->agent->run($task),
            "Agent task: {$task}"
        );
        
        return $result->getAnswer();
    }
}

// Usage
$agent = new RetryableAgent(getenv('ANTHROPIC_API_KEY'));

try {
    $response = $agent->runWithRetry('Calculate 15 * 23');
    echo "Answer: {$response}\n";
} catch (\Throwable $e) {
    echo "Failed after retries: " . $e->getMessage() . "\n";
}
```

### Step 4: Fallback Strategies

Implement fallback behavior:

```php
<?php

use ClaudeAgents\Agent;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;

class FallbackAgent
{
    private $errorService;
    private Agent $primaryAgent;
    private Agent $fallbackAgent;

    public function __construct(string $apiKey)
    {
        $client = new ClaudePhp(apiKey: $apiKey);
        
        // Primary agent with advanced model
        $this->primaryAgent = Agent::create($client)
            ->withSystemPrompt('You are a helpful assistant.');
        
        // Fallback: simpler responses
        $this->fallbackAgent = Agent::create($client)
            ->withSystemPrompt('Give brief, simple answers.');
        
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
    }

    public function run(string $task): string
    {
        // Try primary agent
        try {
            $result = $this->primaryAgent->run($task);
            return $result->getAnswer();
            
        } catch (\Throwable $e) {
            $userMessage = $this->errorService->convertToUserFriendly($e);
            error_log("Primary agent failed: {$userMessage}");
            
            // Try fallback agent
            try {
                $result = $this->fallbackAgent->run($task);
                return "[Fallback Mode] " . $result->getAnswer();
                
            } catch (\Throwable $fallbackError) {
                // Both failed
                throw new \RuntimeException(
                    "Service temporarily unavailable: " . 
                    $this->errorService->convertToUserFriendly($fallbackError)
                );
            }
        }
    }
}

// Usage
$agent = new FallbackAgent(getenv('ANTHROPIC_API_KEY'));
$response = $agent->run('What is PHP?');
echo $response . "\n";
```

### Step 5: Complete Error-Resilient System

Put it all together:

```php
<?php

use ClaudeAgents\Agent;
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;

class ProductionAgent
{
    private Agent $agent;
    private $errorService;
    private $logger;

    public function __construct(string $apiKey, \Psr\Log\LoggerInterface $logger)
    {
        $client = new ClaudePhp(apiKey: $apiKey);
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
        $this->logger = $logger;
        
        // Create agent with error-safe tools
        $this->agent = Agent::create($client)
            ->withTool($this->createSafeTool())
            ->withSystemPrompt('You are a helpful assistant.');
    }

    private function createSafeTool(): Tool
    {
        return Tool::create('safe_calculation')
            ->description('Perform calculations safely')
            ->parameter('expression', 'string', 'Math expression')
            ->required('expression')
            ->handler(function (array $input) {
                return $this->errorService->executeToolWithFallback(
                    fn($in) => eval("return {$in['expression']};"),
                    'safe_calculation',
                    $input,
                    'Calculation unavailable'
                );
            });
    }

    public function execute(string $task): array
    {
        $startTime = microtime(true);
        
        try {
            $result = $this->errorService->executeWithRetry(
                fn() => $this->agent->run($task),
                "Execute task: {$task}"
            );
            
            $duration = microtime(true) - $startTime;
            
            $this->logger->info('Task completed', [
                'task' => $task,
                'duration' => $duration,
            ]);
            
            return [
                'success' => true,
                'result' => $result->getAnswer(),
                'duration' => $duration,
            ];
            
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            $details = $this->errorService->getErrorDetails($e);
            
            $this->logger->error('Task failed', array_merge($details, [
                'task' => $task,
                'duration' => $duration,
            ]));
            
            return [
                'success' => false,
                'error' => $this->errorService->convertToUserFriendly($e),
                'duration' => $duration,
            ];
        }
    }
}

// Usage
$logger = new \Monolog\Logger('agent');
$agent = new ProductionAgent(getenv('ANTHROPIC_API_KEY'), $logger);

$result = $agent->execute('What is 2+2?');
if ($result['success']) {
    echo "Result: {$result['result']}\n";
} else {
    echo "Error: {$result['error']}\n";
}
```

### Summary

You've learned how to:
- ✅ Integrate error handling with the Agent class
- ✅ Handle errors at the tool level
- ✅ Implement retry logic for agents
- ✅ Create fallback strategies
- ✅ Build production-ready error-resilient systems

**Next**: Learn advanced logging and debugging techniques.

---

## Tutorial 4: Logging and Debugging

**Estimated Time**: 20 minutes  
**Prerequisites**: Completed Tutorials 1-3

### Learning Objectives

- Configure PSR-3 logging
- Log detailed error information
- Debug error patterns
- Monitor error rates

### Step 1: Setup PSR-3 Logging

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

// Create a logger
$logger = new Logger('error_handling');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
$logger->pushHandler(new RotatingFileHandler('./logs/errors.log', 7, Logger::WARNING));

// Create service with logger
$service = new ErrorHandlingService(
    logger: $logger,
    maxRetries: 3,
    initialDelayMs: 1000
);

$service->initialize();

// Errors will now be logged automatically
try {
    throw new \RuntimeException('Test error');
} catch (\Throwable $e) {
    $userMessage = $service->convertToUserFriendly($e);
    $details = $service->getErrorDetails($e);
    
    // Explicitly log with context
    $logger->error('Operation failed', $details);
    
    echo "User Message: {$userMessage}\n";
}
```

### Step 2: Structured Logging

Log errors with rich context:

```php
<?php

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;

$logger = new Logger('app');
$logger->pushHandler(new StreamHandler('./logs/app.log', Logger::DEBUG));
$logger->pushProcessor(new IntrospectionProcessor());
$logger->pushProcessor(new MemoryUsageProcessor());

$service = new ErrorHandlingService(logger: $logger);
$service->initialize();

$client = new ClaudePhp(apiKey: 'invalid_key');

try {
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4',
        'max_tokens' => 100,
        'messages' => [['role' => 'user', 'content' => 'Test']],
    ]);
} catch (\Throwable $e) {
    $details = $service->getErrorDetails($e);
    
    // Log with additional context
    $logger->error('API call failed', array_merge($details, [
        'user_id' => 'user_123',
        'request_id' => uniqid(),
        'endpoint' => 'messages.create',
    ]));
    
    echo $service->convertToUserFriendly($e) . "\n";
}
```

### Step 3: Error Rate Monitoring

Track and monitor error rates:

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use Psr\Log\NullLogger;

class ErrorMonitor
{
    private ErrorHandlingService $service;
    private array $errorCounts = [];
    private int $totalRequests = 0;

    public function __construct()
    {
        $this->service = new ErrorHandlingService(logger: new NullLogger());
        $this->service->initialize();
    }

    public function trackRequest(callable $fn): mixed
    {
        $this->totalRequests++;
        
        try {
            return $fn();
        } catch (\Throwable $e) {
            $errorType = basename(str_replace('\\', '/', get_class($e)));
            
            if (!isset($this->errorCounts[$errorType])) {
                $this->errorCounts[$errorType] = 0;
            }
            $this->errorCounts[$errorType]++;
            
            // Log for monitoring
            $details = $this->service->getErrorDetails($e);
            error_log(json_encode([
                'error_type' => $errorType,
                'user_message' => $details['user_friendly_message'],
                'timestamp' => time(),
            ]));
            
            throw $e;
        }
    }

    public function getStats(): array
    {
        $errorRate = $this->totalRequests > 0 
            ? (array_sum($this->errorCounts) / $this->totalRequests) * 100 
            : 0;
        
        return [
            'total_requests' => $this->totalRequests,
            'total_errors' => array_sum($this->errorCounts),
            'error_rate_percent' => round($errorRate, 2),
            'errors_by_type' => $this->errorCounts,
        ];
    }
}

// Usage
$monitor = new ErrorMonitor();

// Simulate requests
for ($i = 0; $i < 10; $i++) {
    try {
        $monitor->trackRequest(function () use ($i) {
            if ($i % 3 === 0) {
                throw new \RuntimeException('Test error');
            }
            return 'Success';
        });
    } catch (\Throwable $e) {
        // Error logged and tracked
    }
}

print_r($monitor->getStats());
```

### Step 4: Debug Mode

Create a debug mode for development:

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use Psr\Log\LoggerInterface;

class DebugErrorService extends ErrorHandlingService
{
    private bool $debugMode;

    public function __construct(
        ?LoggerInterface $logger = null,
        bool $debugMode = false
    ) {
        parent::__construct($logger);
        $this->debugMode = $debugMode;
    }

    public function convertToUserFriendly(\Throwable $error): string
    {
        $userMessage = parent::convertToUserFriendly($error);
        
        if ($this->debugMode) {
            // In debug mode, include technical details
            $details = $this->getErrorDetails($error);
            
            return sprintf(
                "%s\n\n[DEBUG INFO]\nException: %s\nFile: %s:%d\nTrace:\n%s",
                $userMessage,
                $details['exception_class'],
                $details['file'],
                $details['line'],
                $error->getTraceAsString()
            );
        }
        
        return $userMessage;
    }
}

// Usage
$debugService = new DebugErrorService(debugMode: true);
$debugService->initialize();

try {
    throw new \RuntimeException('Test error');
} catch (\Throwable $e) {
    echo $debugService->convertToUserFriendly($e) . "\n";
}
```

### Summary

You've learned how to:
- ✅ Configure PSR-3 logging with Monolog
- ✅ Log detailed error information with context
- ✅ Monitor error rates and track patterns
- ✅ Create debug mode for development

**Next**: Learn production deployment patterns.

---

## Tutorial 5: Production Patterns

**Estimated Time**: 30 minutes  
**Prerequisites**: Completed Tutorials 1-4

### Learning Objectives

- Implement circuit breaker pattern
- Configure production logging
- Monitor system health
- Handle high-load scenarios

### Step 1: Circuit Breaker Integration

```php
<?php

use ClaudeAgents\Helpers\CircuitBreaker;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;

class ResilientAPIClient
{
    private ClaudePhp $client;
    private CircuitBreaker $breaker;
    private $errorService;

    public function __construct(string $apiKey)
    {
        $this->client = new ClaudePhp(apiKey: $apiKey);
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
        
        // Circuit breaker: open after 5 failures, reset after 60 seconds
        $this->breaker = new CircuitBreaker(
            failureThreshold: 5,
            resetTimeout: 60
        );
    }

    public function makeRequest(array $params): mixed
    {
        // Check circuit breaker first
        if ($this->breaker->isOpen()) {
            throw new \RuntimeException(
                'Service temporarily unavailable due to repeated failures'
            );
        }

        try {
            $result = $this->errorService->executeWithRetry(
                fn() => $this->client->messages()->create($params),
                'API request'
            );
            
            $this->breaker->recordSuccess();
            return $result;
            
        } catch (\Throwable $e) {
            $this->breaker->recordFailure();
            
            $userMessage = $this->errorService->convertToUserFriendly($e);
            throw new \RuntimeException($userMessage, 0, $e);
        }
    }

    public function getHealth(): array
    {
        return [
            'circuit_breaker_state' => $this->breaker->getState(),
            'failure_count' => $this->breaker->getFailureCount(),
            'is_available' => !$this->breaker->isOpen(),
        ];
    }
}

// Usage
$client = new ResilientAPIClient(getenv('ANTHROPIC_API_KEY'));

// Check health before making requests
$health = $client->getHealth();
if (!$health['is_available']) {
    echo "Service temporarily unavailable\n";
    exit;
}

try {
    $response = $client->makeRequest([
        'model' => 'claude-sonnet-4',
        'max_tokens' => 100,
        'messages' => [['role' => 'user', 'content' => 'Hello']],
    ]);
    echo "Success!\n";
} catch (\RuntimeException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Step 2: Production Logging Configuration

```php
<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Processor\WebProcessor;
use Monolog\Formatter\JsonFormatter;

function createProductionLogger(): Logger
{
    $logger = new Logger('production');
    
    // Console output for development
    if (getenv('APP_ENV') === 'development') {
        $consoleHandler = new StreamHandler('php://stdout', Logger::DEBUG);
        $logger->pushHandler($consoleHandler);
    }
    
    // File logging with rotation (7 days)
    $fileHandler = new RotatingFileHandler(
        './logs/errors.log',
        7,
        Logger::WARNING
    );
    $fileHandler->setFormatter(new JsonFormatter());
    $logger->pushHandler($fileHandler);
    
    // Critical errors to Slack (if configured)
    if ($slackWebhook = getenv('SLACK_WEBHOOK_URL')) {
        $slackHandler = new SlackWebhookHandler(
            $slackWebhook,
            null,
            'Error Bot',
            true,
            ':warning:',
            false,
            false,
            Logger::CRITICAL
        );
        $logger->pushHandler($slackHandler);
    }
    
    // Add web context
    $logger->pushProcessor(new WebProcessor());
    
    return $logger;
}

// Usage
$logger = createProductionLogger();
$service = new \ClaudeAgents\Services\ErrorHandling\ErrorHandlingService(
    logger: $logger,
    maxRetries: 3,
    initialDelayMs: 1000
);
```

### Step 3: Health Check Endpoint

```php
<?php

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;

class HealthChecker
{
    private $errorService;
    private array $checks = [];

    public function __construct()
    {
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
    }

    public function checkAPI(): bool
    {
        try {
            $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
            
            $response = $this->errorService->executeWithRetry(
                fn() => $client->messages()->create([
                    'model' => 'claude-sonnet-4',
                    'max_tokens' => 10,
                    'messages' => [['role' => 'user', 'content' => 'test']],
                ]),
                'Health check',
            );
            
            $this->checks['api'] = ['status' => 'healthy', 'latency_ms' => 0];
            return true;
            
        } catch (\Throwable $e) {
            $this->checks['api'] = [
                'status' => 'unhealthy',
                'error' => $this->errorService->convertToUserFriendly($e),
            ];
            return false;
        }
    }

    public function checkServices(): bool
    {
        try {
            $errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
            $this->checks['error_service'] = [
                'status' => 'healthy',
                'ready' => $errorService->isReady(),
            ];
            return true;
        } catch (\Throwable $e) {
            $this->checks['error_service'] = ['status' => 'unhealthy'];
            return false;
        }
    }

    public function getStatus(): array
    {
        $apiHealthy = $this->checkAPI();
        $servicesHealthy = $this->checkServices();
        
        return [
            'status' => ($apiHealthy && $servicesHealthy) ? 'healthy' : 'degraded',
            'timestamp' => time(),
            'checks' => $this->checks,
        ];
    }
}

// Health check endpoint
header('Content-Type: application/json');

$checker = new HealthChecker();
$status = $checker->getStatus();

http_response_code($status['status'] === 'healthy' ? 200 : 503);
echo json_encode($status, JSON_PRETTY_PRINT);
```

### Step 4: Rate Limiting

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

class RateLimitedService
{
    private $errorService;
    private $rateLimiter;
    private int $requestCount = 0;
    private int $lastResetTime;

    public function __construct(int $requestsPerMinute = 50)
    {
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
        
        // Calculate minimum interval
        $minIntervalMs = (int) (60000 / $requestsPerMinute);
        $this->rateLimiter = ErrorHandlingService::createRateLimiter($minIntervalMs);
        
        $this->lastResetTime = time();
    }

    public function execute(callable $fn, string $context): mixed
    {
        // Reset counter every minute
        if (time() - $this->lastResetTime >= 60) {
            $this->requestCount = 0;
            $this->lastResetTime = time();
        }

        // Apply rate limiting
        ($this->rateLimiter)();
        $this->requestCount++;

        // Execute with error handling
        return $this->errorService->executeWithRetry($fn, $context);
    }

    public function getStats(): array
    {
        return [
            'requests_this_minute' => $this->requestCount,
            'reset_in_seconds' => 60 - (time() - $this->lastResetTime),
        ];
    }
}

// Usage
$service = new RateLimitedService(requestsPerMinute: 50);

for ($i = 0; $i < 10; $i++) {
    try {
        $result = $service->execute(
            fn() => "Request {$i}",
            "Task {$i}"
        );
        echo "Completed: {$result}\n";
    } catch (\Throwable $e) {
        echo "Failed: " . $e->getMessage() . "\n";
    }
}

print_r($service->getStats());
```

### Summary

You've learned how to:
- ✅ Implement circuit breaker for resilience
- ✅ Configure production-grade logging
- ✅ Create health check endpoints
- ✅ Implement rate limiting

**Next**: Learn testing strategies for error handling.

---

## Tutorial 6: Testing Strategies

**Estimated Time**: 25 minutes  
**Prerequisites**: Completed Tutorials 1-5

### Learning Objectives

- Write unit tests for error patterns
- Test error handling integration
- Mock API errors for testing
- Test retry logic

### Step 1: Unit Testing Error Conversion

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudePhp\Exceptions\RateLimitError;
use ClaudePhp\Exceptions\AuthenticationError;
use PHPUnit\Framework\TestCase;

class ErrorHandlingTest extends TestCase
{
    private ErrorHandlingService $service;

    protected function setUp(): void
    {
        $this->service = new ErrorHandlingService();
        $this->service->initialize();
    }

    public function testConvertRateLimitError(): void
    {
        $error = $this->createMockRateLimitError();
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('Rate limit exceeded', $message);
    }

    public function testConvertAuthenticationError(): void
    {
        $error = $this->createMockAuthenticationError();
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('Authentication failed', $message);
    }

    public function testGetErrorDetails(): void
    {
        $error = new \RuntimeException('Test error', 123);
        $details = $this->service->getErrorDetails($error);

        $this->assertArrayHasKey('exception_class', $details);
        $this->assertArrayHasKey('message', $details);
        $this->assertArrayHasKey('code', $details);
        $this->assertSame(123, $details['code']);
    }

    private function createMockRateLimitError(): RateLimitError
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        return new RateLimitError(
            status_code: 429,
            message: 'Rate limit exceeded',
            request: $request,
            response: $response
        );
    }

    private function createMockAuthenticationError(): AuthenticationError
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        return new AuthenticationError(
            status_code: 401,
            message: 'Invalid API key',
            request: $request,
            response: $response
        );
    }
}
```

### Step 2: Testing Custom Patterns

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use PHPUnit\Framework\TestCase;

class CustomPatternTest extends TestCase
{
    public function testCustomPattern(): void
    {
        $service = new ErrorHandlingService(
            customPatterns: [
                'test_error' => [
                    'exception_class' => \LogicException::class,
                    'user_message' => 'Test error message',
                ],
            ]
        );
        $service->initialize();

        $error = new \LogicException('Logic error');
        $message = $service->convertToUserFriendly($error);

        $this->assertSame('Test error message', $message);
    }

    public function testPatternOverride(): void
    {
        $service = new ErrorHandlingService(
            customPatterns: [
                'rate_limit' => [
                    'user_message' => 'Custom rate limit message',
                ],
            ]
        );
        $service->initialize();

        $error = $this->createMockRateLimitError();
        $message = $service->convertToUserFriendly($error);

        $this->assertSame('Custom rate limit message', $message);
    }

    private function createMockRateLimitError()
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        return new \ClaudePhp\Exceptions\RateLimitError(
            status_code: 429,
            message: 'Rate limit',
            request: $request,
            response: $response
        );
    }
}
```

### Step 3: Integration Testing with Mock API

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class ErrorHandlingIntegrationTest extends TestCase
{
    private ErrorHandlingService $service;

    protected function setUp(): void
    {
        $this->service = new ErrorHandlingService();
        $this->service->initialize();
    }

    public function testRealAuthenticationError(): void
    {
        // Use invalid API key to trigger auth error
        $client = new ClaudePhp(apiKey: 'invalid_test_key_123');

        try {
            $client->messages()->create([
                'model' => 'claude-sonnet-4',
                'max_tokens' => 10,
                'messages' => [['role' => 'user', 'content' => 'test']],
            ]);

            $this->fail('Expected AuthenticationError');
        } catch (\Throwable $e) {
            $message = $this->service->convertToUserFriendly($e);
            $details = $this->service->getErrorDetails($e);

            $this->assertStringContainsString('Authentication', $message);
            $this->assertArrayHasKey('suggested_action', $details);
        }
    }

    public function testRetryLogic(): void
    {
        $attempts = 0;
        $maxAttempts = 3;

        try {
            $this->service->executeWithRetry(
                function () use (&$attempts, $maxAttempts) {
                    $attempts++;
                    if ($attempts < $maxAttempts) {
                        throw new \RuntimeException('Temporary error');
                    }
                    return 'Success';
                },
                'Test retry'
            );

            $this->assertSame($maxAttempts, $attempts);
        } catch (\Throwable $e) {
            $this->fail('Should not throw after successful retry');
        }
    }
}
```

### Step 4: Testing Tool Error Handling

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use PHPUnit\Framework\TestCase;

class ToolErrorHandlingTest extends TestCase
{
    private ErrorHandlingService $service;

    protected function setUp(): void
    {
        $this->service = new ErrorHandlingService();
        $this->service->initialize();
    }

    public function testToolSafeExecution(): void
    {
        $successTool = fn() => 'success';
        $result = $this->service->executeToolSafely($successTool, 'test_tool', []);

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['content']);
        $this->assertFalse($result['is_error']);
    }

    public function testToolFailureHandling(): void
    {
        $failingTool = function () {
            throw new \RuntimeException('Tool failed');
        };

        $result = $this->service->executeToolSafely($failingTool, 'failing_tool', []);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['is_error']);
        $this->assertStringContainsString('Error:', $result['content']);
    }

    public function testToolWithFallback(): void
    {
        $failingTool = function () {
            throw new \RuntimeException('Tool failed');
        };

        $result = $this->service->executeToolWithFallback(
            $failingTool,
            'failing_tool',
            [],
            'fallback value'
        );

        $this->assertSame('fallback value', $result);
    }
}
```

### Summary

You've learned how to:
- ✅ Write comprehensive unit tests
- ✅ Test custom error patterns
- ✅ Perform integration testing with real APIs
- ✅ Test tool error handling
- ✅ Test retry logic

## Conclusion

Congratulations! You've completed the Error Handling Service tutorial series. You now have the knowledge to:

- Implement user-friendly error handling in your applications
- Create custom error patterns for domain-specific errors
- Integrate error handling with agents and tools
- Configure production-grade logging and monitoring
- Deploy error-resilient systems
- Write comprehensive tests

### Next Steps

- Explore the [Error Handling Service Documentation](../services/error-handling.md)
- Review the [Best Practices Guide](../BestPractices.md)
- Check out the [Production Patterns Tutorial](ProductionPatterns_Tutorial.md)
- Integrate with [Observability](../Observability.md) systems

### Resources

- [Example Code](../../examples/tutorials/error-handling/)
- [API Reference](../services/error-handling.md#api-reference)
- [Services Documentation](../services/README.md)

Happy coding! 🎉
