# Code Generation Tutorial: AI-Powered Code Generation with Validation

## Introduction

This tutorial will guide you through using the CodeGenerationAgent to generate PHP code with AI, validate it comprehensively, and handle generation failures with automatic retries.

By the end of this tutorial, you'll be able to:

- Generate PHP code from natural language descriptions
- Set up comprehensive validation pipelines
- Handle generation retries and failures
- Use component templates for structured output
- Test generated code automatically
- Integrate code generation into CI/CD pipelines

## Prerequisites

- PHP 8.1 or higher
- Composer  
- Claude API key (Anthropic)
- Basic understanding of code generation concepts
- Completed [Component Validation Tutorial](./ComponentValidation_Tutorial.md) (recommended)

## Table of Contents

1. [Understanding Code Generation](#understanding-code-generation)
2. [Setup and Installation](#setup-and-installation)
3. [Tutorial 1: Basic Code Generation](#tutorial-1-basic-code-generation)
4. [Tutorial 2: Validation Pipeline](#tutorial-2-validation-pipeline)
5. [Tutorial 3: Retry Logic](#tutorial-3-retry-logic)
6. [Tutorial 4: Component Templates](#tutorial-4-component-templates)
7. [Tutorial 5: Complex Components](#tutorial-5-complex-components)
8. [Tutorial 6: Testing Generated Code](#tutorial-6-testing-generated-code)
9. [Tutorial 7: CI/CD Integration](#tutorial-7-cicd-integration)
10. [Common Patterns](#common-patterns)
11. [Troubleshooting](#troubleshooting)
12. [Next Steps](#next-steps)

## Understanding Code Generation

AI-powered code generation transforms natural language descriptions into working code.

### The Generation Flow

```
Description → AI Generation → Validation → Retry (if needed) → Valid Code

"Create a Calculator class"
         ↓
   CodeGenerationAgent
         ↓
   Generated PHP Code
         ↓
   ValidationCoordinator
         ↓
   ┌─────────┴─────────┐
   ↓                   ↓
 Valid              Invalid
   ↓                   ↓
 Return           Retry with
 Result           Feedback
```

### When to Use Code Generation

**Use when:**
- Generating boilerplate code
- Creating components from specifications
- Building test fixtures
- Scaffolding new features
- Prototyping quickly

**Don't use when:**
- Security-critical code
- Code requires deep domain knowledge
- Generated code can't be validated
- Human review is impractical

## Setup and Installation

### Install Framework

```bash
composer require claude-php/agent:^0.8.0
```

### Set API Key

```bash
export ANTHROPIC_API_KEY=your_api_key_here
```

## Tutorial 1: Basic Code Generation

Generate your first component with AI.

### Step 1: Simple Generation

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create code generation agent
$agent = new CodeGenerationAgent($client);

// Generate code
$description = 'Create a Calculator class with add, subtract, multiply, and divide methods';

$result = $agent->generateComponent($description);

// Get the code
echo "Generated Code:\n";
echo str_repeat('=', 50) . "\n";
echo $result->getCode();
echo str_repeat('=', 50) . "\n";

// Check if valid
if ($result->isValid()) {
    echo "✓ Code generated successfully!\n";
} else {
    echo "✗ Validation failed:\n";
    foreach ($result->getValidation()->getErrors() as $error) {
        echo "  - $error\n";
    }
}
```

### Step 2: Save Generated Code

```php
// Save to file
$filename = 'Calculator.php';
$saved = $result->saveToFile($filename);

if ($saved) {
    echo "✓ Code saved to $filename\n";
}
```

### Step 3: View Generation Metadata

```php
$metadata = $result->getMetadata();

echo "Generation Metadata:\n";
echo "- Attempts: " . $metadata['attempts'] . "\n";
echo "- Code Length: " . strlen($result->getCode()) . " bytes\n";
echo "- Line Count: " . substr_count($result->getCode(), "\n") . " lines\n";
```

## Tutorial 2: Validation Pipeline

Add comprehensive validation to code generation.

### Step 1: Setup Validators

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create validation coordinator
$coordinator = new ValidationCoordinator([
    'stop_on_first_failure' => false, // Run all validators
]);

// Add validators in priority order
$coordinator
    ->addValidator(new PHPSyntaxValidator(['priority' => 10]))
    ->addValidator(new ComponentInstantiationValidator(['priority' => 50]));

// Create agent with validation
$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $coordinator,
]);
```

### Step 2: Generate with Validation

```php
$description = 'Create a Logger class with info(), warning(), and error() methods';

$result = $agent->generateComponent($description);

// Get validation details
$validation = $result->getValidation();

echo "Validation Results:\n";
echo "- Valid: " . ($validation->isValid() ? 'Yes' : 'No') . "\n";
echo "- Validators Run: " . $validation->getMetadata()['validator_count'] . "\n";
echo "- Duration: " . $validation->getMetadata()['duration_ms'] . "ms\n";

if ($validation->hasWarnings()) {
    echo "Warnings:\n";
    foreach ($validation->getWarnings() as $warning) {
        echo "  ⚠ $warning\n";
    }
}
```

### Step 3: Handle Validation Failures

```php
if ($result->isValid()) {
    // Save valid code
    $result->saveToFile('Logger.php');
} else {
    // Log errors
    $errors = $result->getValidation()->getErrors();
    error_log('Code generation validation failed: ' . implode(', ', $errors));
    
    // Could retry manually or let agent handle it
    echo "Validation failed with " . count($errors) . " errors\n";
}
```

## Tutorial 3: Retry Logic

Handle generation failures with automatic retries.

### Step 1: Configure Retries

```php
$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $coordinator,
    'max_validation_retries' => 3, // Try up to 3 times
]);

$result = $agent->generateComponent('Create a complex class with validation');

// Check how many attempts were needed
$attempts = $result->getMetadata()['attempts'] ?? 1;
echo "Generated successfully after $attempts attempt(s)\n";
```

### Step 2: Monitor Retry Process

```php
$retries = [];

// Track retry attempts
$agent->onRetry(function (int $attempt, array $errors) use (&$retries) {
    $retries[] = [
        'attempt' => $attempt,
        'errors' => $errors,
        'timestamp' => microtime(true),
    ];
    
    echo "Retry #$attempt after validation errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
});

$result = $agent->generateComponent('Create a class that might need retries');

if (!empty($retries)) {
    echo "Total retries: " . count($retries) . "\n";
}
```

### Step 3: Handle Max Retries Exceeded

```php
use ClaudeAgents\Validation\Exceptions\MaxRetriesException;

try {
    $agent = new CodeGenerationAgent($client, [
        'validation_coordinator' => $coordinator,
        'max_validation_retries' => 2,
    ]);
    
    $result = $agent->generateComponent('Very complex requirement...');
    
    echo "✓ Generated successfully\n";
    
} catch (MaxRetriesException $e) {
    echo "✗ Failed after {$e->getAttempts()} attempts\n";
    echo "Last errors:\n";
    foreach ($e->getLastErrors() as $error) {
        echo "  - $error\n";
    }
    
    // Could notify admin, log for review, etc.
}
```

## Tutorial 4: Component Templates

Use templates for structured code generation.

### Step 1: Generate from Template

```php
use ClaudeAgents\Generation\ComponentTemplate;

// Create class template
$template = ComponentTemplate::classTemplate(
    name: 'UserRepository',
    namespace: 'App\\Repositories',
    options: [
        'properties' => [
            ['name' => 'connection', 'type' => 'PDO', 'visibility' => 'private'],
        ],
        'methods' => [
            [
                'name' => 'find',
                'params' => [['name' => 'id', 'type' => 'int']],
                'return_type' => '?User',
                'visibility' => 'public',
            ],
        ],
    ]
);

echo $template;
```

### Step 2: Combine Template with AI Generation

```php
// Use template as context for AI
$baseTemplate = ComponentTemplate::classTemplate(
    name: 'EmailService',
    namespace: 'App\\Services'
);

$description = <<<DESC
Using this base template, create an EmailService class with:
- Constructor that accepts SMTP configuration
- send() method
- validate() method for email addresses

Base template:
$baseTemplate
DESC;

$result = $agent->generateComponent($description);
```

### Step 3: Interface and Trait Templates

```php
// Generate interface
$interface = ComponentTemplate::interfaceTemplate(
    name: 'CacheInterface',
    namespace: 'App\\Contracts',
    options: [
        'methods' => [
            ['name' => 'get', 'params' => [['name' => 'key', 'type' => 'string']]],
            ['name' => 'set', 'params' => [
                ['name' => 'key', 'type' => 'string'],
                ['name' => 'value', 'type' => 'mixed'],
            ]],
        ],
    ]
);

// Generate trait
$trait = ComponentTemplate::traitTemplate(
    name: 'Timestampable',
    namespace: 'App\\Traits',
    options: [
        'methods' => [
            ['name' => 'getCreatedAt', 'return_type' => '?\DateTime'],
            ['name' => 'getUpdatedAt', 'return_type' => '?\DateTime'],
        ],
    ]
);
```

## Tutorial 5: Complex Components

Generate sophisticated components with dependencies.

### Step 1: Component with Dependencies

```php
$description = <<<'DESC'
Create a UserRepository class with:

Namespace: App\Database\Repositories

Constructor Parameters:
- PDO $connection (required)
- CacheInterface $cache (optional, default: null)

Methods:
- find(int $id): ?User - Find user by ID, use cache if available
- findAll(): array - Get all users
- save(User $user): bool - Save user to database
- delete(int $id): bool - Delete user

Include proper error handling and type hints.
DESC;

$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $coordinator,
    'max_validation_retries' => 3,
]);

$result = $agent->generateComponent($description);

if ($result->isValid()) {
    echo "✓ Complex component generated!\n";
    $result->saveToFile('app/Database/Repositories/UserRepository.php');
}
```

### Step 2: Generate with Constraints

```php
$description = <<<'DESC'
Create a PaymentProcessor class that:

Requirements:
- Must use PSR-3 LoggerInterface for logging
- Constructor should accept logger and API key
- Must validate API key is not empty in constructor
- Include methods: processPayment(), refund(), getStatus()
- All methods must return Result objects
- Include comprehensive PHPDoc comments
- Follow PSR-12 coding standards

Namespace: App\Payment
DESC;

$result = $agent->generateComponent($description);
```

### Step 3: Generate Test Alongside Code

```php
$classDescription = 'Create a StringHelper class with methods: slugify(), truncate(), random()';

// Generate main class
$classResult = $agent->generateComponent($classDescription);

if ($classResult->isValid()) {
    // Save class
    $classResult->saveToFile('StringHelper.php');
    
    // Generate test for it
    $testDescription = <<<DESC
Create a PHPUnit test class for StringHelper with:
- Test cases for slugify() method
- Test cases for truncate() method  
- Test cases for random() method
- Edge cases and error handling

Use the following class:
{$classResult->getCode()}
DESC;
    
    $testResult = $agent->generateComponent($testDescription);
    
    if ($testResult->isValid()) {
        $testResult->saveToFile('tests/StringHelperTest.php');
        echo "✓ Generated class and tests!\n";
    }
}
```

## Tutorial 6: Testing Generated Code

Automatically test AI-generated code.

### Step 1: Validate Generated Code

```php
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;
use ClaudeAgents\Validation\Validators\StaticAnalysisValidator;

// Create comprehensive validation
$coordinator = new ValidationCoordinator();

$coordinator
    ->addValidator(new PHPSyntaxValidator(['priority' => 10]))
    ->addValidator(new ComponentInstantiationValidator(['priority' => 50]))
    ->addValidator(StaticAnalysisValidator::phpstan(level: 5, priority: 100));

$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $coordinator,
]);

$result = $agent->generateComponent('Create a User class');

// All validators ran
echo "Validation complete: " . ($result->isValid() ? '✓' : '✗') . "\n";
```

### Step 2: Run Generated Code

```php
if ($result->isValid()) {
    // Save to temp file
    $tempFile = sys_get_temp_dir() . '/GeneratedClass_' . uniqid() . '.php';
    $result->saveToFile($tempFile);
    
    // Include and test
    require_once $tempFile;
    
    // Extract class name from code
    $service = new ComponentValidationService();
    $classInfo = $service->extractClassInfo($result->getCode());
    $className = $classInfo['class_name'];
    
    // Use the generated class
    $instance = new $className();
    echo "✓ Generated class instantiated successfully!\n";
    
    // Cleanup
    unlink($tempFile);
}
```

### Step 3: Integration Test Generated Code

```php
public function test_generated_code_works(): void
{
    $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
    $agent = new CodeGenerationAgent($client, [
        'validation_coordinator' => $this->coordinator,
    ]);
    
    $result = $agent->generateComponent('Create a simple Counter class');
    
    $this->assertTrue($result->isValid());
    
    // Save and test
    $tempFile = sys_get_temp_dir() . '/Counter_' . uniqid() . '.php';
    $result->saveToFile($tempFile);
    
    require_once $tempFile;
    
    // Test the generated class works
    $this->assertTrue(class_exists('Counter'));
    
    unlink($tempFile);
}
```

## Tutorial 7: CI/CD Integration

Automate code generation in your pipeline.

### Step 1: GitHub Actions Workflow

```yaml
name: Code Generation

on:
  workflow_dispatch:
    inputs:
      description:
        description: 'Component description'
        required: true

jobs:
  generate:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          
      - name: Install dependencies
        run: composer install
        
      - name: Generate Component
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
        run: |
          php scripts/generate-component.php "${{ github.event.inputs.description }}"
          
      - name: Validate Generated Code
        run: |
          vendor/bin/phpunit tests/Generated/
          
      - name: Create Pull Request
        uses: peter-evans/create-pull-request@v5
        with:
          commit-message: "feat: Add generated component"
          title: "Generated: ${{ github.event.inputs.description }}"
          body: "Auto-generated component via AI"
```

### Step 2: Generation Script

Create `scripts/generate-component.php`:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;
use ClaudePhp\ClaudePhp;

$description = $argv[1] ?? throw new \InvalidArgumentException('Description required');

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$coordinator = new ValidationCoordinator();
$coordinator
    ->addValidator(new PHPSyntaxValidator(['priority' => 10]))
    ->addValidator(new ComponentInstantiationValidator(['priority' => 50]));

$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $coordinator,
    'max_validation_retries' => 3,
]);

echo "Generating component...\n";
$result = $agent->generateComponent($description);

if ($result->isValid()) {
    // Extract class name
    $service = new ComponentValidationService();
    $classInfo = $service->extractClassInfo($result->getCode());
    $className = $classInfo['class_name'];
    
    // Save to appropriate directory
    $filename = "src/Generated/{$className}.php";
    $result->saveToFile($filename);
    
    echo "✓ Generated: $filename\n";
    exit(0);
} else {
    echo "✗ Generation failed\n";
    foreach ($result->getValidation()->getErrors() as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
```

### Step 3: Pre-commit Hook

Create `.git/hooks/pre-commit`:

```bash
#!/bin/bash

# Validate any generated code before commit
php vendor/bin/phpunit tests/Generated/ --stop-on-failure

if [ $? -ne 0 ]; then
    echo "❌ Generated code validation failed"
    exit 1
fi

echo "✓ Generated code validated"
```

## Common Patterns

### Pattern 1: Batch Generation

```php
function generateBatch(array $descriptions): array
{
    $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
    $coordinator = new ValidationCoordinator();
    $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
    
    $agent = new CodeGenerationAgent($client, [
        'validation_coordinator' => $coordinator,
    ]);
    
    $results = [];
    
    foreach ($descriptions as $desc) {
        try {
            $result = $agent->generateComponent($desc);
            $results[] = [
                'description' => $desc,
                'valid' => $result->isValid(),
                'code' => $result->getCode(),
            ];
        } catch (\Exception $e) {
            $results[] = [
                'description' => $desc,
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    return $results;
}

// Use it
$components = [
    'Create a User class',
    'Create a Product class',
    'Create an Order class',
];

$results = generateBatch($components);
```

### Pattern 2: Interactive Generation

```php
function interactiveGeneration(): void
{
    $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
    $agent = new CodeGenerationAgent($client, [
        'validation_coordinator' => $coordinator,
    ]);
    
    while (true) {
        echo "\nDescribe the component (or 'quit' to exit): ";
        $description = trim(fgets(STDIN));
        
        if ($description === 'quit') {
            break;
        }
        
        echo "Generating...\n";
        $result = $agent->generateComponent($description);
        
        if ($result->isValid()) {
            echo "✓ Code generated:\n";
            echo $result->getCodeWithLineNumbers();
            
            echo "\nSave? (y/n): ";
            $save = trim(fgets(STDIN));
            
            if ($save === 'y') {
                echo "Filename: ";
                $filename = trim(fgets(STDIN));
                $result->saveToFile($filename);
                echo "✓ Saved to $filename\n";
            }
        } else {
            echo "✗ Generation failed\n";
        }
    }
}
```

### Pattern 3: Specification-Based Generation

```php
class ComponentSpecification
{
    public function __construct(
        public string $className,
        public string $namespace,
        public array $methods = [],
        public array $properties = [],
        public ?string $extends = null,
        public array $implements = [],
    ) {}
    
    public function toDescription(): string
    {
        $desc = "Create a class {$this->className} in namespace {$this->namespace}\n";
        
        if ($this->extends) {
            $desc .= "Extends: {$this->extends}\n";
        }
        
        if (!empty($this->implements)) {
            $desc .= "Implements: " . implode(', ', $this->implements) . "\n";
        }
        
        if (!empty($this->properties)) {
            $desc .= "\nProperties:\n";
            foreach ($this->properties as $prop) {
                $desc .= "- {$prop['visibility']} {$prop['type']} \${$prop['name']}\n";
            }
        }
        
        if (!empty($this->methods)) {
            $desc .= "\nMethods:\n";
            foreach ($this->methods as $method) {
                $desc .= "- {$method['name']}({$method['params']}): {$method['return']}\n";
            }
        }
        
        return $desc;
    }
}

// Use it
$spec = new ComponentSpecification(
    className: 'PaymentGateway',
    namespace: 'App\\Payment',
    methods: [
        ['name' => 'charge', 'params' => 'float $amount', 'return' => 'bool'],
        ['name' => 'refund', 'params' => 'string $transactionId', 'return' => 'bool'],
    ]
);

$result = $agent->generateComponent($spec->toDescription());
```

## Troubleshooting

### Problem: Generated code has syntax errors

**Solution:**
```php
// Add syntax validator first
$coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));

// Increase retries
$agent = new CodeGenerationAgent($client, [
    'max_validation_retries' => 5,
]);
```

### Problem: Generation takes too long

**Solution:**
```php
// Use simpler model for faster generation
$client = new ClaudePhp(
    apiKey: getenv('ANTHROPIC_API_KEY'),
    model: 'claude-haiku-4-5', // Faster model
);
```

### Problem: Generated code doesn't match requirements

**Solution:**
```php
// Be more specific in description
$description = <<<'DESC'
Create a STRICT implementation of Calculator class:

MUST HAVE:
- Exactly these methods: add(), subtract(), multiply(), divide()
- Each method must accept two float parameters
- Each method must return float
- Include parameter validation
- Throw InvalidArgumentException for invalid inputs

MUST NOT HAVE:
- No additional methods
- No additional properties
- No dependencies
DESC;
```

## Next Steps

### Related Tutorials

- **[Component Validation Tutorial](./ComponentValidation_Tutorial.md)** - Validate generated code
- **[Testing Strategies Tutorial](./TestingStrategies_Tutorial.md)** - Test generated components
- **[Production Patterns Tutorial](./ProductionPatterns_Tutorial.md)** - Deploy generation systems

### Further Reading

- [Code Generation Guide](../code-generation-guide.md)
- [Validation System Guide](../validation-system.md)
- [Component Validation Service](../component-validation-service.md)

### Example Code

All examples from this tutorial are available in:
- `examples/tutorials/code-generation/`
- `examples/code_generation_example.php`

### What You've Learned

✓ Generate code with CodeGenerationAgent
✓ Set up validation pipelines
✓ Handle retries and failures
✓ Use component templates
✓ Generate complex components
✓ Test generated code
✓ Integrate with CI/CD
✓ Implement production patterns

**Ready for more?** Continue with the [Testing Strategies Tutorial](./TestingStrategies_Tutorial.md)!

---

*Tutorial Version: 1.0*
*Framework Version: v0.8.0+*
*Last Updated: February 2026*
