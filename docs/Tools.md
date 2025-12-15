# Tools System

The Tools system provides a flexible and type-safe way to extend agent capabilities with custom functionality. Tools allow agents to interact with external systems, perform calculations, access databases, and more.

## Table of Contents

- [Overview](#overview)
- [Core Concepts](#core-concepts)
- [Creating Tools](#creating-tools)
- [Parameter Types](#parameter-types)
- [Tool Handlers](#tool-handlers)
- [Tool Results](#tool-results)
- [Tool Registry](#tool-registry)
- [Integration with Agents](#integration-with-agents)
- [Chain-to-Tool Conversion](#chain-to-tool-conversion)
- [Error Handling](#error-handling)
- [Best Practices](#best-practices)
- [Advanced Usage](#advanced-usage)
- [Examples](#examples)

## Overview

The Tools system consists of three main components:

1. **Tool** - Defines the tool's interface, parameters, and execution logic
2. **ToolResult** - Represents the result of tool execution
3. **ToolRegistry** - Manages multiple tools and provides lookup/execution

```php
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;
use ClaudeAgents\Tools\ToolRegistry;

// Create a simple calculator tool
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'Math expression to evaluate')
    ->handler(function (array $input): string {
        return (string) eval("return {$input['expression']};");
    });

// Use it
$result = $calculator->execute(['expression' => '5 + 3']);
echo $result->getContent(); // "8"
```

## Core Concepts

### Tool Definition

A tool definition consists of:

- **Name**: Unique identifier for the tool
- **Description**: What the tool does (helps the agent decide when to use it)
- **Parameters**: Input schema defining expected parameters
- **Handler**: Function that executes the tool logic

### Type Safety

The Tools system provides type-safe parameter definitions with validation:

- String, number, boolean, array, and object types
- Required vs optional parameters
- Enum constraints for string parameters
- Min/max constraints for numeric parameters
- Custom validation through handlers

### Execution Model

Tools follow a simple execution model:

1. Agent selects a tool based on the task
2. Agent provides parameters matching the tool's schema
3. Tool handler executes with validated input
4. Tool returns a ToolResult (success or error)
5. Agent uses the result to continue reasoning

## Creating Tools

### Basic Tool Creation

Use the fluent API to create tools:

```php
$tool = Tool::create('tool_name')
    ->description('What this tool does')
    ->stringParam('param1', 'Description of param1')
    ->numberParam('param2', 'Description of param2')
    ->handler(function (array $input) {
        // Tool logic here
        return "Result";
    });
```

### Static Factory Method

```php
public static function create(string $name): self
```

Creates a new tool with the given name.

### Fluent Builder Methods

All configuration methods return `$this` for chaining:

```php
$tool = Tool::create('my_tool')
    ->description('Tool description')
    ->stringParam('name', 'User name', true)
    ->numberParam('age', 'User age', false)
    ->handler($handler);
```

## Parameter Types

### String Parameters

```php
public function stringParam(
    string $name,
    string $description,
    bool $required = true,
    ?array $enum = null
): self
```

**Example:**

```php
// Basic string parameter
->stringParam('name', 'User name')

// Optional string parameter
->stringParam('title', 'Optional title', false)

// String with enum (limited values)
->stringParam('status', 'Status', true, ['active', 'inactive', 'pending'])
```

### Number Parameters

```php
public function numberParam(
    string $name,
    string $description,
    bool $required = true,
    ?float $minimum = null,
    ?float $maximum = null
): self
```

**Example:**

```php
// Basic number parameter
->numberParam('age', 'User age')

// Number with range constraints
->numberParam('rating', 'Rating from 1-5', true, 1.0, 5.0)

// Optional number
->numberParam('discount', 'Optional discount percentage', false, 0, 100)
```

### Boolean Parameters

```php
public function booleanParam(
    string $name,
    string $description,
    bool $required = true
): self
```

**Example:**

```php
// Basic boolean
->booleanParam('enabled', 'Whether feature is enabled')

// Optional boolean
->booleanParam('verbose', 'Enable verbose output', false)
```

### Array Parameters

```php
public function arrayParam(
    string $name,
    string $description,
    bool $required = true,
    ?array $items = null
): self
```

**Example:**

```php
// Basic array
->arrayParam('tags', 'List of tags')

// Array with typed items
->arrayParam('users', 'List of users', true, [
    'type' => 'object',
    'properties' => [
        'id' => ['type' => 'number'],
        'name' => ['type' => 'string'],
    ],
])
```

### Generic Parameters

For complex or custom types:

```php
public function parameter(
    string $name,
    string $type,
    string $description,
    bool $required = true,
    array $extra = []
): self
```

**Example:**

```php
// Object parameter with nested structure
->parameter('config', 'object', 'Configuration object', true, [
    'properties' => [
        'timeout' => ['type' => 'number'],
        'retries' => ['type' => 'number'],
        'endpoints' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ],
    ],
])
```

## Tool Handlers

The handler is the function that executes when the tool is called.

### Handler Signature

```php
function (array $input): string|array|ToolResultInterface
```

### Return Types

Handlers can return:

1. **String** - Automatically wrapped in `ToolResult::success()`
2. **Array** - JSON-encoded and wrapped in `ToolResult::success()`
3. **ToolResultInterface** - Returned as-is for full control

### Simple Handler

```php
->handler(function (array $input): string {
    return "Hello, {$input['name']}!";
})
```

### Array Return

```php
->handler(function (array $input): array {
    return [
        'result' => $input['a'] + $input['b'],
        'operation' => 'addition',
    ];
})
```

### Explicit ToolResult

```php
->handler(function (array $input): ToolResult {
    if (empty($input['query'])) {
        return ToolResult::error('Query cannot be empty');
    }
    
    $results = performSearch($input['query']);
    return ToolResult::success($results);
})
```

### Error Handling in Handlers

Exceptions thrown in handlers are automatically caught:

```php
->handler(function (array $input): string {
    // This exception will be caught and converted to ToolResult::error()
    throw new \RuntimeException('Something went wrong');
})
```

## Tool Results

### Creating Results

```php
// Success with string
$result = ToolResult::success('Operation completed');

// Success with array
$result = ToolResult::success(['status' => 'ok', 'count' => 5]);

// Error
$result = ToolResult::error('Invalid input provided');

// From exception
try {
    // Some operation
} catch (\Throwable $e) {
    $result = ToolResult::fromException($e);
}
```

### Checking Result Status

```php
if ($result->isSuccess()) {
    echo "Tool succeeded: " . $result->getContent();
}

if ($result->isError()) {
    echo "Tool failed: " . $result->getContent();
}
```

### API Format Conversion

Convert to Claude API format:

```php
$apiFormat = $result->toApiFormat('tool_use_123');

// Returns:
// [
//     'type' => 'tool_result',
//     'tool_use_id' => 'tool_use_123',
//     'content' => 'result content',
//     'is_error' => true // only if error
// ]
```

## Tool Registry

Manage multiple tools with the registry.

### Creating a Registry

```php
$registry = new ToolRegistry();
```

### Registering Tools

```php
// Register single tool
$registry->register($calculatorTool);

// Register multiple tools
$registry->registerMany([
    $calculatorTool,
    $searchTool,
    $databaseTool,
]);

// Fluent registration
$registry
    ->register($tool1)
    ->register($tool2)
    ->register($tool3);
```

### Accessing Tools

```php
// Get tool by name
$tool = $registry->get('calculate');

// Check if tool exists
if ($registry->has('search')) {
    // Tool is available
}

// Get all tools
$allTools = $registry->all();

// Get tool names
$names = $registry->names(); // ['calculate', 'search', 'database']

// Get count
$count = $registry->count();
```

### Executing Tools

```php
// Execute through registry
$result = $registry->execute('calculate', ['expression' => '5 + 3']);
```

### Removing Tools

```php
// Remove single tool
$registry->remove('search');

// Clear all tools
$registry->clear();
```

### Export Definitions

Get tool definitions for API calls:

```php
$definitions = $registry->toDefinitions();

// Returns array of tool definitions compatible with Claude API
// [
//     [
//         'name' => 'calculate',
//         'description' => 'Perform calculations',
//         'input_schema' => [...]
//     ],
//     ...
// ]
```

## Integration with Agents

### Adding Tools to Agents

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Tools\Tool;

$agent = Agent::create($client)
    ->withTool($calculatorTool)
    ->withTool($searchTool);

// Or add multiple at once
$agent = Agent::create($client)
    ->withTools([$tool1, $tool2, $tool3]);
```

### Tool Execution Callbacks

Monitor tool execution:

```php
$agent->onToolExecution(function (string $toolName, array $input, $result) {
    echo "Tool: {$toolName}\n";
    echo "Input: " . json_encode($input) . "\n";
    echo "Output: {$result->getContent()}\n";
});
```

### Complete Example

```php
$calculator = Tool::create('calculate')
    ->description('Perform math calculations')
    ->stringParam('expression', 'Math expression')
    ->handler(function (array $input): string {
        return (string) eval("return {$input['expression']};");
    });

$agent = Agent::create($client)
    ->withTool($calculator)
    ->withSystemPrompt('You are a math assistant. Use the calculator for computations.')
    ->onToolExecution(function ($tool, $input, $result) {
        echo "🔧 {$tool}: {$result->getContent()}\n";
    });

$response = $agent->run('What is 25 * 17 + 42?');
```

## Chain-to-Tool Conversion

Convert chains to tools for agent use.

### Basic Conversion

```php
use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Tools\Tool;

$chain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Summarize: {text}'));

$tool = Tool::fromChain(
    $chain,
    'summarize',
    'Summarizes text using AI'
);

$agent->withTool($tool);
```

### How It Works

1. Extracts input schema from chain
2. Wraps chain execution in tool handler
3. Converts chain output to JSON
4. Handles errors gracefully

### Example with Complex Chain

```php
$analysisChain = SequentialChain::create()
    ->addChain('extract', $extractChain)
    ->addChain('analyze', $analyzeChain)
    ->addChain('format', $formatChain);

$analysisTool = Tool::fromChain(
    $analysisChain,
    'analyze_document',
    'Performs multi-step document analysis'
);

$agent = Agent::create($client)
    ->withTool($analysisTool)
    ->run('Analyze this document for me');
```

## Error Handling

### Handler Exceptions

Exceptions in handlers are automatically caught:

```php
->handler(function (array $input): string {
    if (!isset($input['required_param'])) {
        throw new \InvalidArgumentException('Missing required parameter');
    }
    // Exception is caught and converted to ToolResult::error()
})
```

### Explicit Error Results

Return errors explicitly:

```php
->handler(function (array $input): ToolResult {
    if (empty($input['query'])) {
        return ToolResult::error('Query parameter is required');
    }
    
    $results = search($input['query']);
    
    if (empty($results)) {
        return ToolResult::error('No results found');
    }
    
    return ToolResult::success($results);
})
```

### Validation

Validate input before processing:

```php
->handler(function (array $input): ToolResult {
    // Validate input
    $errors = [];
    
    if (!is_numeric($input['amount'])) {
        $errors[] = 'Amount must be numeric';
    }
    
    if ($input['amount'] < 0) {
        $errors[] = 'Amount must be positive';
    }
    
    if (!empty($errors)) {
        return ToolResult::error(implode(', ', $errors));
    }
    
    // Process...
    return ToolResult::success($result);
})
```

## Best Practices

### 1. Clear Descriptions

Write clear, concise descriptions that help the agent understand when to use the tool:

```php
// ❌ Bad
->description('Does stuff')

// ✅ Good
->description('Searches the product database by name, SKU, or category and returns matching products with prices and availability')
```

### 2. Descriptive Parameter Names

Use clear parameter descriptions:

```php
// ❌ Bad
->stringParam('q', 'The q')

// ✅ Good
->stringParam('query', 'Search query - can be product name, SKU, or category')
```

### 3. Validation

Validate all inputs:

```php
->handler(function (array $input): ToolResult {
    // Validate required data
    if (empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        return ToolResult::error('Invalid email address');
    }
    
    // Process...
})
```

### 4. Structured Output

Return structured data when possible:

```php
->handler(function (array $input): array {
    return [
        'status' => 'success',
        'data' => $results,
        'count' => count($results),
        'timestamp' => time(),
    ];
})
```

### 5. Error Messages

Provide helpful error messages:

```php
// ❌ Bad
return ToolResult::error('Error');

// ✅ Good
return ToolResult::error('Failed to connect to database: connection timeout after 30s. Please check your network connection and try again.');
```

### 6. Side Effects

Be clear about side effects in descriptions:

```php
Tool::create('send_email')
    ->description('Sends an email to the specified recipient. Note: This will actually send an email, use with caution.')
    ->stringParam('to', 'Recipient email address')
    ->stringParam('subject', 'Email subject')
    ->stringParam('body', 'Email body')
```

### 7. Idempotency

Make tools idempotent when possible:

```php
Tool::create('get_user')
    ->description('Retrieves user information (read-only, can be called multiple times safely)')
```

### 8. Resource Limits

Implement timeouts and limits:

```php
->handler(function (array $input): ToolResult {
    $timeout = 30; // seconds
    $startTime = time();
    
    $results = [];
    foreach ($items as $item) {
        if (time() - $startTime > $timeout) {
            return ToolResult::error('Operation timed out after 30 seconds');
        }
        
        $results[] = process($item);
    }
    
    return ToolResult::success($results);
})
```

## Advanced Usage

### Dynamic Tool Creation

Create tools dynamically based on configuration:

```php
function createDatabaseTool(PDO $pdo, string $table): Tool
{
    return Tool::create("query_{$table}")
        ->description("Query the {$table} table")
        ->stringParam('query', 'SQL WHERE clause')
        ->handler(function (array $input) use ($pdo, $table): array {
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$input['query']}");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
}

$usersTool = createDatabaseTool($pdo, 'users');
$productsTool = createDatabaseTool($pdo, 'products');
```

### Tool Composition

Combine multiple tools:

```php
function createMultiSearchTool(array $searchEngines): Tool
{
    return Tool::create('multi_search')
        ->description('Search across multiple search engines')
        ->stringParam('query', 'Search query')
        ->handler(function (array $input) use ($searchEngines): array {
            $results = [];
            foreach ($searchEngines as $name => $tool) {
                $result = $tool->execute(['query' => $input['query']]);
                $results[$name] = json_decode($result->getContent(), true);
            }
            return $results;
        });
}
```

### Caching Tool Results

Cache expensive operations:

```php
$cache = [];

$expensiveTool = Tool::create('expensive_operation')
    ->description('Performs expensive computation')
    ->stringParam('input', 'Input data')
    ->handler(function (array $input) use (&$cache): ToolResult {
        $cacheKey = md5($input['input']);
        
        if (isset($cache[$cacheKey])) {
            return ToolResult::success([
                'result' => $cache[$cacheKey],
                'cached' => true,
            ]);
        }
        
        $result = performExpensiveOperation($input['input']);
        $cache[$cacheKey] = $result;
        
        return ToolResult::success([
            'result' => $result,
            'cached' => false,
        ]);
    });
```

### From Definition

Load tools from JSON configuration:

```php
$definition = [
    'name' => 'weather',
    'description' => 'Get current weather',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'city' => [
                'type' => 'string',
                'description' => 'City name',
            ],
        ],
        'required' => ['city'],
    ],
];

$handler = function (array $input): string {
    return fetchWeather($input['city']);
};

$tool = Tool::fromDefinition($definition, $handler);
```

## Examples

### Example 1: Calculator Tool

```php
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations using PHP expressions')
    ->stringParam('expression', 'Mathematical expression (e.g., "5 + 3 * 2")')
    ->handler(function (array $input): ToolResult {
        $expr = $input['expression'];
        
        // Validate expression (only allow safe characters)
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return ToolResult::error('Invalid expression: only numbers and basic operators allowed');
        }
        
        try {
            $result = eval("return {$expr};");
            return ToolResult::success((string) $result);
        } catch (\Throwable $e) {
            return ToolResult::error("Calculation error: {$e->getMessage()}");
        }
    });
```

### Example 2: Database Query Tool

```php
$dbQuery = Tool::create('query_database')
    ->description('Query the database with SQL SELECT statements (read-only)')
    ->stringParam('query', 'SQL SELECT query')
    ->arrayParam('parameters', 'Query parameters for binding', false)
    ->handler(function (array $input) use ($pdo): ToolResult {
        // Validate it's a SELECT query
        if (!preg_match('/^\s*SELECT/i', $input['query'])) {
            return ToolResult::error('Only SELECT queries are allowed');
        }
        
        try {
            $stmt = $pdo->prepare($input['query']);
            $stmt->execute($input['parameters'] ?? []);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ToolResult::success([
                'rows' => $results,
                'count' => count($results),
            ]);
        } catch (\PDOException $e) {
            return ToolResult::error("Database error: {$e->getMessage()}");
        }
    });
```

### Example 3: HTTP Request Tool

```php
$httpRequest = Tool::create('http_request')
    ->description('Make HTTP requests to external APIs')
    ->stringParam('url', 'URL to request')
    ->stringParam('method', 'HTTP method', true, ['GET', 'POST', 'PUT', 'DELETE'])
    ->arrayParam('headers', 'HTTP headers', false)
    ->stringParam('body', 'Request body for POST/PUT', false)
    ->handler(function (array $input): ToolResult {
        $ch = curl_init($input['url']);
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $input['method']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if (!empty($input['headers'])) {
            $headers = [];
            foreach ($input['headers'] as $key => $value) {
                $headers[] = "{$key}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        if (!empty($input['body']) && in_array($input['method'], ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $input['body']);
        }
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ToolResult::error("HTTP request failed: {$error}");
        }
        
        return ToolResult::success([
            'status_code' => $statusCode,
            'body' => $response,
        ]);
    });
```

### Example 4: File Operations Tool

```php
$fileOps = Tool::create('file_operations')
    ->description('Perform file operations (read, write, list) in allowed directories')
    ->stringParam('operation', 'Operation to perform', true, ['read', 'write', 'list'])
    ->stringParam('path', 'File or directory path')
    ->stringParam('content', 'Content to write (for write operation)', false)
    ->handler(function (array $input) use ($allowedDir): ToolResult {
        $path = realpath($allowedDir . '/' . $input['path']);
        
        // Security: ensure path is within allowed directory
        if (strpos($path, realpath($allowedDir)) !== 0) {
            return ToolResult::error('Access denied: path outside allowed directory');
        }
        
        switch ($input['operation']) {
            case 'read':
                if (!file_exists($path)) {
                    return ToolResult::error('File not found');
                }
                return ToolResult::success(file_get_contents($path));
                
            case 'write':
                if (empty($input['content'])) {
                    return ToolResult::error('Content is required for write operation');
                }
                file_put_contents($path, $input['content']);
                return ToolResult::success('File written successfully');
                
            case 'list':
                if (!is_dir($path)) {
                    return ToolResult::error('Path is not a directory');
                }
                $files = array_diff(scandir($path), ['.', '..']);
                return ToolResult::success(['files' => array_values($files)]);
                
            default:
                return ToolResult::error('Unknown operation');
        }
    });
```

### Example 5: Multi-Tool Agent

```php
$registry = new ToolRegistry();

$registry->registerMany([
    $calculator,
    $httpRequest,
    $dbQuery,
    $fileOps,
]);

$agent = Agent::create($client)
    ->withTools($registry->all())
    ->withSystemPrompt('You are a helpful assistant with access to multiple tools. Choose the right tool for each task.')
    ->maxIterations(10)
    ->onToolExecution(function (string $tool, array $input, $result) {
        echo "🔧 {$tool}\n";
        echo "   Input: " . json_encode($input, JSON_PRETTY_PRINT) . "\n";
        echo "   Output: {$result->getContent()}\n\n";
    });

$response = $agent->run('Calculate 25 * 17, then save the result to result.txt');
```

## See Also

- [Agent Documentation](Agent.md)
- [Chains Documentation](Chains.md)
- [Examples Directory](../examples/)
- [Built-in Tools](BuiltInTools.md) _(coming soon)_

