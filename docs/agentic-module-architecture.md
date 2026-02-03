# Agentic Module Architecture

The Agentic Module Architecture provides a sophisticated AI-powered code generation and validation system for the Claude PHP Agent framework. Inspired by Langflow's AI assistant, it enables natural language-driven code generation with robust validation, retry logic, and real-time streaming feedback.

## Table of Contents

- [Overview](#overview)
- [Core Components](#core-components)
- [Architecture](#architecture)
- [Getting Started](#getting-started)
- [Advanced Usage](#advanced-usage)
- [API Reference](#api-reference)
- [Best Practices](#best-practices)

## Overview

The Agentic Module Architecture consists of three main systems:

1. **Code Generation** - Generate PHP code from natural language descriptions using Claude
2. **Validation System** - Multi-stage validation with automatic retry loops
3. **SSE Streaming** - Real-time progress updates via Server-Sent Events

### Key Features

- ✅ **Automatic Validation** - Multiple validators run in priority order
- 🔄 **Retry Logic** - Automatic retry with improved prompts on validation failure
- 📡 **Real-time Streaming** - SSE support for web clients
- 🎯 **Flexible Validators** - Syntax, static analysis, custom scripts, and LLM review
- 🔧 **Extensible** - Easy to add custom validators and generators
- 📊 **Progress Tracking** - Detailed callbacks for monitoring

## Core Components

### CodeGenerationAgent

The main agent for generating code with validation retry loops.

```php
use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new CodeGenerationAgent($client, [
    'max_validation_retries' => 3,
    'max_tokens' => 4096,
]);

$result = $agent->generateComponent('Create a UserRepository class');
```

### ValidationCoordinator

Orchestrates multiple validators to check code quality.

```php
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\StaticAnalysisValidator;

$coordinator = new ValidationCoordinator();
$coordinator->addValidator(new PHPSyntaxValidator());
$coordinator->addValidator(StaticAnalysisValidator::phpstan(level: 6));

$result = $coordinator->validate($code);
```

### Validators

Multiple validator types are available:

- **PHPSyntaxValidator** - Fast syntax checking with `php -l`
- **StaticAnalysisValidator** - PHPStan or Psalm integration
- **CustomScriptValidator** - Run custom validation scripts
- **LLMReviewValidator** - AI-powered code review

### SSE Streaming

Real-time progress updates for web clients.

```php
use ClaudeAgents\Streaming\SSEStreamAdapter;

$adapter = new SSEStreamAdapter();
$agent->onUpdate($adapter->createCodeGenerationCallback());
```

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    User Request                          │
└─────────────────┬───────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────┐
│              CodeGenerationAgent                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │  1. Generate Code (Claude API)                    │  │
│  │  2. Validate Code (ValidationCoordinator)         │  │
│  │  3. If Invalid: Retry with improved prompt        │  │
│  │  4. If Valid: Return ComponentResult              │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────┬───────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────┐
│           ValidationCoordinator                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Priority 10: PHPSyntaxValidator                  │  │
│  │  Priority 20: StaticAnalysisValidator             │  │
│  │  Priority 30: CustomScriptValidator               │  │
│  │  Priority 100: LLMReviewValidator                 │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────┬───────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────┐
│                SSE Stream (optional)                     │
│  • code.generating                                       │
│  • code.generated                                        │
│  • validation.started                                    │
│  • validation.passed / failed                            │
│  • retry.attempt                                         │
│  • component.completed                                   │
└─────────────────────────────────────────────────────────┘
```

## Getting Started

### Installation

The Agentic Module Architecture is included in `claude-php/agent`. No additional installation is required.

### Basic Example

```php
<?php

require 'vendor/autoload.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Setup validation
$validator = new ValidationCoordinator();
$validator->addValidator(new PHPSyntaxValidator());

// Create agent
$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $validator,
    'max_validation_retries' => 3,
]);

// Generate code
$result = $agent->generateComponent(
    'Create a Calculator class with add, subtract, multiply, and divide methods'
);

if ($result->isValid()) {
    echo $result->getCode();
    $result->saveToFile('Calculator.php');
} else {
    foreach ($result->getValidation()->getErrors() as $error) {
        echo "Error: {$error}\n";
    }
}
```

### With Progress Tracking

```php
$agent->onUpdate(function (string $type, array $data) {
    match ($type) {
        'code.generating' => echo "🔄 Generating...\n",
        'code.generated' => echo "✅ Generated {$data['line_count']} lines\n",
        'validation.started' => echo "🔍 Validating...\n",
        'validation.passed' => echo "✅ Validation passed!\n",
        'validation.failed' => echo "❌ Validation failed\n",
        'retry.attempt' => echo "🔄 Retry {$data['attempt']}/{$data['max_attempts']}\n",
        default => null,
    };
});

$result = $agent->generateComponent('Create a Logger class');
```

## Advanced Usage

### Custom Validators

Create your own validator by implementing `ValidatorInterface`:

```php
use ClaudeAgents\Validation\Contracts\ValidatorInterface;
use ClaudeAgents\Validation\ValidationResult;

class SecurityValidator implements ValidatorInterface
{
    public function validate(string $code, array $context = []): ValidationResult
    {
        $errors = [];
        
        // Check for dangerous functions
        if (preg_match('/\b(eval|exec|shell_exec)\b/', $code)) {
            $errors[] = 'Dangerous function detected';
        }
        
        return empty($errors)
            ? ValidationResult::success()
            : ValidationResult::failure($errors);
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
        return 15; // Run after syntax check, before static analysis
    }
}

// Use it
$coordinator->addValidator(new SecurityValidator());
```

### SSE Streaming for Web

Create an endpoint for real-time updates:

```php
// stream.php
use ClaudeAgents\Streaming\SSEServer;
use ClaudeAgents\Streaming\SSEStreamAdapter;

SSEServer::setupHeaders();

$adapter = new SSEStreamAdapter();
$agent->onUpdate($adapter->createCodeGenerationCallback());

$result = $agent->generateComponent($_GET['description']);
```

Client-side JavaScript:

```javascript
const eventSource = new EventSource('stream.php?description=Create+a+Logger');

eventSource.addEventListener('code.generated', (e) => {
    const data = JSON.parse(e.data);
    console.log(`Generated ${data.line_count} lines`);
});

eventSource.addEventListener('component.completed', (e) => {
    console.log('Complete!');
    eventSource.close();
});
```

### Multiple Validators with Priorities

```php
$coordinator = new ValidationCoordinator([
    'stop_on_first_failure' => false, // Run all validators
]);

// Priority 10 - runs first (fast fail)
$coordinator->addValidator(new PHPSyntaxValidator());

// Priority 20 - static analysis
$coordinator->addValidator(StaticAnalysisValidator::phpstan(level: 6));

// Priority 30 - custom tests
$coordinator->addValidator(CustomScriptValidator::phpunit('tests/GeneratedTest.php'));

// Priority 100 - LLM review (slow but thorough)
$coordinator->addValidator(new LLMReviewValidator($client));
```

### Component Templates

Use templates for common patterns:

```php
use ClaudeAgents\Generation\ComponentTemplate;

// Generate a service class
$code = ComponentTemplate::serviceTemplate(
    name: 'EmailService',
    namespace: 'App\\Services',
    options: [
        'dependencies' => [
            ['name' => 'mailer', 'type' => 'MailerInterface'],
            ['name' => 'logger', 'type' => 'LoggerInterface'],
        ],
    ]
);

// Generate an interface
$code = ComponentTemplate::interfaceTemplate(
    name: 'CacheInterface',
    namespace: 'App\\Contracts',
    options: [
        'methods' => [
            ['name' => 'get', 'params' => [['type' => 'string', 'name' => 'key']], 'return_type' => 'mixed'],
            ['name' => 'set', 'params' => [['type' => 'string', 'name' => 'key'], ['type' => 'mixed', 'name' => 'value']], 'return_type' => 'bool'],
        ],
    ]
);
```

## API Reference

### CodeGenerationAgent

#### Constructor Options

```php
[
    'max_validation_retries' => 3,        // Maximum retry attempts
    'validation_coordinator' => null,     // Custom ValidationCoordinator
    'enable_streaming' => true,           // Enable progress updates
    'name' => 'code_generation_agent',    // Agent name
    'model' => 'claude-sonnet-4-5',      // Claude model
    'max_tokens' => 4096,                 // Max tokens per response
]
```

#### Methods

- `generateComponent(string $description, array $context = []): ComponentResult`
- `onUpdate(callable $callback): self`
- `onValidation(callable $callback): self`
- `onRetry(callable $callback): self`
- `getValidationCoordinator(): ValidationCoordinator`

### ValidationCoordinator

#### Methods

- `addValidator(ValidatorInterface $validator): self`
- `addValidators(array $validators): self`
- `removeValidator(string $name): self`
- `validate(string $code, array $context = []): ValidationResult`
- `clearCache(): void`

### ValidationResult

#### Methods

- `isValid(): bool`
- `isFailed(): bool`
- `getErrors(): array`
- `getWarnings(): array`
- `getMetadata(): array`
- `merge(ValidationResult $other): self`
- `toArray(): array`
- `getSummary(): string`

### ComponentResult

#### Methods

- `getCode(): string`
- `getValidation(): ValidationResult`
- `isValid(): bool`
- `saveToFile(string $path): bool`
- `getSummary(): string`
- `toArray(): array`

## Best Practices

### 1. Use Appropriate Validators

```php
// For production code
$coordinator->addValidator(new PHPSyntaxValidator());
$coordinator->addValidator(StaticAnalysisValidator::phpstan(level: 8));
$coordinator->addValidator(new LLMReviewValidator($client));

// For quick prototyping
$coordinator->addValidator(new PHPSyntaxValidator());
```

### 2. Set Reasonable Retry Limits

```php
// For simple code: lower retries
$agent = new CodeGenerationAgent($client, [
    'max_validation_retries' => 1,
]);

// For complex code: more retries
$agent = new CodeGenerationAgent($client, [
    'max_validation_retries' => 5,
]);
```

### 3. Provide Context

```php
$context = [
    'framework' => 'Laravel',
    'php_version' => '8.2',
    'requirements' => ['validation', 'authorization'],
];

$result = $agent->generateComponent($description, $context);
```

### 4. Handle Errors Gracefully

```php
use ClaudeAgents\Validation\Exceptions\MaxRetriesException;

try {
    $result = $agent->generateComponent($description);
} catch (MaxRetriesException $e) {
    // Log the failure and last errors
    error_log('Code generation failed after max retries');
    error_log('Last errors: ' . implode(', ', $e->getLastErrors()));
}
```

### 5. Cache Validation Results

```php
$coordinator = new ValidationCoordinator([
    'cache_results' => true, // Enable caching
]);
```

## See Also

- [Code Generation Guide](code-generation-guide.md)
- [Validation System](validation-system.md)
- [Examples](../examples/)
