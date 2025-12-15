# Built-in Tools

The claude-php-agent framework includes a set of ready-to-use tools that provide common functionality without requiring custom implementation.

## Available Tools

- [Calculator Tool](#calculator-tool) - Mathematical calculations
- [DateTime Tool](#datetime-tool) - Date/time operations  
- [HTTP Tool](#http-tool) - HTTP requests to external APIs
- [FileSystem Tool](#filesystem-tool) - File and directory operations
- [Database Tool](#database-tool) - SQL query execution
- [Regex Tool](#regex-tool) - Regular expression operations

## Quick Start

### Using BuiltInToolRegistry

The easiest way to use built-in tools is through the `BuiltInToolRegistry`:

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Tools\BuiltIn\BuiltInToolRegistry;

// Create agent with all built-in tools
$registry = BuiltInToolRegistry::createWithAll();

$agent = Agent::create($client)
    ->withTools($registry->all())
    ->run('What is 25 * 17, and what will the date be 30 days from now?');
```

## Calculator Tool

Performs safe mathematical calculations with basic arithmetic operations.

### Features

- Basic arithmetic: `+`, `-`, `*`, `/`
- Parentheses for order of operations
- Decimal numbers
- Configurable precision
- Optional math functions (sqrt, sin, cos, etc.)

### Creation

```php
use ClaudeAgents\Tools\BuiltIn\CalculatorTool;

// Basic calculator (arithmetic only)
$calculator = CalculatorTool::create();

// Calculator with math functions
$calculator = CalculatorTool::create([
    'allow_functions' => true,
    'max_precision' => 10,
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `allow_functions` | bool | `false` | Enable math functions (sqrt, sin, cos, etc.) |
| `max_precision` | int | `10` | Maximum decimal precision for results |

### Usage

```php
$result = $calculator->execute(['expression' => '(25 * 17) + 42']);

if ($result->isSuccess()) {
    $data = json_decode($result->getContent(), true);
    echo "Result: " . $data['result']; // 467
}
```

### Examples

```php
// Simple arithmetic
$calculator->execute(['expression' => '5 + 3']); // 8

// Order of operations
$calculator->execute(['expression' => '(10 + 5) * 2']); // 30

// Decimals
$calculator->execute(['expression' => '10.5 * 2.5']); // 26.25

// Complex expression
$calculator->execute(['expression' => '((100 - 25) * 4) / 3']); // 100
```

### Security

- Only allows safe characters and operators
- Blocks dangerous functions (exec, eval, system, etc.)
- Validates expressions before evaluation
- Prevents code injection

## DateTime Tool

Handles date/time operations including formatting, parsing, arithmetic, and timezone conversions.

### Features

- Get current date/time
- Format dates in various formats
- Parse dates into components
- Add/subtract time intervals
- Calculate differences between dates
- Timezone support

### Creation

```php
use ClaudeAgents\Tools\BuiltIn\DateTimeTool;

// Basic datetime tool
$datetime = DateTimeTool::create();

// With custom defaults
$datetime = DateTimeTool::create([
    'default_timezone' => 'America/New_York',
    'default_format' => 'F j, Y g:i A',
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `default_timezone` | string | `'UTC'` | Default timezone for operations |
| `default_format` | string | `'Y-m-d H:i:s T'` | Default date format string |

### Operations

#### Now

Get current date/time:

```php
$result = $datetime->execute([
    'operation' => 'now',
    'timezone' => 'America/New_York',
    'format' => 'Y-m-d H:i:s T',
]);

// Returns:
// {
//     "datetime": "2024-01-15 10:30:00 EST",
//     "timestamp": 1705329000,
//     "timezone": "America/New_York"
// }
```

#### Format

Format a date:

```php
$result = $datetime->execute([
    'operation' => 'format',
    'date' => '2024-01-15',
    'format' => 'F j, Y',
]);

// Returns: {"formatted": "January 15, 2024", "timestamp": 1705276800}
```

#### Parse

Parse a date into components:

```php
$result = $datetime->execute([
    'operation' => 'parse',
    'date' => '2024-01-15 14:30:00',
]);

// Returns:
// {
//     "year": 2024,
//     "month": 1,
//     "day": 15,
//     "hour": 14,
//     "minute": 30,
//     "second": 0,
//     "day_of_week": "Monday",
//     "timestamp": 1705328400
// }
```

#### Add

Add time to a date:

```php
$result = $datetime->execute([
    'operation' => 'add',
    'date' => '2024-01-01',
    'interval' => '+30 days',
]);

// Returns: {"result": "2024-01-31 00:00:00 UTC", "timestamp": 1706659200}
```

#### Subtract

Subtract time from a date:

```php
$result = $datetime->execute([
    'operation' => 'subtract',
    'date' => '2024-01-31',
    'interval' => '15 days',
]);

// Returns: {"result": "2024-01-16 00:00:00 UTC", "timestamp": 1705363200}
```

#### Diff

Calculate difference between two dates:

```php
$result = $datetime->execute([
    'operation' => 'diff',
    'date' => '2024-01-01',
    'date2' => '2024-12-31',
]);

// Returns:
// {
//     "years": 0,
//     "months": 11,
//     "days": 30,
//     "hours": 0,
//     "minutes": 0,
//     "seconds": 0,
//     "total_days": 365,
//     "is_negative": false
// }
```

### Supported Timezones

All IANA timezone identifiers are supported, including:
- `UTC`
- `America/New_York`
- `America/Los_Angeles`
- `Europe/London`
- `Asia/Tokyo`
- And many more...

## HTTP Tool

Make HTTP requests to external APIs and web services.

### Features

- Multiple HTTP methods (GET, POST, PUT, DELETE, PATCH)
- Custom headers
- Request body support
- Response parsing
- Timeout configuration
- Domain whitelisting
- Response size limiting

### Creation

```php
use ClaudeAgents\Tools\BuiltIn\HTTPTool;

// Basic HTTP tool
$http = HTTPTool::create();

// With configuration
$http = HTTPTool::create([
    'timeout' => 30,
    'follow_redirects' => true,
    'allowed_domains' => ['api.example.com'],
    'max_response_size' => 1024 * 1024, // 1MB
    'user_agent' => 'MyApp/1.0',
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `timeout` | int | `30` | Request timeout in seconds |
| `follow_redirects` | bool | `true` | Follow HTTP redirects |
| `allowed_domains` | array | `[]` | Whitelist of allowed domains (empty = all allowed) |
| `max_response_size` | int | `1048576` | Maximum response size in bytes (1MB) |
| `user_agent` | string | `'ClaudeAgents-PHP-HTTPTool/1.0'` | User agent string |

### Usage

#### GET Request

```php
$result = $http->execute([
    'url' => 'https://api.example.com/users',
    'method' => 'GET',
    'headers' => [
        'Authorization' => 'Bearer token',
        'Accept' => 'application/json',
    ],
]);

if ($result->isSuccess()) {
    $data = json_decode($result->getContent(), true);
    echo "Status: " . $data['status_code'];
    echo "Body: " . $data['body'];
}
```

#### POST Request

```php
$result = $http->execute([
    'url' => 'https://api.example.com/users',
    'method' => 'POST',
    'headers' => [
        'Content-Type' => 'application/json',
    ],
    'body' => json_encode(['name' => 'John Doe']),
]);
```

### Response Format

```php
{
    "status_code": 200,
    "headers": {
        "content-type": "application/json",
        "content-length": "1234"
    },
    "body": "response content here",
    "content_type": "application/json",
    "time_seconds": 0.523,
    "truncated": false
}
```

### Security

- Domain whitelisting to restrict allowed endpoints
- Timeout limits to prevent hanging requests
- Response size limits to prevent memory issues
- Custom user agent for identification

## BuiltInToolRegistry

Convenient registry for managing built-in tools.

### Creating Registries

#### All Tools

```php
// All tools with defaults
$registry = BuiltInToolRegistry::createWithAll();

// All tools with custom config
$registry = BuiltInToolRegistry::createWithAll([
    'calculator' => ['allow_functions' => true],
    'datetime' => ['default_timezone' => 'America/New_York'],
    'http' => ['timeout' => 60],
]);

// Exclude specific tools
$registry = BuiltInToolRegistry::createWithAll([
    'http' => false, // Exclude HTTP tool
]);
```

#### Individual Tools

```php
// Calculator only
$registry = BuiltInToolRegistry::withCalculator();

// DateTime only
$registry = BuiltInToolRegistry::withDateTime();

// HTTP only
$registry = BuiltInToolRegistry::withHTTP();
```

#### Selective Tools

```php
// Choose specific tools
$registry = BuiltInToolRegistry::withTools(
    ['calculator', 'datetime'],
    [
        'calculator' => ['allow_functions' => true],
        'datetime' => ['default_timezone' => 'UTC'],
    ]
);
```

### Using with Agents

```php
$registry = BuiltInToolRegistry::createWithAll();

$agent = Agent::create($client)
    ->withTools($registry->all())
    ->run('Calculate 25 * 17 and tell me the current time in Tokyo');
```

## Complete Example

```php
<?php

require_once 'vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Tools\BuiltIn\BuiltInToolRegistry;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);

// Create registry with all tools
$registry = BuiltInToolRegistry::createWithAll([
    'calculator' => ['allow_functions' => false],
    'datetime' => ['default_timezone' => 'UTC'],
    'http' => ['timeout' => 30],
]);

// Create agent
$agent = Agent::create($client)
    ->withTools($registry->all())
    ->withSystemPrompt('You have access to calculator, datetime, and HTTP tools. Use them to answer questions accurately.')
    ->onToolExecution(function ($tool, $input, $result) {
        echo "Used tool: {$tool}\n";
    });

// Run tasks
$response = $agent->run(
    'Calculate 15% of 840, then tell me what date it will be 45 days from now'
);

echo $response->getAnswer();
```

## Testing

All built-in tools have comprehensive test coverage:

```bash
# Run all tool tests
./vendor/bin/phpunit tests/Unit/Tools/

# Run specific tool tests
./vendor/bin/phpunit tests/Unit/Tools/BuiltIn/CalculatorToolTest.php
./vendor/bin/phpunit tests/Unit/Tools/BuiltIn/DateTimeToolTest.php
./vendor/bin/phpunit tests/Unit/Tools/BuiltIn/BuiltInToolRegistryTest.php
```

## Error Handling

All tools return `ToolResult` objects that indicate success or error:

```php
$result = $tool->execute($input);

if ($result->isSuccess()) {
    $data = json_decode($result->getContent(), true);
    // Process successful result
} else {
    // Handle error
    echo "Error: " . $result->getContent();
}
```

Common error scenarios:
- Invalid input parameters
- Invalid expressions (calculator)
- Invalid dates/timezones (datetime)
- Network failures (HTTP)
- Timeout exceeded (HTTP)
- Domain not allowed (HTTP)

## See Also

- [Tools Documentation](Tools.md) - Creating custom tools
- [Agent Documentation](Agent.md) - Using tools with agents
- [Examples](../examples/builtin_tools_example.php) - Complete examples

