# Validation System

The validation system provides flexible, multi-stage code validation for the Claude PHP Agent framework.

## Quick Start

```php
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;

$coordinator = new ValidationCoordinator();
$coordinator->addValidator(new PHPSyntaxValidator());

$result = $coordinator->validate($code);

if ($result->isValid()) {
    echo "✅ Code is valid\n";
} else {
    foreach ($result->getErrors() as $error) {
        echo "❌ {$error}\n";
    }
}
```

## Components

### Core Classes

- **ValidationCoordinator** - Orchestrates multiple validators
- **ValidationResult** - Encapsulates validation outcome
- **ValidatorInterface** - Base interface for all validators

### Built-in Validators

- **PHPSyntaxValidator** - Fast syntax checking with `php -l`
- **StaticAnalysisValidator** - PHPStan/Psalm integration
- **ComponentInstantiationValidator** - Runtime validation by class instantiation
- **CustomScriptValidator** - Execute custom validation scripts
- **LLMReviewValidator** - AI-powered code review

### Services

- **ComponentValidationService** - Standalone service for component validation by instantiation
- **ClassLoader** - Dynamically loads PHP classes from code strings

### Exceptions

- **ValidationException** - Base validation exception
- **MaxRetriesException** - Thrown when max retries exceeded
- **ComponentValidationException** - Component validation specific errors
- **ClassLoadException** - Class loading errors

## Features

- ✅ **Composable** - Combine multiple validators
- 🎯 **Prioritized** - Validators run in priority order
- 🔧 **Extensible** - Easy to create custom validators
- 💾 **Cacheable** - Avoid redundant validation
- 📊 **Informative** - Rich error and warning information

## Documentation

- [Validation System Guide](../../docs/validation-system.md)
- [Agentic Module Architecture](../../docs/agentic-module-architecture.md)

## Component Validation

The Component Validation system provides runtime validation by dynamically loading and instantiating PHP classes. Inspired by Langflow's validation approach, it validates components by:

1. Extracting the class name from generated code
2. Dynamically loading the class (using temp files or eval)
3. Instantiating the class to trigger constructor validation
4. Catching comprehensive exception types

### Standalone Usage

```php
use ClaudeAgents\Validation\ComponentValidationService;

$service = new ComponentValidationService([
    'load_strategy' => 'temp_file',  // or 'eval'
    'constructor_args' => [],         // Args to pass to constructor
]);

$code = <<<'PHP'
<?php

class MyComponent
{
    public function __construct()
    {
        if (!extension_loaded('json')) {
            throw new \RuntimeException('JSON extension required');
        }
    }
}
PHP;

$result = $service->validate($code);

if ($result->isValid()) {
    $className = $result->getMetadata()['class_name'];
    echo "✅ Component {$className} validated successfully\n";
} else {
    foreach ($result->getErrors() as $error) {
        echo "❌ {$error}\n";
    }
}
```

### Integration with ValidationCoordinator

```php
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;

$coordinator = new ValidationCoordinator();

// Add validators in priority order
$coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
$coordinator->addValidator(new ComponentInstantiationValidator(['priority' => 50]));

$result = $coordinator->validate($code);
```

### Configuration Options

```php
$service = new ComponentValidationService([
    // Load Strategy
    'load_strategy' => 'temp_file',   // 'temp_file' (default) or 'eval'
    'allow_eval' => false,             // Security: must be explicitly enabled for eval
    
    // Temp File Options
    'temp_dir' => sys_get_temp_dir(),  // Directory for temp files
    'cleanup_temp_files' => true,      // Auto-cleanup temp files
    
    // Validation Options
    'constructor_args' => [],          // Args to pass to constructor
    'timeout' => 5.0,                  // Timeout in seconds
    'catch_fatal_errors' => true,      // Catch fatal errors
]);
```

### Load Strategies

**Temp File (Default - Safer):**
- Writes code to temporary file
- Uses `require_once` to load the file
- Proper file cleanup
- Isolated namespace to avoid collisions

**Eval (Requires opt-in):**
- Uses `eval()` to execute code directly
- Similar to Python's `exec()`
- Must be explicitly enabled via `allow_eval` option
- Less safe but simpler for certain use cases

### Validation Metadata

Successful validations include rich metadata:

```php
$result->getMetadata() = [
    'validator' => 'component_validation',
    'class_name' => 'MyComponent',
    'namespace' => 'MyApp\Components',
    'fully_qualified_class_name' => 'DynamicValidation\Temp12345678\MyComponent',
    'load_strategy' => 'temp_file',
    'instantiation_time_ms' => 12.34,
    'constructor_args_count' => 0,
    'instance_class' => 'DynamicValidation\Temp12345678\MyComponent',
];
```

### Error Handling

The validator catches comprehensive exception types:

- `\ParseError` - Syntax errors in code
- `\Error` - Fatal errors during instantiation
- `\TypeError` - Type errors (wrong constructor arguments)
- `\ArgumentCountError` - Missing required constructor arguments
- `\Exception` - General exceptions from constructor
- `\Throwable` - Catch-all for any throwable

### Security Considerations

- Eval strategy requires explicit opt-in via `allow_eval` option
- Temp files use unique names and restricted permissions (0600)
- Generated class names include random suffix to avoid collisions
- All temp files are cleaned up after validation
- Code is never executed with elevated privileges

## Examples

See [examples/validation_example.php](../../examples/validation_example.php) for complete examples.
