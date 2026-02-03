# Component Validation Service

## Overview

The Component Validation Service provides runtime validation of PHP components by dynamically loading and instantiating classes. Inspired by Langflow's component validation approach, it validates components beyond syntax and static analysis by actually executing constructor code.

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                 Component Validation Flow                │
└─────────────────────────────────────────────────────────┘
                            │
                            ▼
            ┌───────────────────────────────┐
            │  ComponentValidationService   │
            └───────────────────────────────┘
                     │              │
         ┌───────────┴──────┐      │
         ▼                  ▼      ▼
┌─────────────────┐  ┌──────────────────┐
│  Class Loader   │  │ Class Extraction │
└─────────────────┘  └──────────────────┘
         │
    ┌────┴────┐
    ▼         ▼
┌──────┐  ┌──────┐
│ Temp │  │ Eval │
│ File │  │      │
└──────┘  └──────┘
```

## Components

### 1. ComponentValidationService

Core service that orchestrates the validation process.

**File:** `src/Validation/ComponentValidationService.php`

**Key Features:**
- Extracts class names using token parsing (primary) and regex (fallback)
- Dynamically loads classes via ClassLoader
- Instantiates classes to trigger constructor validation
- Catches comprehensive exception types
- Returns rich ValidationResult with metadata

**Example:**
```php
$service = new ComponentValidationService([
    'load_strategy' => 'temp_file',
    'constructor_args' => ['arg1', 'arg2'],
]);

$result = $service->validate($code);
```

### 2. ClassLoader

Handles dynamic class loading with two strategies.

**File:** `src/Validation/ClassLoader.php`

**Strategies:**

1. **Temp File (Default):**
   - Writes code to temporary file with unique namespace
   - Uses `require_once` to load the file
   - Safer and more isolated
   - Automatic cleanup

2. **Eval (Opt-in):**
   - Uses `eval()` to execute code directly
   - Must be explicitly enabled via `allow_eval` option
   - Simpler but less safe

**Example:**
```php
$loader = new ClassLoader([
    'load_strategy' => 'temp_file',
    'temp_dir' => '/custom/temp/dir',
]);

$fqcn = $loader->loadClass($code, 'MyClass');
$instance = new $fqcn();
```

### 3. ComponentInstantiationValidator

Validator implementation for ValidationCoordinator integration.

**File:** `src/Validation/Validators/ComponentInstantiationValidator.php`

**Integration:**
```php
$coordinator = new ValidationCoordinator();
$coordinator->addValidator(new ComponentInstantiationValidator([
    'priority' => 50,
]));
```

### 4. Exception Classes

**ComponentValidationException:**
- Thrown when component validation fails
- Contains class name, original code, and exception details
- Provides code snippets for debugging

**ClassLoadException:**
- Thrown when class loading fails
- Contains load strategy and temp file path information

## Configuration

### ComponentValidationService Options

```php
[
    // Load Strategy
    'load_strategy' => 'temp_file',   // 'temp_file' or 'eval'
    'allow_eval' => false,             // Must be true to use eval
    
    // Temp File Options
    'temp_dir' => sys_get_temp_dir(), // Directory for temp files
    'cleanup_temp_files' => true,      // Auto-cleanup
    
    // Validation Options
    'constructor_args' => [],          // Args to pass to constructor
    'timeout' => 5.0,                  // Timeout in seconds
    'catch_fatal_errors' => true,      // Catch fatal errors
]
```

### ComponentInstantiationValidator Options

```php
[
    // All ComponentValidationService options, plus:
    'priority' => 50,    // Validator priority (lower runs first)
    'enabled' => true,   // Enable/disable validator
]
```

## Usage Examples

### Standalone Validation

```php
use ClaudeAgents\Validation\ComponentValidationService;

$service = new ComponentValidationService();

$code = <<<'PHP'
<?php

class MyComponent
{
    public function __construct()
    {
        if (!extension_loaded('json')) {
            throw new \RuntimeException('JSON required');
        }
    }
}
PHP;

$result = $service->validate($code);

if ($result->isValid()) {
    echo "✅ Valid component\n";
} else {
    foreach ($result->getErrors() as $error) {
        echo "❌ {$error}\n";
    }
}
```

### With ValidationCoordinator

```php
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;

$coordinator = new ValidationCoordinator([
    'stop_on_first_failure' => false,
]);

$coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
$coordinator->addValidator(new ComponentInstantiationValidator(['priority' => 50]));

$result = $coordinator->validate($code);
```

### With Constructor Arguments

```php
$service = new ComponentValidationService([
    'constructor_args' => [
        'config' => ['api_key' => 'test', 'endpoint' => 'https://api.example.com'],
    ],
]);

$result = $service->validate($componentCode);
```

### Using Eval Strategy

```php
$service = new ComponentValidationService([
    'load_strategy' => 'eval',
    'allow_eval' => true,  // Must explicitly enable
]);

$result = $service->validate($code);
```

## Validation Metadata

### Success Metadata

```php
[
    'validator' => 'component_validation',
    'class_name' => 'MyComponent',
    'namespace' => 'MyApp\Components',
    'fully_qualified_class_name' => 'DynamicValidation\Temp12345678\MyComponent',
    'load_strategy' => 'temp_file',
    'instantiation_time_ms' => 12.34,
    'constructor_args_count' => 2,
    'instance_class' => 'DynamicValidation\Temp12345678\MyComponent',
]
```

### Failure Metadata

```php
[
    'validator' => 'component_validation',
    'class_name' => 'FailingComponent',
    'exception_type' => 'RuntimeException',
    'exception_message' => 'Component initialization failed',
    'load_strategy' => 'temp_file',
    'duration_ms' => 5.67,
    'code_snippet' => '...',
]
```

## Error Handling

The validator catches comprehensive exception types:

- `\ParseError` - Syntax errors in code
- `\Error` - Fatal errors during instantiation
- `\TypeError` - Type errors (wrong constructor arguments)
- `\ArgumentCountError` - Missing required constructor arguments
- `\Exception` - General exceptions from constructor
- `\Throwable` - Catch-all for any throwable

All exceptions are converted to ValidationResult with detailed error information.

## Security Considerations

1. **Eval Strategy:**
   - Requires explicit opt-in via `allow_eval` option
   - Disabled by default for security

2. **Temp Files:**
   - Use unique names to avoid conflicts
   - Created with restricted permissions (0600)
   - Automatic cleanup after validation

3. **Namespace Isolation:**
   - Generated classes use unique namespaces
   - Class names include random suffix
   - Prevents conflicts with existing classes

4. **No Privilege Escalation:**
   - Code is never executed with elevated privileges
   - Runs in same context as calling code

## Performance

- **Class Extraction:** Fast token parsing with regex fallback
- **Temp Files:** Optimized buffering and deferred cleanup
- **Caching:** Supports ValidationCoordinator result caching
- **Timeout:** Configurable timeout protection

Typical validation times:
- Simple class: 0.5-2ms
- Complex class: 2-10ms
- With multiple validators: 30-50ms

## Testing

Comprehensive test suite with 63 tests and 137 assertions:

```bash
# Run all validation tests
./vendor/bin/phpunit tests/Unit/Validation/

# Run specific test suites
./vendor/bin/phpunit tests/Unit/Validation/ClassLoaderTest.php
./vendor/bin/phpunit tests/Unit/Validation/ComponentValidationServiceTest.php
./vendor/bin/phpunit tests/Unit/Validation/ComponentInstantiationValidatorTest.php
```

## Examples

See `examples/component_validation_example.php` for comprehensive usage examples.

## Inspiration

This implementation is inspired by Langflow's component validation approach:
- Dynamic class creation from code strings
- Instantiation-based validation
- Comprehensive exception handling
- Rich error reporting

## Key Differences from Langflow

1. **Language:** PHP vs Python constraints
2. **Load Strategy:** Temp files + require vs exec()
3. **Type Safety:** PHP strict typing provides compile-time validation
4. **Security:** Eval requires explicit opt-in in PHP
5. **Error Handling:** PHP distinguishes Error and Exception

## See Also

- [Validation System](../src/Validation/README.md)
- [Validation System Guide](./validation-system.md)
- [Examples](../examples/component_validation_example.php)
