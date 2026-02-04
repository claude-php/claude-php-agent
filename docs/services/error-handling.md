# Error Handling Service

The Error Handling Service provides user-friendly error message conversion and comprehensive error handling capabilities for Claude API errors. Inspired by Langflow's error handling approach, it transforms technical exceptions into actionable messages that end-users can understand.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Error Pattern Catalog](#error-pattern-catalog)
- [Configuration](#configuration)
- [Integration](#integration)
- [Best Practices](#best-practices)
- [Migration Guide](#migration-guide)
- [API Reference](#api-reference)

## Overview

The ErrorHandlingService bridges the gap between technical API errors and user-friendly messages. When your application encounters errors from the Claude API, this service:

1. **Identifies** the error type through pattern matching
2. **Converts** technical messages into user-friendly text
3. **Provides** detailed error context for debugging
4. **Handles** retry logic with exponential backoff
5. **Logs** comprehensive error information

### Architecture

```
┌─────────────────┐
│   Your Agent    │
└────────┬────────┘
         │ throws exception
         ▼
┌─────────────────────────┐
│ ErrorHandlingService    │
├─────────────────────────┤
│ • Pattern Matching      │
│ • Message Conversion    │
│ • Retry Logic           │
│ • Detailed Logging      │
└────────┬────────────────┘
         │
         ├──► User-Friendly Message
         └──► Detailed Error Context
```

## Features

- **🎯 User-Friendly Messages**: Convert technical errors into actionable text
- **🔄 Smart Retry Logic**: Exponential backoff with configurable attempts
- **📊 Detailed Logging**: Extract comprehensive error information for debugging
- **⚙️ Configurable Patterns**: Override defaults or add custom error patterns
- **🏢 Service Integration**: Seamless integration with ServiceManager
- **🛠️ Tool Support**: Safe tool execution with error handling
- **📝 PSR-3 Logging**: Compatible with any PSR-3 logger

## Installation

The Error Handling Service is included in the claude-php/agent package:

```bash
composer require claude-php/agent
```

## Quick Start

### Basic Usage

```php
<?php

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;

// Get the service
$errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);

// Use with Claude API
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

try {
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4',
        'max_tokens' => 1024,
        'messages' => [
            ['role' => 'user', 'content' => 'Hello!'],
        ],
    ]);
} catch (\Throwable $e) {
    // Get user-friendly message
    echo $errorService->convertToUserFriendly($e) . PHP_EOL;
    
    // Get detailed error info for logging
    $details = $errorService->getErrorDetails($e);
    error_log(json_encode($details));
}
```

### With Retry Logic

```php
<?php

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;

$errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Execute with automatic retry
$response = $errorService->executeWithRetry(
    fn() => $client->messages()->create([
        'model' => 'claude-sonnet-4',
        'max_tokens' => 1024,
        'messages' => [
            ['role' => 'user', 'content' => 'Hello!'],
        ],
    ]),
    'Create message'
);
```

## Error Pattern Catalog

The service includes 9 default error patterns covering common API errors:

### Rate Limit Errors (429)

**Exception**: `ClaudePhp\Exceptions\RateLimitError`  
**User Message**: "Rate limit exceeded. Please wait before retrying."  
**Suggested Action**: Wait 60 seconds before making another request

**Example**:
```php
try {
    // Make too many requests quickly
} catch (RateLimitError $e) {
    echo $errorService->convertToUserFriendly($e);
    // Output: "Rate limit exceeded. Please wait before retrying."
}
```

### Authentication Errors (401)

**Exception**: `ClaudePhp\Exceptions\AuthenticationError`  
**User Message**: "Authentication failed. Please check your API key."  
**Suggested Action**: Verify your ANTHROPIC_API_KEY is valid

**Example**:
```php
$client = new ClaudePhp(apiKey: 'invalid_key');
try {
    $response = $client->messages()->create([...]);
} catch (AuthenticationError $e) {
    echo $errorService->convertToUserFriendly($e);
    // Output: "Authentication failed. Please check your API key."
}
```

### Permission Errors (403)

**Exception**: `ClaudePhp\Exceptions\PermissionDeniedError`  
**User Message**: "Permission denied. Check your API key permissions."  
**Suggested Action**: Ensure your API key has the required permissions

### Timeout Errors

**Exception**: `ClaudePhp\Exceptions\APITimeoutError`  
**User Message**: "Request timed out. Please try again."  
**Suggested Action**: Check your network connection or increase timeout

### Connection Errors

**Exception**: `ClaudePhp\Exceptions\APIConnectionError`  
**User Message**: "Connection error. Check your network."  
**Suggested Action**: Verify your internet connection and try again

### Overloaded Errors (529)

**Exception**: `ClaudePhp\Exceptions\OverloadedError`  
**User Message**: "Service temporarily overloaded. Please retry."  
**Suggested Action**: Wait a few moments and try again

### Bad Request Errors (400)

**Exception**: `ClaudePhp\Exceptions\BadRequestError`  
**User Message**: "Invalid request. Please check your parameters."  
**Suggested Action**: Review your request parameters and format

### Server Errors (500)

**Exception**: `ClaudePhp\Exceptions\InternalServerError`  
**User Message**: "Server error occurred. Please try again later."  
**Suggested Action**: This is a temporary server issue. Retry in a few minutes

### Validation Errors (422)

**Exception**: `ClaudePhp\Exceptions\UnprocessableEntityError`  
**User Message**: "Request validation failed. Check your input."  
**Suggested Action**: Verify all required fields are provided correctly

## Configuration

### Custom Error Patterns

Add your own error patterns:

```php
<?php

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use Psr\Log\NullLogger;

$service = new ErrorHandlingService(
    logger: new NullLogger(),
    maxRetries: 3,
    initialDelayMs: 1000,
    customPatterns: [
        'quota_exceeded' => [
            'exception_class' => \RuntimeException::class,
            'message_pattern' => '/quota.*exceeded/i',
            'user_message' => 'API quota exceeded. Please check your billing.',
            'suggested_action' => 'Upgrade your API plan or wait for quota reset.',
        ],
        'content_filter' => [
            'message_pattern' => '/content.*policy/i',
            'user_message' => 'Request blocked by content policy.',
            'suggested_action' => 'Review the content policy and adjust your request.',
        ],
    ]
);
```

### Override Default Patterns

Override any default pattern:

```php
$service = new ErrorHandlingService(
    customPatterns: [
        'rate_limit' => [
            'exception_class' => RateLimitError::class,
            'user_message' => 'Too many requests. Please slow down.',
            'suggested_action' => 'Wait 2 minutes before retrying.',
        ],
    ]
);
```

### Retry Configuration

Configure retry behavior:

```php
$service = new ErrorHandlingService(
    maxRetries: 5,           // Try up to 5 times
    initialDelayMs: 2000,    // Start with 2 second delay
);
```

### Via Settings Service

Configure through the Settings service:

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

$settings = ServiceManager::getInstance()->get(ServiceType::SETTINGS);

$settings->set('error_handling.max_retries', 5);
$settings->set('error_handling.initial_delay_ms', 2000);
$settings->set('error_handling.custom_patterns', [
    'my_pattern' => [
        'exception_class' => MyException::class,
        'user_message' => 'My custom message',
    ],
]);

// Service will use these settings when created
$errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
```

## Integration

### With Agents

Integrate error handling into your agents:

```php
<?php

use ClaudeAgents\Agent;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Exceptions\AgentException;
use ClaudePhp\ClaudePhp;

class MyAgent
{
    private Agent $agent;
    private $errorService;

    public function __construct()
    {
        $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
        $this->agent = Agent::create($client);
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
    }

    public function run(string $task): string
    {
        try {
            $result = $this->agent->run($task);
            return $result->getAnswer();
        } catch (\Throwable $e) {
            // Log detailed error
            $details = $this->errorService->getErrorDetails($e);
            error_log(json_encode($details));

            // Return user-friendly message
            throw new AgentException(
                $this->errorService->convertToUserFriendly($e),
                0,
                $e
            );
        }
    }
}
```

### With Tools

Handle tool execution errors:

```php
<?php

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

$errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);

$apiTool = Tool::create('call_external_api')
    ->description('Call an external API')
    ->parameter('url', 'string', 'API endpoint URL')
    ->required('url')
    ->handler(function (array $input) use ($errorService) {
        $result = $errorService->executeToolSafely(
            fn($in) => file_get_contents($in['url']),
            'call_external_api',
            $input
        );

        if ($result['is_error']) {
            return $result['content']; // Already formatted error message
        }

        return $result['content'];
    });
```

### With Fallback

Use fallback values for non-critical operations:

```php
$weatherData = $errorService->executeToolWithFallback(
    fn($input) => $weatherApi->fetch($input['city']),
    'weather_api',
    ['city' => 'London'],
    'Weather data temporarily unavailable'
);
```

## Best Practices

### 1. Always Log Detailed Errors

While showing user-friendly messages to users, always log the detailed error information:

```php
try {
    $result = $agent->run($task);
} catch (\Throwable $e) {
    // User-facing
    $userMessage = $errorService->convertToUserFriendly($e);
    
    // System logging
    $details = $errorService->getErrorDetails($e);
    $logger->error('Agent execution failed', $details);
    
    // Show only user message to end user
    return $userMessage;
}
```

### 2. Use Appropriate Retry Counts

Configure retries based on operation criticality:

```php
// Critical operations: more retries
$criticalService = new ErrorHandlingService(maxRetries: 5);

// Non-critical operations: fewer retries
$nonCriticalService = new ErrorHandlingService(maxRetries: 2);
```

### 3. Handle Rate Limiting Gracefully

Use the rate limiter for predictable request patterns:

```php
$limiter = ErrorHandlingService::createRateLimiter(200); // 200ms between requests

foreach ($tasks as $task) {
    $limiter(); // Throttle requests
    $result = $agent->run($task);
}
```

### 4. Customize Messages for Your Domain

Override default messages to match your application's terminology:

```php
$service = new ErrorHandlingService(
    customPatterns: [
        'authentication' => [
            'exception_class' => AuthenticationError::class,
            'user_message' => 'Your session has expired. Please log in again.',
        ],
    ]
);
```

### 5. Combine with Circuit Breaker

For production systems, combine with circuit breaker pattern:

```php
use ClaudeAgents\Helpers\CircuitBreaker;

$breaker = new CircuitBreaker(
    failureThreshold: 5,
    resetTimeout: 60
);

if ($breaker->isOpen()) {
    throw new \RuntimeException('Service temporarily unavailable');
}

try {
    $result = $errorService->executeWithRetry($fn, 'API call');
    $breaker->recordSuccess();
} catch (\Throwable $e) {
    $breaker->recordFailure();
    throw $e;
}
```

## Migration Guide

### From ErrorHandler to ErrorHandlingService

The new ErrorHandlingService replaces the old `ErrorHandler` class with enhanced capabilities:

**Old Code**:
```php
use ClaudeAgents\Helpers\ErrorHandler;

$handler = new ErrorHandler($logger, 3, 1000);
$result = $handler->executeWithRetry($fn, 'API call');
```

**New Code**:
```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

$handler = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
$result = $handler->executeWithRetry($fn, 'API call');

// NEW: Convert errors to user-friendly messages
$userMessage = $handler->convertToUserFriendly($exception);
```

**Migration Steps**:

1. Replace direct `ErrorHandler` instantiation with service access
2. Add user-friendly message conversion where appropriate
3. Update logging to use `getErrorDetails()` method
4. Configure via Settings service instead of constructor params

## API Reference

### ErrorHandlingService

#### `convertToUserFriendly(Throwable $error): string`

Convert a technical exception into a user-friendly message.

**Parameters**:
- `$error` (Throwable): The exception to convert

**Returns**: User-friendly error message string

**Example**:
```php
$message = $service->convertToUserFriendly($exception);
echo $message; // "Rate limit exceeded. Please wait before retrying."
```

#### `getErrorDetails(Throwable $error): array`

Get detailed error information for logging and debugging.

**Parameters**:
- `$error` (Throwable): The exception to analyze

**Returns**: Array containing:
- `exception_class` (string): Full class name
- `message` (string): Technical error message
- `code` (int): Error code
- `file` (string): File where error occurred
- `line` (int): Line number
- `user_friendly_message` (string): Converted message
- `status_code` (int, optional): HTTP status code
- `request_id` (string, optional): API request ID
- `suggested_action` (string, optional): Suggested fix

**Example**:
```php
$details = $service->getErrorDetails($exception);
$logger->error('API call failed', $details);
```

#### `executeWithRetry(callable $fn, string $context): mixed`

Execute a function with comprehensive error handling and retry logic.

**Parameters**:
- `$fn` (callable): Function to execute
- `$context` (string): Context description for logging

**Returns**: Result from the function

**Throws**: Exception if all retries fail or non-retryable error occurs

**Example**:
```php
$result = $service->executeWithRetry(
    fn() => $client->messages()->create([...]),
    'Create message'
);
```

#### `executeToolSafely(callable $toolFn, string $toolName, array $input): array`

Execute a tool with error handling.

**Parameters**:
- `$toolFn` (callable): Tool execution function
- `$toolName` (string): Tool name for logging
- `$input` (array): Tool input parameters

**Returns**: Array with keys:
- `success` (bool): Whether execution succeeded
- `content` (mixed): Result or error message
- `is_error` (bool): Whether an error occurred

**Example**:
```php
$result = $service->executeToolSafely(
    $tool->getHandler(),
    'my_tool',
    ['param' => 'value']
);
```

#### `executeToolWithFallback(callable $toolFn, string $toolName, array $input, string $fallback): string`

Execute a tool with fallback value on error.

**Parameters**:
- `$toolFn` (callable): Tool execution function
- `$toolName` (string): Tool name for logging
- `$input` (array): Tool input parameters
- `$fallback` (string): Fallback value on error

**Returns**: Result string or fallback

**Example**:
```php
$data = $service->executeToolWithFallback(
    $api->fetch(...),
    'api_call',
    ['id' => 123],
    'Data unavailable'
);
```

#### `addErrorPattern(string $name, array $pattern): self`

Add or update an error pattern.

**Parameters**:
- `$name` (string): Pattern name
- `$pattern` (array): Pattern configuration

**Returns**: Self for chaining

**Example**:
```php
$service->addErrorPattern('custom_error', [
    'exception_class' => MyException::class,
    'user_message' => 'Custom message',
]);
```

#### `getErrorPatterns(): array`

Get all configured error patterns.

**Returns**: Array of pattern configurations

#### `static createRateLimiter(int $minIntervalMs): callable`

Create a rate limiter function.

**Parameters**:
- `$minIntervalMs` (int): Minimum milliseconds between requests

**Returns**: Callable throttle function

**Example**:
```php
$limiter = ErrorHandlingService::createRateLimiter(200);
$limiter(); // Ensures 200ms since last call
```

## See Also

- [Services System Documentation](README.md)
- [Error Handling Tutorial](../tutorials/ErrorHandling_Tutorial.md)
- [Production Patterns](../BestPractices.md)
- [Logging and Monitoring](../Observability.md)
