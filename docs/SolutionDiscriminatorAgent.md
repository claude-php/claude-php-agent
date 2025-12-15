# SolutionDiscriminatorAgent Documentation

## Overview

The `SolutionDiscriminatorAgent` is an intelligent solution evaluation system that uses Claude AI to objectively assess and compare multiple solution candidates. It provides LLM-based scoring across customizable criteria, enabling data-driven selection of the best solution from a set of alternatives.

## Features

- 🤖 **LLM-Based Evaluation**: Uses Claude to objectively score solutions against specific criteria
- 📊 **Multi-Criteria Analysis**: Define custom evaluation criteria for your specific use case
- 🏆 **Automatic Selection**: Identifies the best solution based on aggregate scores
- 📈 **Detailed Scoring**: Provides per-criterion scores for comprehensive analysis
- 🔄 **Flexible Input**: Accepts JSON arrays or single text solutions
- 📝 **Context-Aware**: Optionally provide context to guide evaluation
- 🔌 **MAKER Integration**: Can serve as voting mechanism in MAKER agent framework

## Installation

The SolutionDiscriminatorAgent is included in the `claude-php-agent` package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\SolutionDiscriminatorAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$discriminator = new SolutionDiscriminatorAgent($client);

// Define solutions to evaluate
$solutions = [
    ['id' => 'solution_a', 'description' => 'First approach...'],
    ['id' => 'solution_b', 'description' => 'Second approach...'],
];

// Evaluate and select best
$evaluations = $discriminator->evaluateSolutions($solutions);

// Or use the run method with JSON
$result = $discriminator->run(json_encode($solutions));

if ($result->isSuccess()) {
    echo $result->getAnswer(); // "Best solution: solution_b (score: 0.85)"
    
    $metadata = $result->getMetadata();
    print_r($metadata['evaluations']); // Detailed scores
}
```

## Configuration

The SolutionDiscriminatorAgent accepts configuration options in its constructor:

```php
$discriminator = new SolutionDiscriminatorAgent($client, [
    'name' => 'my_discriminator',           // Agent name
    'criteria' => ['speed', 'accuracy'],     // Evaluation criteria
    'logger' => $logger,                     // PSR-3 logger instance
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'solution_discriminator'` | Unique name for the agent |
| `criteria` | array | `['correctness', 'completeness', 'quality']` | Criteria for evaluation |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

## Evaluation Criteria

### Default Criteria

By default, solutions are evaluated on:
- **correctness**: How accurate and correct the solution is
- **completeness**: Whether the solution fully addresses the problem
- **quality**: Overall quality of implementation/approach

### Custom Criteria

Define criteria specific to your use case:

```php
// For algorithm comparison
$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['time_complexity', 'space_complexity', 'readability'],
]);

// For design evaluation
$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['scalability', 'maintainability', 'security'],
]);

// For code review
$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['testability', 'performance', 'code_quality'],
]);
```

## Methods

### evaluateSolutions()

Evaluate multiple solutions and return detailed scoring.

```php
public function evaluateSolutions(
    array $solutions, 
    ?string $context = null
): array
```

**Parameters:**
- `$solutions` (array): Array of solutions to evaluate
- `$context` (string|null): Optional context to guide evaluation

**Returns:** Array of evaluations with structure:
```php
[
    [
        'solution_id' => 'sol_1',
        'solution' => [...],
        'scores' => [
            'criterion1' => 0.85,
            'criterion2' => 0.90,
        ],
        'total_score' => 0.875,
    ],
    // ... more evaluations
]
```

**Example:**

```php
$solutions = [
    ['id' => 'bubble_sort', 'code' => '...', 'complexity' => 'O(n²)'],
    ['id' => 'quick_sort', 'code' => '...', 'complexity' => 'O(n log n)'],
];

$context = 'Need to sort large datasets efficiently';

$evaluations = $discriminator->evaluateSolutions($solutions, $context);

foreach ($evaluations as $eval) {
    echo "{$eval['solution_id']}: {$eval['total_score']}\n";
}
```

### run()

Process solutions through natural language task or JSON input.

```php
public function run(string $task): AgentResult
```

**Parameters:**
- `$task` (string): JSON array of solutions or single text solution

**Returns:** AgentResult with evaluation details

**Example:**

```php
// With JSON array
$task = json_encode([
    ['id' => 'option_a', 'details' => '...'],
    ['id' => 'option_b', 'details' => '...'],
]);

$result = $discriminator->run($task);

if ($result->isSuccess()) {
    echo $result->getAnswer(); // Best solution info
    
    $metadata = $result->getMetadata();
    $best = $metadata['best_solution'];
    $evaluations = $metadata['evaluations'];
}

// With single text
$result = $discriminator->run('Simple text solution to evaluate');
```

### getName()

Get the agent's name.

```php
public function getName(): string
```

## Use Cases

### 1. Algorithm Selection

Compare different algorithm implementations:

```php
$algorithms = [
    [
        'id' => 'approach_a',
        'algorithm' => 'Binary Search',
        'time_complexity' => 'O(log n)',
        'space_complexity' => 'O(1)',
        'implementation' => '...',
    ],
    [
        'id' => 'approach_b',
        'algorithm' => 'Hash Table Lookup',
        'time_complexity' => 'O(1) average',
        'space_complexity' => 'O(n)',
        'implementation' => '...',
    ],
];

$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['time_efficiency', 'space_efficiency', 'implementation_complexity'],
]);

$evaluations = $discriminator->evaluateSolutions(
    $algorithms,
    'Searching in frequently updated dataset with memory constraints'
);
```

### 2. Design Pattern Evaluation

Choose the best design pattern:

```php
$patterns = [
    [
        'id' => 'singleton',
        'pattern' => 'Singleton',
        'pros' => 'Global access, single instance',
        'cons' => 'Testing difficulty, hidden dependencies',
    ],
    [
        'id' => 'dependency_injection',
        'pattern' => 'Dependency Injection',
        'pros' => 'Testable, flexible, decoupled',
        'cons' => 'More complex setup',
    ],
];

$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['testability', 'maintainability', 'simplicity'],
]);

$evaluations = $discriminator->evaluateSolutions($patterns);
```

### 3. Code Review Decisions

Evaluate refactoring options:

```php
$refactorings = [
    [
        'id' => 'extract_method',
        'description' => 'Break large method into smaller methods',
        'risk' => 'Low',
        'effort' => 'Medium',
        'benefit' => 'High',
    ],
    [
        'id' => 'introduce_polymorphism',
        'description' => 'Replace conditionals with polymorphism',
        'risk' => 'Medium',
        'effort' => 'High',
        'benefit' => 'Very High',
    ],
];

$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['risk_level', 'effort_required', 'long_term_benefit'],
]);

$evaluations = $discriminator->evaluateSolutions(
    $refactorings,
    'Team has limited time, codebase lacks tests'
);
```

### 4. Architecture Comparison

Compare system architectures:

```php
$architectures = [
    [
        'id' => 'monolith',
        'type' => 'Monolithic',
        'deployment' => 'Single deployment unit',
        'scalability' => 'Vertical scaling',
    ],
    [
        'id' => 'microservices',
        'type' => 'Microservices',
        'deployment' => 'Independent services',
        'scalability' => 'Horizontal scaling',
    ],
    [
        'id' => 'modular_monolith',
        'type' => 'Modular Monolith',
        'deployment' => 'Single unit with clear boundaries',
        'scalability' => 'Vertical with migration path',
    ],
];

$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['scalability', 'deployment_complexity', 'development_speed'],
]);

$evaluations = $discriminator->evaluateSolutions(
    $architectures,
    'Startup with small team, expecting rapid growth'
);
```

### 5. Technology Stack Selection

Choose between technology stacks:

```php
$stacks = [
    [
        'id' => 'lamp',
        'stack' => 'LAMP (Linux, Apache, MySQL, PHP)',
        'maturity' => 'Very mature',
        'community' => 'Large',
        'performance' => 'Good',
    ],
    [
        'id' => 'mean',
        'stack' => 'MEAN (MongoDB, Express, Angular, Node.js)',
        'maturity' => 'Mature',
        'community' => 'Large',
        'performance' => 'Excellent',
    ],
];

$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['team_expertise', 'ecosystem', 'performance', 'hiring_pool'],
]);

$evaluations = $discriminator->evaluateSolutions($stacks);
```

## Integration with MAKER Agent

The SolutionDiscriminatorAgent can be used as a voting mechanism in the MAKER framework:

```php
use ClaudeAgents\Agents\MakerAgent;
use ClaudeAgents\Agents\SolutionDiscriminatorAgent;

$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['correctness', 'efficiency'],
]);

// MAKER agent can use discriminator for voting
$maker = new MakerAgent($client, [
    'voting_k' => 2,
    'discriminator' => $discriminator, // Custom voting
]);
```

## Solution Format

### Minimal Format

```php
$solutions = [
    ['id' => 'sol_1', 'content' => 'Solution description'],
    ['id' => 'sol_2', 'content' => 'Another solution'],
];
```

### Rich Format

Include any relevant fields for evaluation:

```php
$solutions = [
    [
        'id' => 'solution_a',
        'name' => 'Fast Algorithm',
        'description' => 'Optimized for speed',
        'code' => '...',
        'complexity' => 'O(n log n)',
        'pros' => ['Fast', 'Memory efficient'],
        'cons' => ['Complex implementation'],
        'test_results' => ['passed' => 95, 'failed' => 5],
    ],
];
```

The agent will consider all provided fields during evaluation.

## Working with Results

### Accessing Scores

```php
$evaluations = $discriminator->evaluateSolutions($solutions);

foreach ($evaluations as $eval) {
    echo "Solution: {$eval['solution_id']}\n";
    echo "Total Score: {$eval['total_score']}\n";
    echo "Criteria:\n";
    
    foreach ($eval['scores'] as $criterion => $score) {
        echo "  - {$criterion}: " . number_format($score, 3) . "\n";
    }
}
```

### Sorting by Score

```php
// Sort descending by total score
usort($evaluations, fn($a, $b) => $b['total_score'] <=> $a['total_score']);

$best = $evaluations[0];
$worst = end($evaluations);
```

### Finding Top N Solutions

```php
// Get top 3 solutions
$top3 = array_slice($evaluations, 0, 3);

foreach ($top3 as $i => $eval) {
    echo ($i + 1) . ". {$eval['solution_id']} - {$eval['total_score']}\n";
}
```

### Analyzing Criteria

```php
// Find which criterion had highest variance
$criterionScores = [];

foreach ($evaluations as $eval) {
    foreach ($eval['scores'] as $criterion => $score) {
        $criterionScores[$criterion][] = $score;
    }
}

foreach ($criterionScores as $criterion => $scores) {
    $variance = variance($scores);
    echo "{$criterion}: variance = {$variance}\n";
}
```

## Error Handling

The agent handles evaluation failures gracefully:

```php
$result = $discriminator->run($task);

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    $evaluations = $metadata['evaluations'];
    $best = $metadata['best_solution'];
} else {
    // Handle failure
    echo "Evaluation failed: {$result->getError()}\n";
}
```

Individual criterion evaluation failures default to 0.5 (neutral score) to allow the evaluation to continue.

## Performance Considerations

### API Calls

The agent makes one API call per solution per criterion:

```
Total calls = number_of_solutions × number_of_criteria
```

For 3 solutions with 3 criteria = 9 API calls.

### Optimization Strategies

1. **Limit criteria** to most important ones
2. **Batch solutions** when possible
3. **Cache evaluations** for repeated comparisons
4. **Use context** to guide evaluation more efficiently

```php
// Good: 2 criteria = 6 calls for 3 solutions
$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['correctness', 'efficiency'],
]);

// Less optimal: 5 criteria = 15 calls for 3 solutions
$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['a', 'b', 'c', 'd', 'e'],
]);
```

## Best Practices

### 1. Choose Meaningful Criteria

```php
// Good - specific, measurable
$criteria = ['time_complexity', 'space_complexity', 'code_readability'];

// Less good - vague
$criteria = ['good', 'better', 'best'];
```

### 2. Provide Rich Context

```php
// Good - detailed context
$context = "E-commerce platform with 1M+ users, " .
           "high read volume, moderate writes, " .
           "team of 5 developers, PHP 8.2, MySQL 8.0";

$evaluations = $discriminator->evaluateSolutions($solutions, $context);

// Less good - minimal context
$context = "web app";
```

### 3. Include Relevant Solution Details

```php
// Good - comprehensive
$solution = [
    'id' => 'approach_a',
    'algorithm' => 'Quick Sort',
    'time_complexity' => 'O(n log n) average, O(n²) worst',
    'space_complexity' => 'O(log n)',
    'stable' => false,
    'in_place' => true,
    'code' => '...',
    'benchmarks' => ['small' => '10ms', 'large' => '500ms'],
];

// Less good - minimal
$solution = ['id' => 'a', 'name' => 'Quick Sort'];
```

### 4. Validate Before Evaluating

```php
if (empty($solutions)) {
    throw new InvalidArgumentException('No solutions to evaluate');
}

if (count($solutions) < 2) {
    echo "Only one solution - no comparison needed\n";
    return $solutions[0];
}

$evaluations = $discriminator->evaluateSolutions($solutions);
```

### 5. Log Evaluations for Analysis

```php
$logger = new Logger('discriminator');

$discriminator = new SolutionDiscriminatorAgent($client, [
    'logger' => $logger,
]);

// Evaluations are logged automatically
$evaluations = $discriminator->evaluateSolutions($solutions);

// Log results
foreach ($evaluations as $eval) {
    $logger->info('Evaluation', [
        'solution_id' => $eval['solution_id'],
        'total_score' => $eval['total_score'],
        'scores' => $eval['scores'],
    ]);
}
```

## Troubleshooting

### Low Score Variance

If all solutions get similar scores:

1. **Add more specific criteria**
2. **Provide more context** to guide evaluation
3. **Include more differentiating details** in solutions

```php
// Before: all scores ~0.75
$criteria = ['quality'];

// After: more variance
$criteria = ['time_complexity', 'space_complexity', 'maintainability'];
```

### Unexpected Best Solution

If the selected solution doesn't match expectations:

1. **Review criteria** - ensure they align with your goals
2. **Check context** - provide more specific requirements
3. **Examine per-criterion scores** to understand the decision

```php
$evaluations = $discriminator->evaluateSolutions($solutions, $context);

// Analyze the best solution
$best = array_reduce($evaluations, fn($c, $i) => 
    (!$c || $i['total_score'] > $c['total_score']) ? $i : $c
);

print_r($best['scores']); // See which criteria drove the decision
```

### API Rate Limits

With many solutions/criteria, you may hit rate limits:

```php
// Break into batches
$batches = array_chunk($solutions, 5);

$allEvaluations = [];
foreach ($batches as $batch) {
    $evaluations = $discriminator->evaluateSolutions($batch);
    $allEvaluations = array_merge($allEvaluations, $evaluations);
    sleep(1); // Rate limit breathing room
}
```

## API Reference

### Constructor

```php
public function __construct(ClaudePhp $client, array $options = [])
```

### Methods

#### `evaluateSolutions(array $solutions, ?string $context = null): array`
Evaluate multiple solutions with optional context.

#### `run(string $task): AgentResult`
Process solutions through JSON or text input.

#### `getName(): string`
Get the agent name.

## See Also

- [SolutionDiscriminatorAgent Tutorial](tutorials/SolutionDiscriminatorAgent_Tutorial.md)
- [Examples](../examples/solution_discriminator_example.php)
- [MAKER Agent Documentation](MakerAgent.md)
- [Agent Selection Guide](agent-selection-guide.md)

