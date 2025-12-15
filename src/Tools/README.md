# Tools Directory

This directory contains the core Tools system for claude-php-agent, including base classes, contracts, and built-in tools.

## Directory Structure

```
Tools/
├── Tool.php                    # Main tool implementation with fluent API
├── ToolResult.php             # Tool execution result wrapper
├── ToolRegistry.php           # Registry for managing multiple tools
└── BuiltIn/                   # Built-in tools ready to use
    ├── CalculatorTool.php     # Mathematical calculations
    ├── DateTimeTool.php       # Date/time operations
    ├── HTTPTool.php           # HTTP requests
    └── BuiltInToolRegistry.php # Registry for built-in tools
```

## Quick Start

### Using Built-in Tools

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Tools\BuiltIn\BuiltInToolRegistry;

// Get all built-in tools
$registry = BuiltInToolRegistry::createWithAll();

// Use with an agent
$agent = Agent::create($client)
    ->withTools($registry->all())
    ->run('What is 25 * 17?');
```

### Creating Custom Tools

```php
use ClaudeAgents\Tools\Tool;

$myTool = Tool::create('my_tool')
    ->description('What this tool does')
    ->stringParam('input', 'Description of input parameter')
    ->handler(function (array $input): string {
        // Your logic here
        return "Result: " . $input['input'];
    });
```

## Core Components

### Tool.php

The main tool implementation providing:
- Fluent API for building tools
- Parameter type definitions (string, number, boolean, array)
- Handler execution with error handling
- Conversion to/from API format
- Integration with chains via `Tool::fromChain()`

**Key Methods:**
- `Tool::create(string $name)` - Create new tool
- `->description(string)` - Set description
- `->stringParam()`, `->numberParam()`, etc. - Add parameters
- `->handler(callable)` - Set execution handler
- `->execute(array)` - Execute the tool
- `Tool::fromChain()` - Create tool from chain
- `Tool::fromDefinition()` - Create from definition array

### ToolResult.php

Represents the result of tool execution:
- Success/error state
- Content getter
- API format conversion
- Factory methods for easy creation

**Key Methods:**
- `ToolResult::success(string|array)` - Create success result
- `ToolResult::error(string)` - Create error result
- `ToolResult::fromException(Throwable)` - Create from exception
- `->isSuccess()`, `->isError()` - Check status
- `->getContent()` - Get result content
- `->toApiFormat(string)` - Convert to API format

### ToolRegistry.php

Manages multiple tools:
- Register/remove tools
- Lookup by name
- Execute tools by name
- Export definitions for API

**Key Methods:**
- `->register(ToolInterface)` - Add tool
- `->registerMany(array)` - Add multiple tools
- `->get(string)` - Get tool by name
- `->has(string)` - Check if tool exists
- `->all()` - Get all tools
- `->execute(string, array)` - Execute tool by name
- `->toDefinitions()` - Get API definitions

## Built-in Tools

### CalculatorTool

Safe mathematical calculations:
- Basic arithmetic (+, -, *, /)
- Parentheses and order of operations
- Decimal precision control
- Optional math functions
- Security validation

```php
use ClaudeAgents\Tools\BuiltIn\CalculatorTool;

$calc = CalculatorTool::create([
    'allow_functions' => false,
    'max_precision' => 10,
]);

$result = $calc->execute(['expression' => '(25 * 17) + 42']);
```

### DateTimeTool

Date/time operations:
- Current time in any timezone
- Date formatting and parsing
- Date arithmetic (add/subtract)
- Date differences
- Timezone conversions

```php
use ClaudeAgents\Tools\BuiltIn\DateTimeTool;

$datetime = DateTimeTool::create([
    'default_timezone' => 'UTC',
    'default_format' => 'Y-m-d H:i:s T',
]);

$result = $datetime->execute([
    'operation' => 'now',
    'timezone' => 'America/New_York',
]);
```

### HTTPTool

HTTP requests to external APIs:
- Multiple HTTP methods
- Custom headers
- Request body support
- Timeout configuration
- Domain whitelisting
- Response size limits

```php
use ClaudeAgents\Tools\BuiltIn\HTTPTool;

$http = HTTPTool::create([
    'timeout' => 30,
    'allowed_domains' => ['api.example.com'],
]);

$result = $http->execute([
    'url' => 'https://api.example.com/data',
    'method' => 'GET',
    'headers' => ['Authorization' => 'Bearer token'],
]);
```

### BuiltInToolRegistry

Convenient registry for built-in tools:

```php
use ClaudeAgents\Tools\BuiltIn\BuiltInToolRegistry;

// All tools
$registry = BuiltInToolRegistry::createWithAll();

// Specific tools only
$registry = BuiltInToolRegistry::withTools(['calculator', 'datetime']);

// Individual tool registries
$calcRegistry = BuiltInToolRegistry::withCalculator();
$dateRegistry = BuiltInToolRegistry::withDateTime();
$httpRegistry = BuiltInToolRegistry::withHTTP();
```

## Test Coverage

All components are fully tested:

```bash
# Run all tool tests
./vendor/bin/phpunit tests/Unit/Tools/

# Specific test suites
./vendor/bin/phpunit tests/Unit/Tools/ToolTest.php
./vendor/bin/phpunit tests/Unit/Tools/ToolResultTest.php
./vendor/bin/phpunit tests/Unit/Tools/ToolRegistryTest.php
./vendor/bin/phpunit tests/Unit/Tools/BuiltIn/
```

**Current Stats:**
- 120 tests
- 331 assertions
- 100% pass rate
- Zero linting errors

## Documentation

Complete documentation available:

- **[Tools Guide](../../docs/Tools.md)** - Comprehensive guide to creating and using tools
- **[Built-in Tools Guide](../../docs/BuiltInTools.md)** - Documentation for all built-in tools
- **[Examples](../../examples/builtin_tools_example.php)** - Working examples

## Usage Patterns

### Pattern 1: Quick Setup with Built-ins

```php
$registry = BuiltInToolRegistry::createWithAll();
$agent = Agent::create($client)->withTools($registry->all());
```

### Pattern 2: Custom Tool

```php
$tool = Tool::create('custom')
    ->description('My custom tool')
    ->stringParam('input', 'Input parameter')
    ->handler(fn($input) => processInput($input['input']));

$agent->withTool($tool);
```

### Pattern 3: Mixed Tools

```php
$registry = BuiltInToolRegistry::withCalculator();
$registry->register($myCustomTool);
$agent->withTools($registry->all());
```

### Pattern 4: Chain as Tool

```php
$chain = LLMChain::create($client)
    ->withPromptTemplate($template);

$tool = Tool::fromChain($chain, 'analyze', 'Analyzes text');
$agent->withTool($tool);
```

## Best Practices

1. **Clear Descriptions**: Write detailed descriptions to help agents choose the right tool
2. **Input Validation**: Validate all inputs in your handler
3. **Error Handling**: Return `ToolResult::error()` for validation failures
4. **Structured Output**: Return structured data when possible
5. **Security**: Implement safety measures for tools with side effects
6. **Testing**: Write tests for custom tools
7. **Documentation**: Document tool behavior and parameters

## Security Considerations

### For Built-in Tools
- Calculator: Expression validation, dangerous function blocking
- HTTP: Domain whitelisting, timeout limits, size limits
- DateTime: Input validation, timezone validation

### For Custom Tools
- Always validate inputs
- Implement rate limiting for expensive operations
- Use timeouts for external calls
- Sanitize outputs
- Log security-relevant actions
- Document security implications

## Contributing

When adding new tools:

1. Extend `Tool` or implement `ToolInterface`
2. Add comprehensive tests
3. Document in `docs/Tools.md` or `docs/BuiltInTools.md`
4. Add usage examples
5. Ensure no linting errors
6. Follow PSR-12 coding standards

## See Also

- [Agent Documentation](../../docs/Agent.md)
- [Chains Documentation](../../docs/Chains.md)
- [Examples Directory](../../examples/)
- [Test Directory](../../tests/Unit/Tools/)

