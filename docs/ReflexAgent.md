# ReflexAgent Documentation

## Overview

The `ReflexAgent` is a simple, fast, and deterministic rule-based agent that operates using condition-action mappings. It's designed for scenarios where you have well-defined rules and need predictable, immediate responses without complex reasoning.

## Features

- 🎯 **Simple Rule-Based Logic**: Define if-condition → action rules
- ⚡ **Fast & Deterministic**: No reasoning overhead, immediate responses
- 🔄 **Flexible Conditions**: Support for strings, regex, and callable functions
- 📊 **Priority-Based Matching**: Control rule evaluation order
- 🤖 **Optional LLM Fallback**: Handle unmatched inputs intelligently
- 🛠️ **Dynamic Rule Management**: Add, remove, and update rules at runtime
- 🎭 **Callable Actions**: Support for dynamic, context-aware responses

## Installation

The ReflexAgent is included in the `claude-php-agent` package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\ReflexAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$agent = new ReflexAgent($client);

// Add a simple rule
$agent->addRule(
    name: 'greeting',
    condition: 'hello',
    action: 'Hi there! How can I help you?'
);

// Run the agent
$result = $agent->run('hello world');

if ($result->isSuccess()) {
    echo $result->getAnswer(); // "Hi there! How can I help you?"
}
```

## Configuration

The ReflexAgent accepts configuration options in its constructor:

```php
$agent = new ReflexAgent($client, [
    'name' => 'my_reflex_agent',    // Agent name
    'use_llm_fallback' => true,      // Use LLM when no rule matches
    'logger' => $logger,             // PSR-3 logger instance
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'reflex_agent'` | Unique name for the agent |
| `use_llm_fallback` | bool | `true` | Use Claude LLM when no rule matches |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

## Working with Rules

### Adding Rules

Rules consist of four components:

1. **Name**: Unique identifier for the rule
2. **Condition**: When to trigger the rule (string, regex, or callable)
3. **Action**: What to do when triggered (string or callable)
4. **Priority**: Rule evaluation order (higher = checked first)

```php
$agent->addRule(
    name: 'greeting',
    condition: 'hello',
    action: 'Hi there!',
    priority: 10
);
```

### Condition Types

#### String Conditions (Substring Match)

Simple case-insensitive substring matching:

```php
$agent->addRule('help', 'help', 'How can I help you?');

$agent->run('I need help'); // Matches - contains "help"
```

#### Regex Conditions

Pattern matching using regular expressions:

```php
// Match email addresses
$agent->addRule(
    'email',
    '/\b[\w\.-]+@[\w\.-]+\.\w{2,}\b/',
    'Email detected!'
);

$agent->run('Contact me at john@example.com'); // Matches
```

#### Callable Conditions

Custom logic using functions:

```php
$agent->addRule(
    'long_input',
    fn(string $input) => strlen($input) > 50,
    'That\'s a long message!'
);

$agent->run('Very long text here...'); // Matches if > 50 chars
```

### Action Types

#### String Actions

Return a fixed response:

```php
$agent->addRule('hello', 'hello', 'Hello! How are you?');
```

#### Callable Actions

Generate dynamic responses:

```php
$agent->addRule(
    'uppercase',
    'uppercase',
    fn(string $input) => strtoupper($input)
);

$agent->run('uppercase hello'); // Returns "UPPERCASE HELLO"
```

#### Context-Aware Actions

Use closures to maintain state:

```php
$count = 0;

$agent->addRule(
    'counter',
    'count',
    function($input) use (&$count) {
        $count++;
        return "You've counted {$count} times";
    }
);
```

### Rule Priority

Rules are evaluated in priority order (highest first):

```php
// Both rules match "test", but high priority wins
$agent->addRule('low', 'test', 'Low priority', priority: 1);
$agent->addRule('high', 'test', 'High priority', priority: 10);

$result = $agent->run('test');
echo $result->getAnswer(); // "High priority"
```

### Adding Multiple Rules

Add several rules at once:

```php
$agent->addRules([
    ['name' => 'hello', 'condition' => 'hello', 'action' => 'Hi!', 'priority' => 10],
    ['name' => 'bye', 'condition' => 'bye', 'action' => 'Goodbye!', 'priority' => 9],
]);
```

### Removing Rules

Remove a rule by name:

```php
$agent->removeRule('greeting'); // Returns true if removed
```

### Clearing All Rules

Remove all rules:

```php
$agent->clearRules();
```

### Getting Rules

Retrieve all defined rules:

```php
$rules = $agent->getRules();

foreach ($rules as $rule) {
    echo "{$rule['name']} (priority: {$rule['priority']})\n";
}
```

## LLM Fallback

When no rule matches and `use_llm_fallback` is enabled, the agent uses Claude to generate a response:

```php
$agent = new ReflexAgent($client, ['use_llm_fallback' => true]);

$agent->addRule('greeting', 'hello', 'Hi!');

// No rule matches "What's the weather?"
$result = $agent->run("What's the weather?");

// LLM generates appropriate response
$metadata = $result->getMetadata();
echo $metadata['used_llm_fallback'] ? 'Used LLM' : 'Used rule';
```

### Disabling LLM Fallback

For strict rule-based behavior:

```php
$agent = new ReflexAgent($client, ['use_llm_fallback' => false]);

$agent->addRule('hello', 'hello', 'Hi!');

$result = $agent->run('goodbye'); // Fails - no matching rule
echo $result->getError(); // "No matching rule found for input"
```

## Result Handling

The agent returns an `AgentResult` object:

```php
$result = $agent->run('hello');

if ($result->isSuccess()) {
    echo $result->getAnswer();
    
    $metadata = $result->getMetadata();
    if (isset($metadata['rule_matched'])) {
        echo "Matched: {$metadata['rule_matched']}";
        echo "Priority: {$metadata['priority']}";
    }
    
    echo "Iterations: {$result->getIterations()}"; // Always 1
} else {
    echo "Error: {$result->getError()}";
}
```

## Common Patterns

### FAQ Bot

```php
$faqBot = new ReflexAgent($client);

$faqBot->addRules([
    [
        'name' => 'hours',
        'condition' => '/hours|open|close/i',
        'action' => 'We\'re open Monday-Friday 9am-5pm',
        'priority' => 10
    ],
    [
        'name' => 'location',
        'condition' => '/where|location|address/i',
        'action' => '123 Main St, City, State 12345',
        'priority' => 10
    ],
    [
        'name' => 'contact',
        'condition' => '/contact|phone|email/i',
        'action' => 'Call us: 555-1234, Email: info@example.com',
        'priority' => 10
    ],
]);

$result = $faqBot->run('What are your hours?');
```

### Input Validator

```php
$validator = new ReflexAgent($client, ['use_llm_fallback' => false]);

$validator->addRule(
    'valid_email',
    fn($input) => filter_var($input, FILTER_VALIDATE_EMAIL) !== false,
    fn($input) => "✅ Valid email: {$input}",
    priority: 10
);

$validator->addRule(
    'invalid_email',
    fn($input) => true, // Catch-all
    '❌ Invalid email format',
    priority: 1
);

$result = $validator->run('user@example.com');
```

### Simple Calculator

```php
$calc = new ReflexAgent($client, ['use_llm_fallback' => false]);

$calc->addRule(
    'add',
    '/(\d+)\s*\+\s*(\d+)/',
    function($input) {
        preg_match('/(\d+)\s*\+\s*(\d+)/', $input, $m);
        return (int)$m[1] + (int)$m[2];
    },
    priority: 10
);

$calc->addRule(
    'multiply',
    '/(\d+)\s*\*\s*(\d+)/',
    function($input) {
        preg_match('/(\d+)\s*\*\s*(\d+)/', $input, $m);
        return (int)$m[1] * (int)$m[2];
    },
    priority: 10
);

$result = $calc->run('5 + 3'); // Returns "8"
```

### Command Router

```php
$router = new ReflexAgent($client, ['use_llm_fallback' => false]);

$router->addRule(
    'deploy',
    '/^deploy (\w+)$/i',
    function($input) {
        preg_match('/^deploy (\w+)$/i', $input, $m);
        $env = $m[1];
        // Trigger deployment
        return "Deploying to {$env}...";
    },
    priority: 10
);

$router->addRule(
    'status',
    '/^status$/i',
    fn() => getSystemStatus(),
    priority: 10
);

$result = $router->run('deploy production');
```

### Context-Aware Agent

```php
$state = ['stage' => 'init', 'data' => []];

$agent = new ReflexAgent($client, ['use_llm_fallback' => false]);

$agent->addRule(
    'start',
    fn($input) => $state['stage'] === 'init',
    function($input) use (&$state) {
        $state['stage'] = 'collect_name';
        return 'What is your name?';
    },
    priority: 10
);

$agent->addRule(
    'collect_name',
    fn($input) => $state['stage'] === 'collect_name',
    function($input) use (&$state) {
        $state['data']['name'] = $input;
        $state['stage'] = 'complete';
        return "Nice to meet you, {$input}!";
    },
    priority: 10
);
```

## Best Practices

### 1. Use Specific Conditions

```php
// ❌ Too generic
$agent->addRule('test', 'a', 'Match');

// ✅ Specific and clear
$agent->addRule('email_inquiry', '/email|contact.*email/i', 'Email: contact@example.com');
```

### 2. Set Appropriate Priorities

```php
// High priority for specific matches
$agent->addRule('specific', '/urgent.*help/i', 'Urgent help response', priority: 10);

// Lower priority for general matches
$agent->addRule('general', 'help', 'General help', priority: 5);

// Lowest for catch-all
$agent->addRule('default', fn() => true, 'Default response', priority: 1);
```

### 3. Validate Inputs in Conditions

```php
$agent->addRule(
    'safe_input',
    function($input) {
        // Validate before processing
        return strlen($input) > 0 && strlen($input) < 1000;
    },
    'Valid input received',
    priority: 10
);
```

### 4. Use LLM Fallback Wisely

```php
// For customer service - use fallback
$customerBot = new ReflexAgent($client, ['use_llm_fallback' => true]);

// For strict validation - no fallback
$validator = new ReflexAgent($client, ['use_llm_fallback' => false]);
```

### 5. Keep Rules Simple

```php
// ❌ Complex logic in action
$agent->addRule('complex', 'test', function($input) {
    // 100 lines of complex logic
});

// ✅ Delegate to separate functions
$agent->addRule('simple', 'test', fn($input) => handleTest($input));
```

### 6. Document Your Rules

```php
$agent->addRule(
    name: 'payment_inquiry',
    condition: '/payment|pay|billing/i',
    action: 'Contact billing: billing@example.com',
    priority: 10
);
// Rule handles: payment questions, billing inquiries
```

## Performance Considerations

1. **Rule Count**: ReflexAgent is fast, but with hundreds of rules, consider organizing into multiple agents
2. **Regex Complexity**: Simple regex patterns are fast; very complex patterns may slow evaluation
3. **LLM Fallback**: Adds latency when triggered; use templates or specific rules for common cases
4. **Callable Actions**: Keep them lightweight; heavy processing should be async

## Comparison with Other Agents

| Feature | ReflexAgent | ReactAgent | ChainOfThoughtAgent |
|---------|-------------|------------|---------------------|
| Speed | ⚡⚡⚡ Very fast | 🔄 Medium | 🐌 Slower |
| Reasoning | ❌ None | ✅ Tool-based | ✅ Step-by-step |
| Predictability | ✅ Deterministic | 🔄 Variable | 🔄 Variable |
| Use Cases | FAQs, Routing | Complex tasks | Analysis, Planning |
| API Calls | 0-1 per run | Multiple | Multiple |

## Error Handling

The ReflexAgent handles errors gracefully:

```php
$agent = new ReflexAgent($client);

// Exception in action
$agent->addRule('error', 'error', fn() => throw new Exception('Oops'));

$result = $agent->run('error trigger');
echo $result->getError(); // "Oops"
```

## API Reference

### Constructor

```php
public function __construct(
    ClaudePhp $client,
    array $options = []
)
```

### Methods

#### `addRule(string $name, $condition, $action, int $priority = 0): self`

Add a rule to the agent.

- **$name**: Unique rule identifier
- **$condition**: string, regex pattern, or callable
- **$action**: string or callable
- **$priority**: Rule priority (higher = checked first)

#### `addRules(array $rules): self`

Add multiple rules at once.

#### `removeRule(string $name): bool`

Remove a rule by name. Returns true if removed.

#### `clearRules(): self`

Remove all rules.

#### `getRules(): array`

Get all defined rules.

#### `run(string $task): AgentResult`

Process input and return result.

#### `getName(): string`

Get the agent name.

## Troubleshooting

### Rule Not Matching

```php
// Check if rule is defined
$rules = $agent->getRules();
var_dump($rules);

// Test condition separately
$condition = fn($input) => str_contains($input, 'test');
var_dump($condition('test input')); // Should be true
```

### Wrong Rule Matching

```php
// Check rule priorities
foreach ($agent->getRules() as $rule) {
    echo "{$rule['name']}: priority {$rule['priority']}\n";
}

// Increase priority of desired rule
$agent->removeRule('wrong_rule');
$agent->addRule('right_rule', 'condition', 'action', priority: 100);
```

### LLM Fallback Not Working

```php
// Ensure fallback is enabled
$agent = new ReflexAgent($client, ['use_llm_fallback' => true]);

// Check API key is set
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
```

## See Also

- [ReflexAgent Tutorial](tutorials/ReflexAgent_Tutorial.md)
- [Basic Example](../examples/reflex_agent.php)
- [Advanced Example](../examples/advanced_reflex_agent.php)
- [Agent Selection Guide](agent-selection-guide.md)

