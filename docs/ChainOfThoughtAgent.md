# ChainOfThoughtAgent

The `ChainOfThoughtAgent` implements Chain-of-Thought (CoT) reasoning, a powerful prompting technique that encourages step-by-step problem solving. This approach is particularly effective for complex reasoning tasks including mathematics, logic puzzles, and decision-making scenarios.

## Table of Contents

- [Overview](#overview)
- [Key Concepts](#key-concepts)
- [Features](#features)
- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Configuration Options](#configuration-options)
- [CoT Modes](#cot-modes)
- [Example Patterns](#example-patterns)
- [Best Practices](#best-practices)
- [API Reference](#api-reference)

## Overview

Chain-of-Thought reasoning breaks down complex problems into intermediate steps, making the reasoning process transparent and more reliable. The agent supports two main approaches:

1. **Zero-shot CoT**: Uses trigger phrases to elicit step-by-step reasoning without examples
2. **Few-shot CoT**: Provides examples of reasoning patterns to guide the model

## Key Concepts

### Chain-of-Thought Reasoning

CoT reasoning improves AI performance by:
- Making reasoning explicit and verifiable
- Breaking complex problems into manageable steps
- Reducing errors through systematic analysis
- Providing insights into the model's decision process

### Zero-shot vs Few-shot

**Zero-shot CoT**: Simply add a trigger phrase like "Let's think step by step" to your prompt. This is:
- Simple and flexible
- Works across diverse problem types
- Requires no example preparation

**Few-shot CoT**: Provide examples showing the desired reasoning pattern. This is:
- More consistent for specific problem types
- Better for domain-specific reasoning
- Requires upfront example creation

## Features

- ✅ Zero-shot and few-shot CoT modes
- ✅ Predefined trigger phrases and examples
- ✅ Customizable reasoning patterns
- ✅ Built-in examples for math, logic, and decision-making
- ✅ Token usage tracking
- ✅ PSR-3 logging support
- ✅ Type-safe configuration
- ✅ Comprehensive test coverage

## Installation

The `ChainOfThoughtAgent` is included in the `claude-php-agent` package:

```bash
composer require claude-php-agent
```

## Basic Usage

### Zero-shot CoT

```php
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Reasoning\CoTPrompts;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'trigger' => CoTPrompts::zeroShotTrigger(),
]);

$result = $agent->run(
    'If a book costs $15 with a 20% discount, what is the final price?'
);

echo $result->getAnswer();
// Output will show step-by-step reasoning:
// Step 1: Calculate discount: 20% of $15 = $3
// Step 2: Subtract from original: $15 - $3 = $12
// Final Answer: $12
```

### Few-shot CoT

```php
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::mathExamples(),
]);

$result = $agent->run(
    'A train travels 120 miles in 2 hours. What is its average speed?'
);

echo $result->getAnswer();
// Output shows reasoning following the example pattern
```

## Configuration Options

### Constructor Parameters

```php
new ChainOfThoughtAgent(ClaudePhp $client, array $options = [])
```

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | `string` | `'cot_agent'` | Agent identifier |
| `mode` | `string` | `'zero_shot'` | CoT mode: `'zero_shot'` or `'few_shot'` |
| `trigger` | `string` | `"Let's think step by step."` | Trigger phrase for zero-shot mode |
| `examples` | `array` | Math examples | Few-shot examples for pattern learning |
| `logger` | `LoggerInterface` | `NullLogger` | PSR-3 logger instance |

## CoT Modes

### Zero-shot Mode

Zero-shot CoT uses trigger phrases to activate step-by-step reasoning:

```php
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'trigger' => "Let's approach this systematically.",
]);
```

**Available Trigger Phrases:**

```php
use ClaudeAgents\Reasoning\CoTPrompts;

$triggers = CoTPrompts::zeroShotTriggers();
// Returns:
// - "Let's think step by step."
// - "Let's work this out systematically."
// - "Let's break this down."
// - "Let's approach this logically."
// - "Let's reason through this."
```

### Few-shot Mode

Few-shot CoT learns from examples:

```php
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::mathExamples(),
]);
```

**Built-in Example Sets:**

```php
// Math problems
$mathExamples = CoTPrompts::mathExamples();

// Logic puzzles
$logicExamples = CoTPrompts::logicExamples();

// Decision-making
$decisionExamples = CoTPrompts::decisionExamples();
```

**Custom Examples:**

```php
$customExamples = [
    [
        'question' => 'How many legs do 3 cats and 2 dogs have?',
        'answer' => "Step 1: Count cat legs: 3 cats × 4 legs = 12 legs\n" .
                   "Step 2: Count dog legs: 2 dogs × 4 legs = 8 legs\n" .
                   "Step 3: Add together: 12 + 8 = 20 legs\n" .
                   "Final Answer: 20 legs"
    ],
];

$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => $customExamples,
]);
```

## Example Patterns

### Mathematical Reasoning

```php
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
]);

$result = $agent->run(
    'A store sells apples at $2 each or 3 for $5. ' .
    'How much do you save buying 6 apples with the deal?'
);
```

### Logic Puzzles

```php
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::logicExamples(),
]);

$result = $agent->run(
    'If all roses are flowers and some flowers are red, ' .
    'can we conclude that some roses are red?'
);
```

### Decision Making

```php
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::decisionExamples(),
]);

$result = $agent->run(
    'Should a startup prioritize growth or profitability in year one?'
);
```

### Multi-step Problems

```php
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'trigger' => "Let's break this down step by step.",
]);

$result = $agent->run(
    'A recipe serves 4 people and needs 2 cups of flour. ' .
    'How much flour for 10 people? Round to nearest 1/4 cup.'
);
```

## Best Practices

### 1. Choose the Right Mode

**Use Zero-shot when:**
- Problem type varies significantly
- You need flexibility
- Examples are hard to create
- Quick prototyping

**Use Few-shot when:**
- Problem type is consistent
- You have domain-specific patterns
- Accuracy is critical
- You need specific output formatting

### 2. Craft Effective Trigger Phrases

Good triggers are:
- Clear and direct
- Action-oriented
- Appropriate for the problem domain

```php
// Good triggers by domain
$mathTrigger = "Let's solve this step by step.";
$logicTrigger = "Let's think through this logically.";
$planningTrigger = "Let's break this down systematically.";
$debugTrigger = "Let's analyze this carefully.";
```

### 3. Create Quality Examples

For few-shot CoT, provide examples that:
- Show clear reasoning steps
- Match your problem domain
- Demonstrate the desired output format
- Are neither too simple nor too complex

```php
$examples = [
    [
        'question' => 'Specific, clear question',
        'answer' => "Step 1: First reasoning step\n" .
                   "Step 2: Next reasoning step\n" .
                   "Step 3: Final calculation\n" .
                   "Final Answer: Clear conclusion"
    ],
];
```

### 4. Handle Results Properly

```php
$result = $agent->run($task);

if ($result->isSuccess()) {
    // Access the answer
    $answer = $result->getAnswer();
    
    // Check metadata
    $metadata = $result->getMetadata();
    $tokensUsed = $metadata['tokens']['input'] + $metadata['tokens']['output'];
    $mode = $metadata['reasoning_mode'];
    
    // Log for monitoring
    echo "Tokens used: {$tokensUsed}\n";
} else {
    // Handle errors
    error_log("CoT Agent failed: " . $result->getError());
}
```

### 5. Monitor Performance

```php
use Psr\Log\LoggerInterface;

$agent = new ChainOfThoughtAgent($client, [
    'logger' => $logger, // Your PSR-3 logger
]);

// Logger will record:
// - Task initiation
// - Errors and exceptions
```

### 6. Optimize Token Usage

```php
// Track token usage across multiple runs
$totalTokens = 0;

foreach ($tasks as $task) {
    $result = $agent->run($task);
    if ($result->isSuccess()) {
        $metadata = $result->getMetadata();
        $totalTokens += $metadata['tokens']['input'] + $metadata['tokens']['output'];
    }
}

echo "Total tokens used: {$totalTokens}\n";
```

## API Reference

### ChainOfThoughtAgent Class

```php
class ChainOfThoughtAgent implements AgentInterface
```

#### Constructor

```php
public function __construct(
    ClaudePhp $client,
    array $options = []
)
```

#### Methods

##### run(string $task): AgentResult

Execute the agent with a reasoning task.

**Parameters:**
- `$task` - The problem or question to reason about

**Returns:**
- `AgentResult` object containing:
  - `answer` - The step-by-step reasoning and conclusion
  - `iterations` - Number of iterations (always 1 for CoT)
  - `metadata` - Contains `reasoning_mode` and `tokens` information
  - `success` - Whether the task completed successfully

**Example:**

```php
$result = $agent->run('What is 15% of 200?');

if ($result->isSuccess()) {
    echo $result->getAnswer();
    print_r($result->getMetadata());
}
```

##### getName(): string

Get the agent's name.

**Returns:**
- The agent's configured name

### CoTPrompts Class

Static utility class for CoT prompts and examples.

#### Methods

##### zeroShotTrigger(): string

Get the default zero-shot trigger phrase.

```php
$trigger = CoTPrompts::zeroShotTrigger();
// Returns: "Let's think step by step."
```

##### zeroShotTriggers(): array

Get all available trigger phrases.

```php
$triggers = CoTPrompts::zeroShotTriggers();
// Returns array of 5+ trigger phrases
```

##### mathExamples(): array

Get pre-built math reasoning examples.

```php
$examples = CoTPrompts::mathExamples();
```

##### logicExamples(): array

Get pre-built logic reasoning examples.

```php
$examples = CoTPrompts::logicExamples();
```

##### decisionExamples(): array

Get pre-built decision-making examples.

```php
$examples = CoTPrompts::decisionExamples();
```

##### fewShotSystem(array $examples): string

Build a system prompt from examples.

```php
$systemPrompt = CoTPrompts::fewShotSystem($myExamples);
```

### AgentResult Class

Result object returned by the agent.

```php
// Success check
$result->isSuccess(): bool

// Get the answer
$result->getAnswer(): string

// Get error message (if failed)
$result->getError(): string

// Get metadata
$result->getMetadata(): array

// Get iteration count
$result->getIterations(): int
```

## Advanced Usage

### Combining with Other Patterns

```php
// Use CoT with validation
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
]);

$result = $agent->run($task);

if ($result->isSuccess()) {
    // Validate the reasoning
    $answer = $result->getAnswer();
    
    if (str_contains($answer, 'Final Answer:')) {
        // Extract and use the conclusion
        preg_match('/Final Answer:\s*(.+)$/m', $answer, $matches);
        $conclusion = $matches[1] ?? '';
    }
}
```

### Integration with Logging Systems

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('cot-agent');
$logger->pushHandler(new StreamHandler('logs/cot.log', Logger::INFO));

$agent = new ChainOfThoughtAgent($client, [
    'logger' => $logger,
    'name' => 'production-cot',
]);
```

### Performance Tuning

```php
// For faster responses, use zero-shot (fewer tokens)
$fastAgent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
]);

// For better accuracy, use few-shot with domain examples
$accurateAgent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => $domainSpecificExamples,
]);
```

## Troubleshooting

### Common Issues

**Problem:** Reasoning is incomplete or skips steps

**Solution:** Use few-shot mode with detailed examples:

```php
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::mathExamples(), // Shows detailed steps
]);
```

**Problem:** Output format is inconsistent

**Solution:** Provide examples with consistent formatting:

```php
$examples = [
    [
        'question' => '...',
        'answer' => "Step 1: ...\nStep 2: ...\nFinal Answer: ..."
    ],
];
```

**Problem:** Token usage is too high

**Solution:** Use zero-shot mode and simpler trigger phrases:

```php
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'trigger' => "Let's think step by step.",
]);
```

## Examples

See the `examples/cot_example.php` file for comprehensive working examples including:
- Zero-shot math problems
- Few-shot with different example sets
- Logic puzzles
- Decision-making scenarios
- Custom trigger phrases

Run the example:

```bash
export ANTHROPIC_API_KEY='your-api-key'
php examples/cot_example.php
```

## Further Reading

- [Tutorial: Chain-of-Thought Reasoning](tutorials/ChainOfThoughtAgent_Tutorial.md)
- [Agent Selection Guide](agent-selection-guide.md)
- [Research Paper: Chain-of-Thought Prompting](https://arxiv.org/abs/2201.11903)

## License

This component is part of the claude-php-agent package and is licensed under the MIT License.

