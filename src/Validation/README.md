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
- **CustomScriptValidator** - Execute custom validation scripts
- **LLMReviewValidator** - AI-powered code review

### Exceptions

- **ValidationException** - Base validation exception
- **MaxRetriesException** - Thrown when max retries exceeded

## Features

- ✅ **Composable** - Combine multiple validators
- 🎯 **Prioritized** - Validators run in priority order
- 🔧 **Extensible** - Easy to create custom validators
- 💾 **Cacheable** - Avoid redundant validation
- 📊 **Informative** - Rich error and warning information

## Documentation

- [Validation System Guide](../../docs/validation-system.md)
- [Agentic Module Architecture](../../docs/agentic-module-architecture.md)

## Examples

See [examples/validation_example.php](../../examples/validation_example.php) for complete examples.
