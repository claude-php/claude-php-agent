# Component Validation Tutorial: Runtime Validation by Instantiation

## Introduction

This tutorial will guide you through using the Component Validation Service to validate PHP components at runtime by dynamically loading and instantiating classes. This provides validation beyond syntax checking by actually executing constructor code and catching runtime errors.

By the end of this tutorial, you'll be able to:

- Validate PHP components by instantiation
- Use ComponentValidationService standalone
- Integrate with ValidationCoordinator
- Handle constructor validation and errors
- Choose between temp file and eval strategies
- Extract detailed validation metadata

## Prerequisites

- PHP 8.1 or higher
- Composer
- Basic understanding of PHP classes and constructors
- Familiarity with validation concepts

## Table of Contents

1. [Understanding Component Validation](#understanding-component-validation)
2. [Setup and Installation](#setup-and-installation)
3. [Tutorial 1: Basic Validation](#tutorial-1-basic-validation)
4. [Tutorial 2: Constructor Validation](#tutorial-2-constructor-validation)
5. [Tutorial 3: ValidationCoordinator Integration](#tutorial-3-validationcoordinator-integration)
6. [Tutorial 4: Advanced Patterns](#tutorial-4-advanced-patterns)
7. [Tutorial 5: Production Usage](#tutorial-5-production-usage)
8. [Tutorial 6: Testing Validated Components](#tutorial-6-testing-validated-components)
9. [Common Patterns](#common-patterns)
10. [Troubleshooting](#troubleshooting)
11. [Next Steps](#next-steps)

## Understanding Component Validation

Traditional PHP validation includes:
- **Syntax Validation** - Checks if code is syntactically correct (`php -l`)
- **Static Analysis** - Analyzes code without execution (PHPStan, Psalm)

Component Validation adds:
- **Runtime Instantiation** - Actually creates instances of classes
- **Constructor Execution** - Runs constructor logic to catch validation errors
- **Dependency Verification** - Ensures required extensions and dependencies exist

### When to Use Component Validation

**Use when:**
- Generating code with AI and need runtime verification
- Validating components have proper initialization logic
- Checking for missing extensions or dependencies
- Ensuring constructors don't throw unexpected errors
- Building component libraries that require runtime checks

**Don't use when:**
- Simple syntax checking is sufficient
- Components have complex external dependencies (databases, APIs)
- Constructors have side effects you want to avoid
- Performance is critical (instantiation adds overhead)

### How It Works

```
┌──────────────────┐
│  Generated Code  │
└────────┬─────────┘
         │
         ▼
┌──────────────────────────┐
│ Extract Class Name       │  ← Token parsing + regex
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│ Load Class Dynamically   │  ← Temp file or eval()
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│ Instantiate Class        │  ← new $className()
└────────┬─────────────────┘
         │
         ├─ Success → ValidationResult::success()
         │
         └─ Error → ValidationResult::failure()
```

## Setup and Installation

The Component Validation Service is included in `claude-php/agent` v0.8.0+.

### Install/Update

```bash
composer require claude-php/agent:^0.8.0
# or
composer update claude-php/agent
```

### Verify Installation

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;

// Check version
if (class_exists(ComponentValidationService::class)) {
    echo "✓ Component Validation Service available\n";
} else {
    echo "✗ Please update to v0.8.0+\n";
}
```

## Tutorial 1: Basic Validation

Let's start by validating a simple PHP class.

### Step 1: Create Your First Validation

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;

// Create the service
$service = new ComponentValidationService();

// Code to validate
$code = <<<'PHP'
<?php

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
    
    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }
}
PHP;

// Validate the code
$result = $service->validate($code);

// Check result
if ($result->isValid()) {
    echo "✓ Code is valid!\n";
    echo "Class: " . $result->getMetadata()['class_name'] . "\n";
} else {
    echo "✗ Validation failed:\n";
    foreach ($result->getErrors() as $error) {
        echo "  - $error\n";
    }
}
```

**Output:**
```
✓ Code is valid!
Class: Calculator
```

### Step 2: Understanding the Result

```php
// Get detailed metadata
$metadata = $result->getMetadata();

print_r([
    'Valid' => $result->isValid(),
    'Class Name' => $metadata['class_name'] ?? 'N/A',
    'Namespace' => $metadata['namespace'] ?? 'N/A',
    'Load Strategy' => $metadata['load_strategy'] ?? 'N/A',
    'Instantiation Time' => $metadata['instantiation_time_ms'] ?? 'N/A',
]);
```

### Step 3: Handling Validation Failures

```php
// Code with syntax error
$badCode = <<<'PHP'
<?php

class BrokenClass
{
    public function test()
    {
        return "missing semicolon"
    }
}
PHP;

$result = $service->validate($badCode);

if ($result->isFailed()) {
    echo "Validation failed:\n";
    foreach ($result->getErrors() as $error) {
        echo "  - $error\n";
    }
    
    // Get error metadata
    $metadata = $result->getMetadata();
    echo "\nException Type: " . ($metadata['exception_type'] ?? 'Unknown') . "\n";
}
```

## Tutorial 2: Constructor Validation

Now let's validate classes with constructor logic.

### Step 1: Validating Constructor Requirements

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;

$code = <<<'PHP'
<?php

class DatabaseConnection
{
    private string $host;
    
    public function __construct(string $host = 'localhost')
    {
        if (empty($host)) {
            throw new \InvalidArgumentException('Host cannot be empty');
        }
        $this->host = $host;
    }
}
PHP;

// Create service with constructor arguments
$service = new ComponentValidationService([
    'constructor_args' => ['localhost'],
]);

$result = $service->validate($code);

if ($result->isValid()) {
    echo "✓ Constructor validation passed!\n";
    echo "Args provided: " . $result->getMetadata()['constructor_args_count'] . "\n";
}
```

### Step 2: Testing Invalid Constructor Arguments

```php
// This will fail validation
$invalidService = new ComponentValidationService([
    'constructor_args' => [''], // Empty string - invalid!
]);

$result = $invalidService->validate($code);

if ($result->isFailed()) {
    echo "✗ Constructor validation failed (expected):\n";
    foreach ($result->getErrors() as $error) {
        echo "  - $error\n";
    }
}
```

### Step 3: Extension Dependencies

```php
$code = <<<'PHP'
<?php

class JsonHandler
{
    public function __construct()
    {
        if (!extension_loaded('json')) {
            throw new \RuntimeException('JSON extension is required');
        }
    }
    
    public function encode(array $data): string
    {
        return json_encode($data);
    }
}
PHP;

$service = new ComponentValidationService();
$result = $service->validate($code);

// Will pass because json extension is loaded
if ($result->isValid()) {
    echo "✓ Extension check passed!\n";
}
```

## Tutorial 3: ValidationCoordinator Integration

Combine multiple validators for comprehensive validation.

### Step 1: Setup Multi-Validator Pipeline

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;

// Create coordinator
$coordinator = new ValidationCoordinator([
    'stop_on_first_failure' => false, // Run all validators
]);

// Add validators in priority order
$coordinator->addValidator(new PHPSyntaxValidator([
    'priority' => 10, // Runs first
]));

$coordinator->addValidator(new ComponentInstantiationValidator([
    'priority' => 50, // Runs after syntax check
]));

// Validate code
$code = <<<'PHP'
<?php

namespace App\Services;

class EmailValidator
{
    public function validate(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
PHP;

$result = $coordinator->validate($code);

echo "Validation Result:\n";
echo "- Valid: " . ($result->isValid() ? 'Yes' : 'No') . "\n";
echo "- Validators Run: " . $result->getMetadata()['validator_count'] . "\n";
echo "- Total Time: " . $result->getMetadata()['duration_ms'] . "ms\n";
```

### Step 2: Handling Validation Chain Failures

```php
// Code that passes syntax but fails instantiation
$code = <<<'PHP'
<?php

class ProblematicClass
{
    public function __construct()
    {
        // Syntax is fine, but throws at runtime
        throw new \Exception('Initialization failed');
    }
}
PHP;

$result = $coordinator->validate($code);

if ($result->isFailed()) {
    echo "Validation chain failed:\n";
    echo "- Error count: " . $result->getErrorCount() . "\n";
    echo "- Validators run: " . $result->getMetadata()['validator_count'] . "\n";
    
    foreach ($result->getErrors() as $error) {
        echo "  - $error\n";
    }
}
```

### Step 3: Configuring Stop Behavior

```php
// Stop on first failure (faster)
$fastCoordinator = new ValidationCoordinator([
    'stop_on_first_failure' => true,
]);

$fastCoordinator
    ->addValidator(new PHPSyntaxValidator(['priority' => 10]))
    ->addValidator(new ComponentInstantiationValidator(['priority' => 50]));

// If syntax fails, instantiation won't run
$result = $fastCoordinator->validate($brokenSyntaxCode);
echo "Validators run: " . $result->getMetadata()['validator_count'] . "\n"; // Will be 1
```

## Tutorial 4: Advanced Patterns

Learn advanced validation techniques.

### Step 1: Context-Based Validation

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;

$code = <<<'PHP'
<?php

class ConfigurableComponent
{
    public function __construct(array $config)
    {
        if (empty($config['api_key'])) {
            throw new \InvalidArgumentException('API key required');
        }
    }
}
PHP;

$service = new ComponentValidationService();

// Pass constructor args via context
$result = $service->validate($code, [
    'constructor_args' => [
        ['api_key' => 'test_key_123']
    ],
]);

if ($result->isValid()) {
    echo "✓ Context-based validation passed!\n";
}
```

### Step 2: Expected Class Name Verification

```php
// Verify the generated class name matches expectations
$result = $service->validate($code, [
    'expected_class_name' => 'ConfigurableComponent',
]);

if ($result->isValid()) {
    echo "✓ Class name verified!\n";
} else {
    echo "✗ Class name mismatch\n";
}
```

### Step 3: Load Strategy Selection

```php
// Default: Temp file strategy (safer)
$tempFileService = new ComponentValidationService([
    'load_strategy' => 'temp_file',
    'cleanup_temp_files' => true,
]);

// Optional: Eval strategy (requires opt-in)
$evalService = new ComponentValidationService([
    'load_strategy' => 'eval',
    'allow_eval' => true, // Must explicitly enable
]);

$result = $evalService->validate($code);
echo "Strategy used: " . $result->getMetadata()['load_strategy'] . "\n";
```

## Tutorial 5: Production Usage

Best practices for production environments.

### Step 1: Error Handling

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;
use ClaudeAgents\Validation\Exceptions\ComponentValidationException;

function validateComponent(string $code): bool
{
    $service = new ComponentValidationService();
    
    try {
        $result = $service->validate($code);
        
        if ($result->isValid()) {
            // Log success
            error_log(sprintf(
                'Validation succeeded: %s in %sms',
                $result->getMetadata()['class_name'],
                $result->getMetadata()['instantiation_time_ms']
            ));
            return true;
        } else {
            // Log validation errors
            error_log('Validation failed: ' . implode(', ', $result->getErrors()));
            return false;
        }
    } catch (ComponentValidationException $e) {
        // Handle validation exceptions
        error_log(sprintf(
            'Validation exception: %s (class: %s)',
            $e->getMessage(),
            $e->getClassName()
        ));
        return false;
    } catch (\Throwable $e) {
        // Handle unexpected errors
        error_log('Unexpected error: ' . $e->getMessage());
        return false;
    }
}
```

### Step 2: Metadata Extraction

```php
function extractValidationMetadata(ValidationResult $result): array
{
    $metadata = $result->getMetadata();
    
    return [
        'valid' => $result->isValid(),
        'class_name' => $metadata['class_name'] ?? null,
        'namespace' => $metadata['namespace'] ?? null,
        'load_strategy' => $metadata['load_strategy'] ?? null,
        'instantiation_time_ms' => $metadata['instantiation_time_ms'] ?? null,
        'error_count' => $result->getErrorCount(),
        'warning_count' => $result->getWarningCount(),
        'errors' => $result->getErrors(),
    ];
}

// Use it
$result = $service->validate($code);
$info = extractValidationMetadata($result);

// Store or log the metadata
file_put_contents(
    'validation-log.json',
    json_encode($info, JSON_PRETTY_PRINT) . "\n",
    FILE_APPEND
);
```

### Step 3: Performance Monitoring

```php
class ValidationMonitor
{
    private array $metrics = [];
    
    public function validateWithMetrics(
        ComponentValidationService $service,
        string $code
    ): ValidationResult {
        $start = microtime(true);
        $memoryBefore = memory_get_usage(true);
        
        $result = $service->validate($code);
        
        $duration = microtime(true) - $start;
        $memoryUsed = memory_get_usage(true) - $memoryBefore;
        
        $this->metrics[] = [
            'duration_s' => $duration,
            'memory_bytes' => $memoryUsed,
            'valid' => $result->isValid(),
            'timestamp' => time(),
        ];
        
        return $result;
    }
    
    public function getAverageMetrics(): array
    {
        $count = count($this->metrics);
        if ($count === 0) return [];
        
        return [
            'avg_duration_ms' => array_sum(array_column($this->metrics, 'duration_s')) / $count * 1000,
            'avg_memory_kb' => array_sum(array_column($this->metrics, 'memory_bytes')) / $count / 1024,
            'total_validations' => $count,
            'success_rate' => array_sum(array_column($this->metrics, 'valid')) / $count * 100,
        ];
    }
}

// Use it
$monitor = new ValidationMonitor();
$result = $monitor->validateWithMetrics($service, $code);
print_r($monitor->getAverageMetrics());
```

## Tutorial 6: Testing Validated Components

Write tests for your validation logic.

### Step 1: Unit Testing

```php
<?php

use PHPUnit\Framework\TestCase;
use ClaudeAgents\Validation\ComponentValidationService;

class ComponentValidationTest extends TestCase
{
    private ComponentValidationService $service;
    
    protected function setUp(): void
    {
        $this->service = new ComponentValidationService();
    }
    
    public function test_validates_simple_class(): void
    {
        $code = <<<'PHP'
<?php
class SimpleClass {}
PHP;
        
        $result = $this->service->validate($code);
        
        $this->assertTrue($result->isValid());
        $this->assertSame('SimpleClass', $result->getMetadata()['class_name']);
    }
    
    public function test_detects_constructor_errors(): void
    {
        $code = <<<'PHP'
<?php
class FailingClass
{
    public function __construct()
    {
        throw new \Exception('Error');
    }
}
PHP;
        
        $result = $this->service->validate($code);
        
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }
}
```

### Step 2: Feature Testing

```php
public function test_validation_workflow(): void
{
    // Setup
    $coordinator = new ValidationCoordinator();
    $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
    $coordinator->addValidator(new ComponentInstantiationValidator(['priority' => 50]));
    
    // Generate or load code
    $code = $this->loadTestCode('valid-component.php');
    
    // Validate
    $result = $coordinator->validate($code);
    
    // Assert
    $this->assertTrue($result->isValid());
    $this->assertGreaterThanOrEqual(2, $result->getMetadata()['validator_count']);
}
```

### Step 3: Integration Testing

```php
public function test_validates_ai_generated_code(): void
{
    // Generate code with AI
    $agent = new CodeGenerationAgent($this->client, [
        'validation_coordinator' => $this->coordinator,
    ]);
    
    $result = $agent->generateComponent('Create a Calculator class');
    
    // Validation should have run automatically
    $this->assertTrue($result->isValid());
    $this->assertTrue($result->wasValidated());
    
    // Verify validation metadata
    $validation = $result->getValidation();
    $this->assertArrayHasKey('class_name', $validation->getMetadata());
}
```

## Common Patterns

### Pattern 1: Validation Pipeline

```php
// Build a comprehensive validation pipeline
class ValidationPipeline
{
    private ValidationCoordinator $coordinator;
    
    public function __construct()
    {
        $this->coordinator = new ValidationCoordinator([
            'stop_on_first_failure' => false,
        ]);
        
        // Add all validators
        $this->coordinator
            ->addValidator(new PHPSyntaxValidator(['priority' => 10]))
            ->addValidator(new ComponentInstantiationValidator(['priority' => 50]));
    }
    
    public function validateAndSave(string $code, string $filepath): bool
    {
        $result = $this->coordinator->validate($code);
        
        if ($result->isValid()) {
            file_put_contents($filepath, $code);
            return true;
        }
        
        return false;
    }
}
```

### Pattern 2: Conditional Constructor Args

```php
// Provide different constructor args based on class type
function getConstructorArgs(string $className): array
{
    return match ($className) {
        'DatabaseConnection' => ['localhost', 'root', 'password'],
        'CacheManager' => [['driver' => 'redis']],
        'Logger' => ['/var/log/app.log'],
        default => [],
    };
}

// Use it
$classInfo = $service->extractClassInfo($code);
$constructorArgs = getConstructorArgs($classInfo['class_name']);

$result = $service->validate($code, [
    'constructor_args' => $constructorArgs,
]);
```

### Pattern 3: Validation with Fallback

```php
// Try instantiation validation, fall back to syntax only
function safeValidate(string $code): ValidationResult
{
    $instantiationService = new ComponentValidationService();
    
    try {
        $result = $instantiationService->validate($code);
        
        if ($result->isValid()) {
            return $result;
        }
        
        // Fall back to syntax only
        $syntaxValidator = new PHPSyntaxValidator();
        return $syntaxValidator->validate($code);
        
    } catch (\Throwable $e) {
        // Last resort: syntax validation
        $syntaxValidator = new PHPSyntaxValidator();
        return $syntaxValidator->validate($code);
    }
}
```

## Troubleshooting

### Problem: "Class not found after loading"

**Cause:** Class name extraction failed or namespace mismatch.

**Solution:**
```php
// Debug class extraction
$classInfo = $service->extractClassInfo($code);
var_dump($classInfo); // Check if class_name is correct

// Verify namespace handling
if ($classInfo['namespace']) {
    echo "Using namespace: " . $classInfo['namespace'] . "\n";
}
```

### Problem: "Eval strategy not allowed"

**Cause:** Trying to use eval without explicit opt-in.

**Solution:**
```php
// Must explicitly enable eval
$service = new ComponentValidationService([
    'load_strategy' => 'eval',
    'allow_eval' => true, // Required!
]);
```

### Problem: "Argument count error during instantiation"

**Cause:** Constructor requires arguments but none provided.

**Solution:**
```php
// Provide constructor arguments
$service = new ComponentValidationService([
    'constructor_args' => ['required_arg_1', 'required_arg_2'],
]);

// Or pass via context
$result = $service->validate($code, [
    'constructor_args' => ['arg1', 'arg2'],
]);
```

### Problem: Slow validation performance

**Cause:** Temp file creation overhead or complex constructors.

**Solution:**
```php
// 1. Use eval strategy (if safe)
$service = new ComponentValidationService([
    'load_strategy' => 'eval',
    'allow_eval' => true,
]);

// 2. Run instantiation validation selectively
$coordinator = new ValidationCoordinator();
$coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));

// Only add instantiation for critical validations
if ($needsRuntimeValidation) {
    $coordinator->addValidator(new ComponentInstantiationValidator(['priority' => 50]));
}
```

## Next Steps

### Related Tutorials

- **[Code Generation Tutorial](./CodeGeneration_Tutorial.md)** - Generate and validate code with AI
- **[Testing Strategies Tutorial](./TestingStrategies_Tutorial.md)** - Comprehensive testing approaches
- **[Production Patterns Tutorial](./ProductionPatterns_Tutorial.md)** - Deploy validated components

### Further Reading

- [Component Validation Service Documentation](../component-validation-service.md)
- [Validation System Guide](../validation-system.md)
- [Best Practices](../BestPractices.md)

### Example Code

All examples from this tutorial are available in:
- `examples/tutorials/component-validation/`
- `examples/component_validation_example.php`

### What You've Learned

✓ Validate PHP components at runtime
✓ Use ComponentValidationService standalone
✓ Integrate with ValidationCoordinator
✓ Handle constructor validation
✓ Choose appropriate load strategies
✓ Extract and use validation metadata
✓ Implement production-ready patterns
✓ Write tests for validation logic

**Ready for more?** Continue with the [Code Generation Tutorial](./CodeGeneration_Tutorial.md) to learn how to generate and validate code with AI!

---

*Tutorial Version: 1.0*
*Framework Version: v0.8.0+*
*Last Updated: February 2026*
