# Code Generation

AI-powered code generation with validation and retry logic.

## Quick Start

```php
use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$validator = new ValidationCoordinator();
$validator->addValidator(new PHPSyntaxValidator());

$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $validator,
    'max_validation_retries' => 3,
]);

$result = $agent->generateComponent(
    'Create a Calculator class with add and subtract methods'
);

if ($result->isValid()) {
    echo $result->getCode();
    $result->saveToFile('Calculator.php');
}
```

## Components

### Core Classes

- **CodeGenerationAgent** - Main agent for code generation with retry logic
- **ComponentResult** - Generated code with validation result
- **ComponentTemplate** - Template system for common patterns
- **GeneratorInterface** - Interface for custom generators

## Features

- 🤖 **AI-Powered** - Uses Claude for intelligent code generation
- ✅ **Automatic Validation** - Multi-stage validation with retry
- 🔄 **Retry Logic** - Automatic retry with improved prompts
- 📡 **Streaming** - Real-time progress updates
- 📋 **Templates** - Pre-built templates for common patterns

## Templates

Generate code from templates:

```php
use ClaudeAgents\Generation\ComponentTemplate;

// Class template
$class = ComponentTemplate::classTemplate(
    name: 'User',
    namespace: 'App\\Models',
    options: ['properties' => [...], 'methods' => [...]]
);

// Interface template
$interface = ComponentTemplate::interfaceTemplate(
    name: 'CacheInterface',
    namespace: 'App\\Contracts'
);

// Service template
$service = ComponentTemplate::serviceTemplate(
    name: 'EmailService',
    namespace: 'App\\Services',
    options: ['dependencies' => [...]]
);
```

## Documentation

- [Code Generation Guide](../../docs/code-generation-guide.md)
- [Agentic Module Architecture](../../docs/agentic-module-architecture.md)

## Examples

See [examples/code_generation_example.php](../../examples/code_generation_example.php) for complete examples.
