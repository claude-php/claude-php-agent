# Validation System

The validation system provides flexible, multi-stage code validation with support for syntax checking, static analysis, custom scripts, and LLM-based review.

## Table of Contents

- [Overview](#overview)
- [Core Concepts](#core-concepts)
- [Built-in Validators](#built-in-validators)
- [Custom Validators](#custom-validators)
- [Validation Coordinator](#validation-coordinator)
- [Validation Results](#validation-results)
- [Best Practices](#best-practices)

## Overview

The validation system is designed around these principles:

- **Composable** - Combine multiple validators
- **Prioritized** - Validators run in priority order
- **Flexible** - Easy to create custom validators
- **Cacheable** - Avoid redundant validation
- **Informative** - Rich error and warning information

## Core Concepts

### Validator Interface

All validators implement `ValidatorInterface`:

```php
interface ValidatorInterface
{
    public function validate(string $code, array $context = []): ValidationResult;
    public function getName(): string;
    public function canHandle(string $code): bool;
    public function getPriority(): int;
}
```

### Validation Result

Encapsulates validation outcome:

```php
class ValidationResult
{
    public function isValid(): bool;
    public function getErrors(): array;
    public function getWarnings(): array;
    public function getMetadata(): array;
    // ... more methods
}
```

### Priority System

Validators execute in priority order (lower numbers first):

- **Priority 10** - Fast syntax checks
- **Priority 20** - Static analysis
- **Priority 30** - Custom scripts/tests
- **Priority 100** - Slow, comprehensive checks

## Built-in Validators

### PHPSyntaxValidator

Fast syntax checking using `php -l`.

```php
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;

$validator = new PHPSyntaxValidator([
    'php_binary' => '/usr/bin/php', // Custom PHP binary
    'priority' => 10,
]);

$result = $validator->validate($code);
```

**Features:**
- Very fast (< 100ms typically)
- No dependencies
- Catches basic syntax errors

**Use when:**
- You need quick feedback
- Catching obvious errors early
- Building validation pipelines

### StaticAnalysisValidator

Integrates PHPStan or Psalm for static analysis.

```php
use ClaudeAgents\Validation\Validators\StaticAnalysisValidator;

// PHPStan with level 6
$phpstan = StaticAnalysisValidator::phpstan(6, [
    'config_file' => 'phpstan.neon',
]);

// Psalm
$psalm = StaticAnalysisValidator::psalm([
    'config_file' => 'psalm.xml',
]);

$result = $phpstan->validate($code);
```

**Features:**
- Catches type errors
- Detects undefined variables
- Finds potential bugs

**Use when:**
- Generating production code
- Need type safety guarantees
- Working with strict type systems

### CustomScriptValidator

Execute custom validation scripts.

```php
use ClaudeAgents\Validation\Validators\CustomScriptValidator;

// PHPUnit tests
$phpunit = CustomScriptValidator::phpunit('tests/GeneratedTest.php');

// Pest tests
$pest = CustomScriptValidator::pest('tests/Feature/GeneratedTest.php');

// Custom PHP script
$custom = CustomScriptValidator::phpScript('scripts/validate.php');

// Arbitrary command
$validator = new CustomScriptValidator(
    'vendor/bin/php-cs-fixer fix {file} --dry-run',
    ['priority' => 30]
);
```

**Features:**
- Run any validation command
- Capture stdout/stderr
- Configurable working directory
- Timeout support

**Use when:**
- Running unit tests on generated code
- Checking code style
- Custom business logic validation

### LLMReviewValidator

AI-powered code review using Claude.

```php
use ClaudeAgents\Validation\Validators\LLMReviewValidator;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$validator = new LLMReviewValidator($client, [
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 2048,
    'priority' => 100, // Run last (slowest)
]);

$result = $validator->validate($code);
```

**Features:**
- Checks best practices
- Identifies security issues
- Reviews logic and design
- Provides improvement suggestions

**Use when:**
- Need qualitative feedback
- Checking for subtle issues
- Want improvement suggestions
- Other validators pass but code seems off

## Custom Validators

### Creating a Custom Validator

```php
use ClaudeAgents\Validation\Contracts\ValidatorInterface;
use ClaudeAgents\Validation\ValidationResult;

class SecurityValidator implements ValidatorInterface
{
    private array $dangerousFunctions = [
        'eval', 'exec', 'shell_exec', 'system',
        'passthru', 'popen', 'proc_open',
    ];
    
    public function validate(string $code, array $context = []): ValidationResult
    {
        $errors = [];
        $warnings = [];
        
        // Check for dangerous functions
        foreach ($this->dangerousFunctions as $func) {
            if (preg_match("/\b{$func}\b/", $code)) {
                $errors[] = "Dangerous function '{$func}' detected";
            }
        }
        
        // Check for SQL injection patterns
        if (preg_match('/\$_(GET|POST|REQUEST).*?(mysql_query|query|execute)/', $code)) {
            $errors[] = "Potential SQL injection vulnerability";
        }
        
        // Warnings for risky patterns
        if (preg_match('/extract\s*\(/', $code)) {
            $warnings[] = "extract() can be dangerous, consider alternatives";
        }
        
        return empty($errors)
            ? ValidationResult::success($warnings, ['validator' => 'security'])
            : ValidationResult::failure($errors, $warnings, ['validator' => 'security']);
    }
    
    public function getName(): string
    {
        return 'security';
    }
    
    public function canHandle(string $code): bool
    {
        return str_contains($code, '<?php');
    }
    
    public function getPriority(): int
    {
        return 15; // Run after syntax, before static analysis
    }
}
```

### Using Custom Validators

```php
$coordinator = new ValidationCoordinator();
$coordinator->addValidator(new SecurityValidator());
$coordinator->addValidator(new PHPSyntaxValidator());

$result = $coordinator->validate($code);
```

## Validation Coordinator

### Basic Usage

```php
use ClaudeAgents\Validation\ValidationCoordinator;

$coordinator = new ValidationCoordinator([
    'stop_on_first_failure' => true,  // Stop after first failure
    'cache_results' => true,           // Cache validation results
]);

$coordinator->addValidator($validator1);
$coordinator->addValidator($validator2);

$result = $coordinator->validate($code);
```

### Options

```php
[
    'stop_on_first_failure' => true,  // Stop on first failure or run all?
    'cache_results' => true,           // Enable result caching
    'logger' => $psrLogger,            // PSR-3 logger for debugging
]
```

### Managing Validators

```php
// Add single validator
$coordinator->addValidator($validator);

// Add multiple validators
$coordinator->addValidators([$validator1, $validator2]);

// Remove by name
$coordinator->removeValidator('php_syntax');

// Get all validators
$validators = $coordinator->getValidators();

// Clear cache
$coordinator->clearCache();
```

### Validation with Context

```php
$context = [
    'purpose' => 'API endpoint',
    'framework' => 'Laravel',
    'requirements' => ['authentication', 'validation'],
];

$result = $coordinator->validate($code, $context);
```

### Stop on First Failure

```php
// Stop immediately on failure (faster)
$coordinator = new ValidationCoordinator([
    'stop_on_first_failure' => true,
]);

// Run all validators regardless (comprehensive)
$coordinator = new ValidationCoordinator([
    'stop_on_first_failure' => false,
]);
```

## Validation Results

### Checking Results

```php
$result = $coordinator->validate($code);

if ($result->isValid()) {
    echo "✅ Code is valid\n";
} else {
    echo "❌ Code has errors\n";
}

// Check for errors/warnings
if ($result->hasErrors()) {
    foreach ($result->getErrors() as $error) {
        echo "Error: {$error}\n";
    }
}

if ($result->hasWarnings()) {
    foreach ($result->getWarnings() as $warning) {
        echo "Warning: {$warning}\n";
    }
}
```

### Result Metadata

```php
$metadata = $result->getMetadata();

echo "Validator: {$metadata['validator']}\n";
echo "Duration: {$metadata['duration_ms']}ms\n";
echo "Validator count: {$metadata['validator_count']}\n";
```

### Merging Results

```php
$result1 = $validator1->validate($code);
$result2 = $validator2->validate($code);

$merged = $result1->merge($result2);

// Merged result is valid only if both are valid
// Errors and warnings are combined
```

### Serialization

```php
// To array
$array = $result->toArray();

// To JSON
$json = $result->toJson();

// Summary
$summary = $result->getSummary();
// "Validation passed" or "Validation failed with 3 error(s)"
```

## Best Practices

### 1. Order Validators by Speed

```php
// Fast validators first
$coordinator->addValidator(new PHPSyntaxValidator());          // ~50ms
$coordinator->addValidator(StaticAnalysisValidator::phpstan()); // ~500ms
$coordinator->addValidator($customTests);                        // ~2s
$coordinator->addValidator(new LLMReviewValidator($client));    // ~5s
```

### 2. Use Stop-on-Failure for Fast Feedback

```php
$coordinator = new ValidationCoordinator([
    'stop_on_first_failure' => true,
]);
```

### 3. Enable Caching

```php
$coordinator = new ValidationCoordinator([
    'cache_results' => true,
]);
```

### 4. Provide Context

```php
$context = [
    'file_type' => 'controller',
    'framework' => 'Laravel',
    'requires' => ['authorization'],
];

$result = $coordinator->validate($code, $context);
```

### 5. Handle Validator Exceptions

```php
try {
    $result = $coordinator->validate($code);
} catch (\Throwable $e) {
    // Validator threw exception
    error_log("Validation error: {$e->getMessage()}");
}
```

### 6. Use Appropriate Validation Levels

```php
// Development - fast validation
$dev = new ValidationCoordinator();
$dev->addValidator(new PHPSyntaxValidator());

// Staging - moderate validation
$staging = new ValidationCoordinator();
$staging->addValidator(new PHPSyntaxValidator());
$staging->addValidator(StaticAnalysisValidator::phpstan(6));

// Production - thorough validation
$prod = new ValidationCoordinator();
$prod->addValidator(new PHPSyntaxValidator());
$prod->addValidator(StaticAnalysisValidator::phpstan(8));
$prod->addValidator($securityValidator);
$prod->addValidator(new LLMReviewValidator($client));
```

## Examples

See [validation_example.php](../examples/validation_example.php) for complete working examples.

## See Also

- [Agentic Module Architecture](agentic-module-architecture.md)
- [Code Generation Guide](code-generation-guide.md)
- [API Reference](agentic-module-architecture.md#api-reference)
