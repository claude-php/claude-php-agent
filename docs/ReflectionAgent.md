# ReflectionAgent

The `ReflectionAgent` implements the Generate-Reflect-Refine pattern for iterative quality improvement. This approach enables the agent to produce output, critically evaluate its quality, and progressively refine it until a quality threshold is met or maximum refinements are reached.

## Table of Contents

- [Overview](#overview)
- [Key Concepts](#key-concepts)
- [Features](#features)
- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Configuration Options](#configuration-options)
- [How It Works](#how-it-works)
- [Use Cases](#use-cases)
- [Best Practices](#best-practices)
- [API Reference](#api-reference)

## Overview

The Reflection pattern is a meta-cognitive approach where an AI system generates output, reflects on its quality, and iteratively refines it. This leads to higher-quality results by:

1. **Generating** an initial response to the task
2. **Reflecting** on the output's quality against specified criteria
3. **Refining** the output based on the reflection feedback
4. **Repeating** steps 2-3 until quality threshold is met

This pattern is particularly effective for tasks requiring high-quality output such as writing, code generation, and complex problem-solving.

## Key Concepts

### The Reflection Loop

The agent follows a systematic improvement cycle:

```
Task → Generate → Reflect → Quality OK? → Done
                     ↓                ↓
                     └─── Refine ←────┘
```

### Quality Evaluation

Each reflection produces:
- A **quality score** (1-10) evaluating the current output
- **Constructive feedback** on what works and what needs improvement
- **Specific suggestions** for refinement

### Termination Conditions

The loop terminates when either:
- The quality threshold is met (score ≥ configured threshold)
- Maximum refinement iterations are reached

## Features

- ✅ Iterative quality improvement through reflection
- ✅ Configurable quality thresholds
- ✅ Customizable evaluation criteria
- ✅ Automatic score extraction from reflections
- ✅ Detailed reflection history tracking
- ✅ Token usage monitoring
- ✅ PSR-3 logging support
- ✅ Type-safe configuration
- ✅ Comprehensive test coverage

## Installation

The `ReflectionAgent` is included in the `claude-php-agent` package:

```bash
composer require claude-php-agent
```

## Basic Usage

### Simple Reflection

```php
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new ReflectionAgent($client, [
    'max_refinements' => 3,
    'quality_threshold' => 8,
]);

$result = $agent->run(
    'Write a PHP function to validate email addresses.'
);

if ($result->isSuccess()) {
    echo $result->getAnswer();
    
    // Check reflection history
    $metadata = $result->getMetadata();
    foreach ($metadata['reflections'] as $reflection) {
        echo "Iteration {$reflection['iteration']}: ";
        echo "Score {$reflection['score']}/10\n";
    }
}
```

### With Custom Criteria

```php
$agent = new ReflectionAgent($client, [
    'max_refinements' => 3,
    'quality_threshold' => 8,
    'criteria' => 'code correctness, error handling, documentation, and PHP best practices',
]);

$result = $agent->run(
    'Create a function to parse CSV files with proper error handling.'
);
```

### Writing Improvement

```php
$writingAgent = new ReflectionAgent($client, [
    'name' => 'writing_agent',
    'max_refinements' => 2,
    'quality_threshold' => 8,
    'criteria' => 'clarity, engagement, grammar, structure, and persuasiveness',
]);

$result = $writingAgent->run(
    'Write a compelling product description for a smart home thermostat.'
);
```

## Configuration Options

### Constructor Parameters

```php
new ReflectionAgent(ClaudePhp $client, array $options = [])
```

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | `string` | `'reflection_agent'` | Agent identifier |
| `model` | `string` | `'claude-sonnet-4-5'` | Claude model to use |
| `max_tokens` | `int` | `2048` | Maximum tokens per API call |
| `max_refinements` | `int` | `3` | Maximum refinement iterations |
| `quality_threshold` | `int` | `8` | Quality score (1-10) to stop refining |
| `criteria` | `string\|null` | Default criteria | Custom evaluation criteria |
| `logger` | `LoggerInterface` | `NullLogger` | PSR-3 logger instance |

### Default Criteria

If no custom criteria is provided, the agent evaluates based on:
- Correctness
- Completeness
- Clarity
- Quality

### Quality Threshold

The quality threshold (1-10 scale) determines when the agent stops refining:

- **6-7**: Basic quality, suitable for drafts
- **8**: Good quality, suitable for most use cases
- **9-10**: Excellent quality, for critical applications

## How It Works

### Step 1: Initial Generation

The agent generates an initial response to your task:

```php
$output = $agent->run('Write a function to calculate fibonacci numbers');
// Generates initial implementation
```

### Step 2: Reflection

The agent critically evaluates the output:

```
What's working well?
- Function structure is clear
- Basic logic is correct

What issues exist?
- No input validation
- Missing edge case handling
- Lacks documentation

How can it be improved?
- Add parameter type hints
- Handle negative numbers
- Add PHPDoc comments

Overall quality score: 6/10
```

### Step 3: Refinement

Based on the reflection, the agent improves the output:

```php
// Refined output includes:
// - Input validation
// - Edge case handling
// - Complete documentation
```

### Step 4: Iteration

Steps 2-3 repeat until:
- Quality score meets or exceeds the threshold, OR
- Maximum refinements reached

## Use Cases

### 1. Code Generation

```php
$codeAgent = new ReflectionAgent($client, [
    'criteria' => 'correctness, type safety, error handling, documentation, and best practices',
    'quality_threshold' => 9,
    'max_refinements' => 3,
]);

$result = $codeAgent->run(
    'Create a PHP class for database connection pooling with retry logic'
);
```

### 2. Content Writing

```php
$contentAgent = new ReflectionAgent($client, [
    'criteria' => 'clarity, engagement, SEO optimization, and call-to-action effectiveness',
    'quality_threshold' => 8,
    'max_refinements' => 2,
]);

$result = $contentAgent->run(
    'Write a blog post introduction about the benefits of cloud computing'
);
```

### 3. Technical Documentation

```php
$docAgent = new ReflectionAgent($client, [
    'criteria' => 'technical accuracy, clarity, completeness, and practical examples',
    'quality_threshold' => 8,
    'max_refinements' => 3,
]);

$result = $docAgent->run(
    'Write API documentation for a RESTful authentication endpoint'
);
```

### 4. Problem Solving

```php
$problemAgent = new ReflectionAgent($client, [
    'criteria' => 'solution completeness, feasibility, efficiency, and consideration of edge cases',
    'quality_threshold' => 8,
    'max_refinements' => 3,
]);

$result = $problemAgent->run(
    'Design a caching strategy for a high-traffic e-commerce website'
);
```

### 5. Creative Writing

```php
$creativeAgent = new ReflectionAgent($client, [
    'criteria' => 'creativity, originality, emotional impact, and narrative flow',
    'quality_threshold' => 8,
    'max_refinements' => 2,
]);

$result = $creativeAgent->run(
    'Write an engaging opening paragraph for a science fiction story'
);
```

## Best Practices

### 1. Choose Appropriate Thresholds

Match your threshold to the task criticality:

```php
// For critical code (production systems)
$criticalAgent = new ReflectionAgent($client, [
    'quality_threshold' => 9,
    'max_refinements' => 5,
]);

// For drafts and prototypes
$draftAgent = new ReflectionAgent($client, [
    'quality_threshold' => 7,
    'max_refinements' => 2,
]);
```

### 2. Define Clear Criteria

Be specific about what quality means for your task:

```php
// Vague (less effective)
'criteria' => 'make it good'

// Specific (more effective)
'criteria' => 'code correctness, type safety, error handling, documentation, and PSR-12 compliance'
```

### 3. Balance Quality vs. Cost

More refinements = better quality but higher token usage:

```php
// Track costs
$result = $agent->run($task);
$metadata = $result->getMetadata();
$totalTokens = $metadata['token_usage']['total'];

echo "Task completed in {$result->getIterations()} iterations\n";
echo "Used {$totalTokens} tokens\n";
echo "Final score: {$metadata['final_score']}/10\n";
```

### 4. Monitor Reflection History

Learn from the improvement process:

```php
$result = $agent->run($task);

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    foreach ($metadata['reflections'] as $reflection) {
        echo "Iteration {$reflection['iteration']}: ";
        echo "Score {$reflection['score']}/10\n";
        echo "Feedback: {$reflection['feedback']}\n\n";
    }
}
```

### 5. Handle Edge Cases

```php
$result = $agent->run($task);

if (!$result->isSuccess()) {
    error_log("Reflection failed: " . $result->getError());
} else {
    $metadata = $result->getMetadata();
    
    // Check if quality threshold was met
    if ($metadata['final_score'] < 8) {
        error_log("Warning: Quality threshold not met after max iterations");
    }
}
```

### 6. Use Logging for Insights

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('reflection');
$logger->pushHandler(new StreamHandler('logs/reflection.log', Logger::DEBUG));

$agent = new ReflectionAgent($client, [
    'logger' => $logger,
]);

// Logger will record:
// - Task initiation
// - Each reflection score
// - When quality threshold is met
// - Any errors
```

### 7. Domain-Specific Agents

Create specialized agents for different domains:

```php
class AgentFactory
{
    public static function createCodeAgent(ClaudePhp $client): ReflectionAgent
    {
        return new ReflectionAgent($client, [
            'name' => 'code_reflection_agent',
            'criteria' => 'correctness, type safety, error handling, documentation, and best practices',
            'quality_threshold' => 9,
            'max_refinements' => 3,
        ]);
    }
    
    public static function createWritingAgent(ClaudePhp $client): ReflectionAgent
    {
        return new ReflectionAgent($client, [
            'name' => 'writing_reflection_agent',
            'criteria' => 'clarity, engagement, grammar, and persuasiveness',
            'quality_threshold' => 8,
            'max_refinements' => 2,
        ]);
    }
}

$codeAgent = AgentFactory::createCodeAgent($client);
$writingAgent = AgentFactory::createWritingAgent($client);
```

## API Reference

### ReflectionAgent Class

```php
class ReflectionAgent implements AgentInterface
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

Execute the reflection cycle on a task.

**Parameters:**
- `$task` - The task description or question

**Returns:**
- `AgentResult` object containing:
  - `answer` - The final refined output
  - `iterations` - Total iterations (generate + reflect + refine cycles)
  - `metadata` - Contains:
    - `token_usage` - Input/output/total tokens
    - `reflections` - Array of reflection history
    - `final_score` - Quality score of the final output
  - `success` - Whether the task completed successfully

**Example:**

```php
$result = $agent->run('Write a function to parse JSON safely');

if ($result->isSuccess()) {
    echo $result->getAnswer();
    
    $metadata = $result->getMetadata();
    echo "Final score: {$metadata['final_score']}/10\n";
    echo "Iterations: {$result->getIterations()}\n";
}
```

##### getName(): string

Get the agent's name.

**Returns:**
- The agent's configured name

### AgentResult Class

Result object returned by the agent.

```php
// Success check
$result->isSuccess(): bool

// Get the final refined output
$result->getAnswer(): string

// Get error message (if failed)
$result->getError(): string

// Get metadata including reflections and scores
$result->getMetadata(): array

// Get total iteration count
$result->getIterations(): int

// Get token usage
$result->getTokenUsage(): array
```

### Metadata Structure

```php
[
    'token_usage' => [
        'input' => 1500,
        'output' => 800,
        'total' => 2300,
    ],
    'reflections' => [
        [
            'iteration' => 1,
            'score' => 6,
            'feedback' => 'Initial feedback...',
        ],
        [
            'iteration' => 2,
            'score' => 8,
            'feedback' => 'Improved feedback...',
        ],
    ],
    'final_score' => 8,
]
```

## Advanced Usage

### Adaptive Quality Thresholds

```php
function createAdaptiveAgent(ClaudePhp $client, string $taskComplexity): ReflectionAgent
{
    $config = match($taskComplexity) {
        'simple' => ['quality_threshold' => 7, 'max_refinements' => 1],
        'moderate' => ['quality_threshold' => 8, 'max_refinements' => 2],
        'complex' => ['quality_threshold' => 9, 'max_refinements' => 4],
        default => ['quality_threshold' => 8, 'max_refinements' => 3],
    };
    
    return new ReflectionAgent($client, $config);
}
```

### Combining with Other Patterns

```php
// Use Reflection after Chain-of-Thought
$cotAgent = new ChainOfThoughtAgent($client);
$reflectionAgent = new ReflectionAgent($client, [
    'criteria' => 'logical consistency and step completeness',
]);

$cotResult = $cotAgent->run($complexProblem);
$refinedResult = $reflectionAgent->run(
    "Review and improve this solution:\n\n" . $cotResult->getAnswer()
);
```

### Batch Processing with Quality Tracking

```php
$tasks = [
    'Task 1 description',
    'Task 2 description',
    'Task 3 description',
];

$results = [];
$totalTokens = 0;
$averageScore = 0;

foreach ($tasks as $i => $task) {
    $result = $agent->run($task);
    
    if ($result->isSuccess()) {
        $metadata = $result->getMetadata();
        $results[] = [
            'task' => $task,
            'answer' => $result->getAnswer(),
            'score' => $metadata['final_score'],
            'iterations' => $result->getIterations(),
        ];
        
        $totalTokens += $metadata['token_usage']['total'];
        $averageScore += $metadata['final_score'];
    }
}

$averageScore /= count($results);

echo "Processed " . count($results) . " tasks\n";
echo "Average quality score: " . round($averageScore, 1) . "/10\n";
echo "Total tokens used: {$totalTokens}\n";
```

## Troubleshooting

### Common Issues

**Problem:** Agent always hits max refinements without meeting threshold

**Solution:** Lower your quality threshold or increase max refinements:

```php
$agent = new ReflectionAgent($client, [
    'quality_threshold' => 7, // Lower threshold
    'max_refinements' => 5,   // More attempts
]);
```

**Problem:** Quality doesn't improve between iterations

**Solution:** Make your criteria more specific:

```php
// Too vague
'criteria' => 'quality'

// Better
'criteria' => 'specific issue 1, specific issue 2, and specific issue 3'
```

**Problem:** High token usage

**Solution:** Reduce refinements or use more focused tasks:

```php
$agent = new ReflectionAgent($client, [
    'max_refinements' => 2, // Fewer iterations
    'max_tokens' => 1024,   // Smaller responses
]);
```

**Problem:** Inconsistent score extraction

**Solution:** The agent looks for patterns like "Score: 7/10" or "Quality: 8". Ensure your criteria prompts clear scoring.

## Examples

See the `examples/reflection_agent.php` file for comprehensive working examples including:
- Code generation with reflection
- Writing improvement
- Custom criteria
- Reflection history tracking

Run the example:

```bash
export ANTHROPIC_API_KEY='your-api-key'
php examples/reflection_agent.php
```

## Further Reading

- [Tutorial: Reflection Pattern](tutorials/ReflectionAgent_Tutorial.md)
- [Agent Selection Guide](agent-selection-guide.md)
- [Research: Self-Reflection in Language Models](https://arxiv.org/abs/2303.11366)

## License

This component is part of the claude-php-agent package and is licensed under the MIT License.

