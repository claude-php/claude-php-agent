# Code Generation Guide

This guide covers everything you need to know about generating PHP code using the CodeGenerationAgent.

## Table of Contents

- [Introduction](#introduction)
- [Basic Usage](#basic-usage)
- [Validation](#validation)
- [Retry Logic](#retry-logic)
- [Progress Tracking](#progress-tracking)
- [Templates](#templates)
- [Best Practices](#best-practices)

## Introduction

The `CodeGenerationAgent` uses Claude to generate PHP code from natural language descriptions. It includes automatic validation and retry logic to ensure high-quality output.

## Basic Usage

### Simple Code Generation

```php
use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$agent = new CodeGenerationAgent($client);

$result = $agent->generateComponent(
    'Create a Calculator class with add, subtract, multiply, and divide methods'
);

echo $result->getCode();
```

### With Validation

```php
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;

$validator = new ValidationCoordinator();
$validator->addValidator(new PHPSyntaxValidator());

$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $validator,
]);

$result = $agent->generateComponent('Create a Logger class');

if ($result->isValid()) {
    $result->saveToFile('Logger.php');
}
```

## Validation

### Available Validators

#### PHPSyntaxValidator

Fast syntax checking using `php -l`:

```php
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;

$validator = new PHPSyntaxValidator();
$result = $validator->validate($code);
```

#### StaticAnalysisValidator

PHPStan or Psalm integration:

```php
use ClaudeAgents\Validation\Validators\StaticAnalysisValidator;

// PHPStan
$phpstan = StaticAnalysisValidator::phpstan(level: 6);

// Psalm
$psalm = StaticAnalysisValidator::psalm();
```

#### CustomScriptValidator

Run custom validation scripts:

```php
use ClaudeAgents\Validation\Validators\CustomScriptValidator;

// PHPUnit
$phpunit = CustomScriptValidator::phpunit('tests/MyTest.php');

// Pest
$pest = CustomScriptValidator::pest('tests/Feature/MyTest.php');

// Custom script
$custom = new CustomScriptValidator('php validate.php {file}');
```

#### LLMReviewValidator

AI-powered code review:

```php
use ClaudeAgents\Validation\Validators\LLMReviewValidator;

$llmValidator = new LLMReviewValidator($client);
```

### Validator Priorities

Validators run in priority order (lower = earlier):

```php
$coordinator = new ValidationCoordinator();

$coordinator->addValidator(new PHPSyntaxValidator());         // Priority: 10
$coordinator->addValidator(StaticAnalysisValidator::phpstan()); // Priority: 20
$coordinator->addValidator($customValidator);                   // Priority: 30
$coordinator->addValidator(new LLMReviewValidator($client));   // Priority: 100
```

## Retry Logic

### How It Works

When validation fails, the agent:

1. Captures validation errors
2. Creates an improved prompt with error feedback
3. Regenerates the code
4. Validates again

### Configuration

```php
$agent = new CodeGenerationAgent($client, [
    'max_validation_retries' => 3, // Try up to 3 times after initial attempt
]);
```

### Monitoring Retries

```php
$agent->onRetry(function (int $attempt, array $errors) {
    echo "Retry attempt {$attempt}\n";
    echo "Errors: " . implode(', ', $errors) . "\n";
});
```

### Example Retry Flow

```
Attempt 1: Generate code
          ↓
       Validate
          ↓
    ❌ Failed (syntax error)
          ↓
Attempt 2: Generate with error feedback
          ↓
       Validate
          ↓
    ✅ Passed
```

## Progress Tracking

### Event Types

The agent emits various events during execution:

- `code.generating` - Code generation started
- `code.generated` - Code generation completed
- `validation.started` - Validation began
- `validation.passed` - Validation succeeded
- `validation.failed` - Validation failed
- `retry.attempt` - Retry attempt number
- `retry.reframing` - Prompt being reframed
- `component.completed` - Final component ready

### Tracking Progress

```php
$agent->onUpdate(function (string $type, array $data) {
    $timestamp = date('H:i:s');
    
    match ($type) {
        'code.generating' => echo "[{$timestamp}] 🔄 Generating code...\n",
        'code.generated' => echo "[{$timestamp}] ✅ Generated {$data['line_count']} lines\n",
        'validation.started' => echo "[{$timestamp}] 🔍 Validating...\n",
        'validation.passed' => echo "[{$timestamp}] ✅ Passed!\n",
        'validation.failed' => echo "[{$timestamp}] ❌ Failed: {$data['errors'][0]}\n",
        'retry.attempt' => echo "[{$timestamp}] 🔄 Retry {$data['attempt']}\n",
        'component.completed' => echo "[{$timestamp}] 🎉 Complete!\n",
        default => null,
    };
});
```

### Validation Callbacks

```php
$agent->onValidation(function (ValidationResult $result, int $attempt) {
    if ($result->isValid()) {
        echo "Validation passed on attempt {$attempt}\n";
    } else {
        echo "Validation failed:\n";
        foreach ($result->getErrors() as $error) {
            echo "  - {$error}\n";
        }
    }
});
```

## Templates

### Using ComponentTemplate

Generate code from templates:

```php
use ClaudeAgents\Generation\ComponentTemplate;

// Class template
$class = ComponentTemplate::classTemplate(
    name: 'User',
    namespace: 'App\\Models',
    options: [
        'properties' => [
            ['name' => 'id', 'type' => 'int', 'visibility' => 'private'],
            ['name' => 'name', 'type' => 'string', 'visibility' => 'private'],
        ],
        'methods' => [
            [
                'name' => 'getId',
                'return_type' => 'int',
                'body' => "        return \$this->id;\n",
            ],
        ],
    ]
);

// Interface template
$interface = ComponentTemplate::interfaceTemplate(
    name: 'RepositoryInterface',
    namespace: 'App\\Contracts'
);

// Service template with dependencies
$service = ComponentTemplate::serviceTemplate(
    name: 'UserService',
    namespace: 'App\\Services',
    options: [
        'dependencies' => [
            ['name' => 'repository', 'type' => 'UserRepository'],
            ['name' => 'logger', 'type' => 'LoggerInterface'],
        ],
    ]
);
```

## Best Practices

### 1. Be Specific in Descriptions

```php
// ❌ Vague
$result = $agent->generateComponent('Create a user class');

// ✅ Specific
$result = $agent->generateComponent(
    'Create a User class with properties: id (int), name (string), email (string). 
    Include getters, setters, and a constructor with all properties as parameters.'
);
```

### 2. Provide Context

```php
$context = [
    'namespace' => 'App\\Services',
    'framework' => 'Laravel',
    'php_version' => '8.2',
    'use_attributes' => true,
];

$result = $agent->generateComponent($description, $context);
```

### 3. Use Appropriate Models

```php
// For simple code: faster, cheaper
$agent = new CodeGenerationAgent($client, [
    'model' => 'claude-haiku-3-5',
]);

// For complex code: more capable
$agent = new CodeGenerationAgent($client, [
    'model' => 'claude-sonnet-4-5',
]);
```

### 4. Handle Errors

```php
use ClaudeAgents\Validation\Exceptions\MaxRetriesException;

try {
    $result = $agent->generateComponent($description);
    
    if ($result->isValid()) {
        $result->saveToFile('Generated.php');
    }
} catch (MaxRetriesException $e) {
    // Max retries exceeded
    $lastErrors = $e->getLastErrors();
    // Handle or log errors
} catch (\Throwable $e) {
    // Other errors
    error_log($e->getMessage());
}
```

### 5. Validate Thoroughly in Production

```php
$coordinator = new ValidationCoordinator();

// Syntax check (fast)
$coordinator->addValidator(new PHPSyntaxValidator());

// Static analysis (catch type errors)
$coordinator->addValidator(StaticAnalysisValidator::phpstan(level: 8));

// LLM review (catch logic issues)
$coordinator->addValidator(new LLMReviewValidator($client));

$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $coordinator,
    'max_validation_retries' => 3,
]);
```

### 6. Save and Version Generated Code

```php
$result = $agent->generateComponent($description);

if ($result->isValid()) {
    // Save with timestamp
    $filename = 'Generated_' . date('Ymd_His') . '.php';
    $result->saveToFile("generated/{$filename}");
    
    // Add to version control
    exec("git add generated/{$filename}");
    exec("git commit -m 'Generated: {$description}'");
}
```

## Examples

See the [examples directory](../examples/) for complete working examples:

- `code_generation_example.php` - Basic code generation
- `validation_example.php` - Validation system usage
- `sse_streaming_example.php` - Real-time streaming
- `component_generator_example.php` - Using templates

## See Also

- [Agentic Module Architecture](agentic-module-architecture.md)
- [Validation System](validation-system.md)
- [API Reference](agentic-module-architecture.md#api-reference)
