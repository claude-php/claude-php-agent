# Builder Pattern

Type-safe, fluent agent configuration using the Builder Pattern for readable, maintainable, and validated configurations.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Building Configurations](#building-configurations)
- [Validation](#validation)
- [Templates](#templates)
- [Integration](#integration)
- [Best Practices](#best-practices)
- [Examples](#examples)

## Overview

The **Builder Pattern** provides a fluent, method-chaining API for constructing `AgentConfig` objects. Instead of passing arrays or manually creating config objects, you use `AgentConfigBuilder` for:

- ✅ **Type Safety**: IDE autocomplete and type checking
- ✅ **Validation**: Automatic validation of configuration values
- ✅ **Readability**: Clear, self-documenting code
- ✅ **Immutability**: Configurations don't change unexpectedly
- ✅ **Reusability**: Save and reuse configuration templates

### Problem Without Builder

```php
// ❌ Array-based: No type safety, easy to make mistakes
$config = [
    'name' => 'agent1',
    'model' => 'claude-opus-4-20250514',
    'max_iterations' => 10,
    'temperature' => 0.7,
    'max_tokens' => 4096,
    'system_prompt' => 'You are helpful',
];

// What if you typo 'max_iteratons'?
// What if you set temperature to 5.0 (invalid)?
// No IDE autocomplete
```

### Solution With Builder

```php
// ✅ Fluent, type-safe, validated
$config = AgentConfigBuilder::create()
    ->name('agent1')
    ->model('claude-opus-4-20250514')
    ->maxIterations(10)
    ->temperature(0.7)
    ->maxTokens(4096)
    ->systemPrompt('You are helpful')
    ->build();

// IDE autocomplete works
// Validation happens automatically
// Clear and readable
```

## Quick Start

### Basic Usage

```php
use ClaudeAgents\Builder\AgentConfigBuilder;

$config = AgentConfigBuilder::create()
    ->name('my_agent')
    ->model('claude-sonnet-4-20250514')
    ->maxIterations(15)
    ->temperature(0.8)
    ->build();

// Use with factory
$agent = $factory->create('react', $config->toArray());
```

### Minimal Configuration

Only `name` is required:

```php
$config = AgentConfigBuilder::create()
    ->name('simple_agent')
    ->build();

// Uses defaults:
// - model: 'claude-sonnet-4-20250514'
// - max_iterations: 10
// - temperature: 0.7
// - max_tokens: 4096
```

## Building Configurations

### Core Methods

#### name(string $name)
Set the agent name (required).

```php
$builder->name('customer_support_agent');
```

#### model(string $model)
Set the Claude model.

```php
$builder->model('claude-opus-4-20250514');
$builder->model('claude-sonnet-4-20250514');
$builder->model('claude-haiku-4-20250514');
```

#### maxIterations(int $iterations)
Set maximum reasoning loops (1-100).

```php
$builder->maxIterations(20);
```

**Validation**: Must be between 1 and 100.

#### temperature(float $temperature)
Set response creativity (0.0-1.0).

```php
$builder->temperature(0.7);  // Balanced
$builder->temperature(0.0);  // Deterministic
$builder->temperature(1.0);  // Creative
```

**Validation**: Must be between 0.0 and 1.0.

#### maxTokens(int $tokens)
Set maximum response length.

```php
$builder->maxTokens(8000);
```

**Validation**: Must be at least 1.

#### systemPrompt(string $prompt)
Set system instructions.

```php
$builder->systemPrompt('You are a helpful coding assistant with expertise in PHP and Python.');
```

### Chaining Methods

All methods return `$this`, so you can chain:

```php
$config = AgentConfigBuilder::create()
    ->name('agent')
    ->model('opus')
    ->maxIterations(10)
    ->temperature(0.7)
    ->maxTokens(4096)
    ->systemPrompt('Be helpful')
    ->build();
```

### Building the Configuration

Call `build()` to create the `AgentConfig`:

```php
$config = $builder->build();

// Returns an AgentConfig object
assert($config instanceof AgentConfig);
```

## Validation

The builder automatically validates all values:

### Name Validation

```php
// ✅ Valid
$builder->name('agent1');
$builder->name('my-agent');
$builder->name('agent_123');

// ❌ Invalid - throws InvalidArgumentException
$builder->name('');  // Empty name
```

### Model Validation

```php
// ✅ Valid - any non-empty string
$builder->model('claude-opus-4-20250514');
$builder->model('custom-model');

// ❌ Invalid
$builder->model('');  // Empty model
```

### Max Iterations Validation

```php
// ✅ Valid
$builder->maxIterations(1);
$builder->maxIterations(10);
$builder->maxIterations(100);

// ❌ Invalid - throws InvalidArgumentException
$builder->maxIterations(0);    // Too low
$builder->maxIterations(101);  // Too high
```

### Temperature Validation

```php
// ✅ Valid
$builder->temperature(0.0);
$builder->temperature(0.7);
$builder->temperature(1.0);

// ❌ Invalid - throws InvalidArgumentException
$builder->temperature(-0.1);  // Too low
$builder->temperature(1.1);   // Too high
```

### Max Tokens Validation

```php
// ✅ Valid
$builder->maxTokens(1);
$builder->maxTokens(4096);
$builder->maxTokens(100000);

// ❌ Invalid - throws InvalidArgumentException
$builder->maxTokens(0);   // Too low
$builder->maxTokens(-1);  // Negative
```

### Catching Validation Errors

```php
try {
    $config = AgentConfigBuilder::create()
        ->name('agent')
        ->temperature(2.0)  // Invalid!
        ->build();
} catch (InvalidArgumentException $e) {
    echo "Configuration error: " . $e->getMessage();
    // "Temperature must be between 0.0 and 1.0"
}
```

## Templates

Create reusable configuration templates:

### Template Class

```php
class AgentTemplates
{
    public static function customerSupport(): AgentConfig
    {
        return AgentConfigBuilder::create()
            ->name('support')
            ->model('claude-sonnet-4-20250514')
            ->temperature(0.7)
            ->maxIterations(5)
            ->systemPrompt('You are a helpful customer support agent...')
            ->build();
    }
    
    public static function dataAnalyst(): AgentConfig
    {
        return AgentConfigBuilder::create()
            ->name('analyst')
            ->model('claude-opus-4-20250514')
            ->temperature(0.3)
            ->maxIterations(15)
            ->systemPrompt('You are a data analyst with expertise in...')
            ->build();
    }
    
    public static function codeReviewer(): AgentConfig
    {
        return AgentConfigBuilder::create()
            ->name('reviewer')
            ->model('claude-opus-4-20250514')
            ->temperature(0.2)  // More deterministic
            ->maxIterations(10)
            ->systemPrompt('You are an expert code reviewer...')
            ->build();
    }
}

// Usage
$config = AgentTemplates::customerSupport();
$agent = $factory->create('dialog', $config->toArray());
```

### Template Customization

Start with a template and customize:

```php
// Create base template
$baseConfig = AgentConfigBuilder::create()
    ->model('claude-sonnet-4-20250514')
    ->maxIterations(10)
    ->temperature(0.7);

// Customize for different agents
$agent1Config = clone $baseConfig
    ->name('agent1')
    ->systemPrompt('You are agent 1')
    ->build();

$agent2Config = clone $baseConfig
    ->name('agent2')
    ->systemPrompt('You are agent 2')
    ->build();
```

### Configuration Presets

```php
class ConfigPresets
{
    public static function fast(): AgentConfigBuilder
    {
        return AgentConfigBuilder::create()
            ->model('claude-haiku-4-20250514')
            ->maxIterations(5)
            ->maxTokens(2048);
    }
    
    public static function balanced(): AgentConfigBuilder
    {
        return AgentConfigBuilder::create()
            ->model('claude-sonnet-4-20250514')
            ->maxIterations(10)
            ->maxTokens(4096);
    }
    
    public static function powerful(): AgentConfigBuilder
    {
        return AgentConfigBuilder::create()
            ->model('claude-opus-4-20250514')
            ->maxIterations(20)
            ->maxTokens(8000);
    }
}

// Usage
$config = ConfigPresets::powerful()
    ->name('complex_agent')
    ->systemPrompt('Solve complex problems')
    ->build();
```

## Integration

### With AgentFactory

```php
$factory = new AgentFactory($client, $logger);

$config = AgentConfigBuilder::create()
    ->name('my_agent')
    ->model('claude-opus-4-20250514')
    ->maxIterations(15)
    ->build();

$agent = $factory->create('react', $config->toArray());
```

### With Agent Constructor

```php
$config = AgentConfigBuilder::create()
    ->name('direct_agent')
    ->build();

$agent = new ReactAgent($client, $config, $logger);
```

### From Existing Config

```php
// Load config from somewhere
$existingConfig = AgentConfig::create('old_agent');

// Modify using builder
$newConfig = AgentConfigBuilder::create()
    ->name($existingConfig->getName())
    ->model($existingConfig->getModel())
    ->maxIterations($existingConfig->getMaxIterations() + 5)  // Increase
    ->temperature($existingConfig->getTemperature())
    ->build();
```

## Best Practices

### 1. Always Use Builder for Complex Configs

**❌ Don't:**
```php
$config = [
    'name' => 'agent',
    'model' => 'claude-opus-4-20250514',
    'max_iterations' => 20,
    'temperature' => 0.8,
];
```

**✅ Do:**
```php
$config = AgentConfigBuilder::create()
    ->name('agent')
    ->model('claude-opus-4-20250514')
    ->maxIterations(20)
    ->temperature(0.8)
    ->build();
```

### 2. Create Templates for Common Configs

```php
// ❌ Don't: Repeat configuration
$agent1 = $factory->create('react', [
    'name' => 'agent1',
    'model' => 'opus',
    'temp' => 0.7,
    'system_prompt' => 'Long prompt...',
]);

$agent2 = $factory->create('react', [
    'name' => 'agent2',
    'model' => 'opus',
    'temp' => 0.7,
    'system_prompt' => 'Long prompt...',
]);

// ✅ Do: Use template
$template = AgentConfigBuilder::create()
    ->model('opus')
    ->temperature(0.7)
    ->systemPrompt('Long prompt...');

$agent1 = $factory->create('react', (clone $template)->name('agent1')->build()->toArray());
$agent2 = $factory->create('react', (clone $template)->name('agent2')->build()->toArray());
```

### 3. Validate Early

```php
// ✅ Build validates immediately
try {
    $config = AgentConfigBuilder::create()
        ->name('agent')
        ->temperature(5.0)  // Invalid!
        ->build();
} catch (InvalidArgumentException $e) {
    // Handle configuration error before agent creation
    logError($e);
    return;
}

// Agent creation happens only with valid config
$agent = $factory->create('react', $config->toArray());
```

### 4. Use Method Names, Not Magic Strings

**❌ Less Clear:**
```php
$config['max_iterations'] = 10;  // What's the valid range?
```

**✅ More Clear:**
```php
$builder->maxIterations(10);  // IDE shows type, PHPDoc shows range
```

### 5. Combine with Factory

```php
// Builder for configuration
$config = AgentConfigBuilder::create()
    ->name('agent')
    ->model('opus')
    ->maxIterations(15)
    ->build();

// Factory for creation
$agent = $factory->createReactAgent($config->toArray());

// Best of both patterns!
```

### 6. Document Your Templates

```php
class AgentTemplates
{
    /**
     * Customer support agent configuration.
     * 
     * - Balanced model for cost/performance
     * - Lower iterations for faster responses
     * - Moderate temperature for helpful but consistent replies
     */
    public static function customerSupport(): AgentConfig
    {
        return AgentConfigBuilder::create()
            ->name('support')
            ->model('claude-sonnet-4-20250514')
            ->maxIterations(5)
            ->temperature(0.7)
            ->systemPrompt('...')
            ->build();
    }
}
```

## Examples

### Simple Configuration

```php
$config = AgentConfigBuilder::create()
    ->name('simple_agent')
    ->build();
```

### Full Configuration

```php
$config = AgentConfigBuilder::create()
    ->name('full_agent')
    ->model('claude-opus-4-20250514')
    ->maxIterations(20)
    ->temperature(0.8)
    ->maxTokens(8000)
    ->systemPrompt('You are an expert AI assistant specializing in software development.')
    ->build();
```

### Configuration Variants

```php
// Fast and cheap
$fastConfig = AgentConfigBuilder::create()
    ->name('fast')
    ->model('claude-haiku-4-20250514')
    ->maxIterations(3)
    ->build();

// Balanced
$balancedConfig = AgentConfigBuilder::create()
    ->name('balanced')
    ->model('claude-sonnet-4-20250514')
    ->maxIterations(10)
    ->build();

// Powerful
$powerfulConfig = AgentConfigBuilder::create()
    ->name('powerful')
    ->model('claude-opus-4-20250514')
    ->maxIterations(25)
    ->maxTokens(8000)
    ->build();
```

### Multi-Agent Configuration

```php
$configs = [
    'researcher' => AgentConfigBuilder::create()
        ->name('researcher')
        ->model('claude-opus-4-20250514')
        ->maxIterations(15)
        ->systemPrompt('You research topics thoroughly')
        ->build(),
    
    'writer' => AgentConfigBuilder::create()
        ->name('writer')
        ->model('claude-sonnet-4-20250514')
        ->maxIterations(10)
        ->systemPrompt('You write clear, engaging content')
        ->build(),
    
    'editor' => AgentConfigBuilder::create()
        ->name('editor')
        ->model('claude-sonnet-4-20250514')
        ->maxIterations(5)
        ->temperature(0.3)  // More consistent
        ->systemPrompt('You edit and refine content')
        ->build(),
];

foreach ($configs as $name => $config) {
    $agents[$name] = $factory->create('react', $config->toArray());
}
```

## See Also

- [Factory Pattern](Factory.md) - Agent creation
- [Design Patterns](DesignPatterns.md) - All patterns overview
- [Best Practices](BestPractices.md) - Configuration patterns
- [Examples](../examples/builder_pattern_example.php) - Working code

## API Reference

### AgentConfigBuilder

```php
class AgentConfigBuilder
{
    // Create new builder
    public static function create(): self;
    
    // Required
    public function name(string $name): self;
    
    // Optional (with defaults)
    public function model(string $model): self;                    // Default: 'claude-sonnet-4-20250514'
    public function maxIterations(int $iterations): self;          // Default: 10, Range: 1-100
    public function temperature(float $temperature): self;         // Default: 0.7, Range: 0.0-1.0
    public function maxTokens(int $tokens): self;                  // Default: 4096, Min: 1
    public function systemPrompt(string $prompt): self;            // Default: null
    
    // Build configuration
    public function build(): AgentConfig;
}
```

### AgentConfig

```php
class AgentConfig
{
    public function getName(): string;
    public function getModel(): string;
    public function getMaxIterations(): int;
    public function getTemperature(): float;
    public function getMaxTokens(): int;
    public function getSystemPrompt(): ?string;
    
    public function toArray(): array;
}
```

### Validation Rules

| Method | Type | Validation |
|--------|------|------------|
| `name()` | string | Non-empty |
| `model()` | string | Non-empty |
| `maxIterations()` | int | 1-100 |
| `temperature()` | float | 0.0-1.0 |
| `maxTokens()` | int | >= 1 |
| `systemPrompt()` | string | Any |

